<?php
/**
 * Functions relating to queue management and batch processing
 */

/**
 * Implements hook_cron
 */
function cayl_cron() {
  if (variable_get('cayl_enabled', 1)) {
    $status_service = cayl_get_status();
    $urls = $status_service->get_urls_to_check();
    if ($urls) {
      cayl_enqueue_check_links($urls, CAYL_QUEUE_CHECK_EXISTING);
    }
  }
}

/**
 * Add a list of URLs to the queue of items to be cached.
 * @param $links array of URLs to be cached
 */
function cayl_enqueue_cache_links($links) {
  $queue = DrupalQueue::get(CAYL_QUEUE_CACHE);
  $queue->createQueue();
  foreach ($links as $link) {
    if ($link) {
      $queue->createItem($link);
    }
  }
}

/**
 * Add a list of URLs to a queue of links to be checked. We have multiple queues for checking links so that
 * new links (which we want to check soon) don't get stuck behind a backlog of existing links to recheck
 * @param $links array of URLs to be cached
 */
function cayl_enqueue_check_links($links, $queue_name = CAYL_QUEUE_CHECK_NEW) {
  $queue = DrupalQueue::get($queue_name);
  $queue->createQueue();
  foreach ($links as $link) {
    if ($link) {
      $queue->createItem($link);
    }
  }
}

/**
 * Implements hook_cron_queue_info
 */
function cayl_cron_queue_info() {
  return array(
    CAYL_QUEUE_CHECK_NEW  => array(
      'worker callback' => 'cayl_process_check_queue',
    ),
    CAYL_QUEUE_CHECK_EXISTING => array(
      'worker callback' => 'cayl_process_check_queue',
    ),
    CAYL_QUEUE_CACHE => array(
      'worker callback' => 'cayl_process_cache_queue',
    ),
  );
}

/**
 * Setup a batch (for use with the Batch API) to scan all nodes on the site for links to cache
 * @return array|null
 */
function cayl_batch_scan_all_setup() {
  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'node')->propertyCondition('status', 1);
  $result = $query->execute();
  if (!empty($result['node'])) {
    $operations = array();
    $nids = array_keys($result['node']);
    foreach ($nids as $nid) {
      $operations[] = array('cayl_batch_scan_process', array($nid));
    }
  }
  if (!empty($operations)) {
    $batch = array(
      'operations' => $operations,
      'finished' => 'cayl_batch_scan_all_finished',
    );
    return $batch;
  } else {
    return null;
  }
}

/**
 * Scan a node for links to cache (for use with the Batch API)
 * @param $nid string if of the node to scan
 * @param $context
 */
function cayl_batch_scan_process($nid, $context) {
  $node = node_load($nid, NULL, TRUE);
  if ($node) {
    cayl_cache_node($node);
    $context['results'][] = $node->nid . ' : ' . check_plain($node->title);
  }
  $context['message'] = t('Scanned node "@title"', array('@title' => $node->title));
}

/**
 * Final step in batch processing to scan nodes for links to cache
 * @param $success
 * @param $results
 * @param $operations
 */
function cayl_batch_scan_all_finished($success, $results, $operations) {
  if ($success) {
    drupal_set_message(t('@count nodes scanned', array('@count' => count($results))));
  } else {
    $error_operation = reset($operations);
    drupal_set_message(t('An error occurred while processing @operation with arguments : @args', array('@operation' => $error_operation[0], '@args' => print_r($error_operation[0], TRUE))));
  }
}

/**
 * Setup a batch (for use with the Batch API) to cache all links scheduled for caching immediately
 * Rather than adding URLs to the batch, we add items containing the name of a queue which holds an item to be cached,
 * @return array|null
 */

function cayl_batch_cache_all_setup() {
  foreach (array(CAYL_QUEUE_CHECK_NEW, CAYL_QUEUE_CHECK_EXISTING) as $queue_name) {
    $queue = DrupalQueue::get($queue_name);
    $queue->createQueue();
    $queue_size = $queue->numberOfItems();
    for ($i = 0; $i < $queue_size; $i++) {
      $operations[] = array('cayl_batch_cache_process', array($queue_name));
    }
  }
  if (!empty($operations)) {
    $batch = array(
      'operations' => $operations,
      'finished' => 'cayl_batch_cache_all_finished',
    );
    return $batch;
  } else {
    return null;
  }
}

/**
 * Take an item with a queue name from the batch, and retrieve the next item from that queue
 * @param $item name of the queue from which to pull an object to be checked
 * @param $context
 */
function cayl_batch_cache_process($queue_name, $context) {
  $fetcher = cayl_get_fetcher();
  $checker = cayl_get_checker();
  $status_service = cayl_get_status();
  $queue = DrupalQueue::get($queue_name);
  $queue->createQueue();
  if ($queue->numberOfItems() == 0) {
    return;
  }
  $item = $queue->claimItem(60);
  if (!$item) {
    return;
  }
  $url = $item->data;
  $existing_cache = $status_service->get_cache($url);
  if (!variable_get('cayl_update_strategy',0) || !$existing_cache) {
    watchdog('cayl', "Checking and caching: @url ", array('@url' => $url), WATCHDOG_DEBUG);
    $last_check = $status_service->get_check($url);
    if (($update = $checker->check(empty($last_check) ? array('url' => $url) : $last_check)) !== false) {
      $status_service->save_check($update);
      $cache_metadata = array();
      try {
        $cache_metadata = $fetcher->fetch($url);
      } catch (RuntimeException $re) {
        watchdog('cayl', "Did not cache: @url. @message", array('@url' => $url, '@message' => $re->getMessage()), WATCHDOG_NOTICE);
      }
      if ($cache_metadata) {
        $status_service->save_cache($cache_metadata);
        cayl_disk_space_purge();
      }
    }
  } else {
    watchdog('cayl', "Updating of cached items disabled, did not cache: @url ", array('@url' => $url), WATCHDOG_DEBUG);
  }
  $queue->deleteItem($item);
  $context['message'] = t('Cached URL "@url"', array('@url' => $url));
}

/**
 * Final step in batch processing to cache all links
 * @param $success
 * @param $results
 * @param $operations
 */
function cayl_batch_cache_all_finished($success, $results, $operations) {
  if ($success) {
    drupal_set_message(t('@count URLs cached', array('@count' => count($results))));
    cache_clear_all('*', 'cache_filter',TRUE);
    cache_clear_all('*', 'cache_field',TRUE);
  } else {
    $error_operation = reset($operations);
    drupal_set_message(t('An error occurred while processing @operation with arguments : @args', array('@operation' => $error_operation[0], '@args' => print_r($error_operation, TRUE))));
  }
}

/**
 * Process an item in the queue to be cached
 * @param $item string url of the item to be cached
 */
function cayl_process_cache_queue($item) {
  watchdog('cayl', "Caching: @url ", array('@url' => $item), WATCHDOG_DEBUG);
  try {
    $fetcher = cayl_get_fetcher();
    $status_service = cayl_get_status();
    $existing_cache = $status_service->get_cache($item);
    /* Make sure that the item either doesn't already exist, or that we are allowed to update existing cached items */
    if (!variable_get('cayl_update_strategy',0) || !$existing_cache) {
      $cache_metadata = array();
      try {
        $cache_metadata = $fetcher->fetch($item);
      } catch (RuntimeException $re) {
        watchdog('cayl', "Did not cache: @url. @message", array('@url' => $item, '@message' => $re->getMessage()), WATCHDOG_NOTICE);
      }
      if ($cache_metadata) {
        $status_service->save_cache($cache_metadata);
        /* Clear caches that could contain HTML with versions of the links that don't contain data- attributes */
        /* TODO: Ideally we would clear the cache only once per cron job */
        cache_clear_all('*', 'cache_filter',TRUE);
        cache_clear_all('*', 'cache_field',TRUE);
        cayl_disk_space_purge();
      }
    }
    variable_set(CAYL_VAR_LAST_CACHE_RUN,time());
  } catch (Exception $e) {
    // If there is an error, log the error, and then continue. Otherwise the bad item will not be removed from the queue
    watchdog('cayl', "Error caching @url : @msg ", array('@url' => $item, '@msg' => $e->getMessage()), WATCHDOG_NOTICE);
  }
}

/**
 * Process and item in the queue to be checked
 * @param $item string url of the item to be checked
 */
function cayl_process_check_queue($item, $force = false) {
  watchdog('cayl', "Checking: @url ", array('@url' => $item), WATCHDOG_DEBUG);
  try {
    $checker = cayl_get_checker();
    $status = cayl_get_status();
    $last_check = $status->get_check($item);
    if (($update = $checker->check(empty($last_check) ? array('url' => $item) : $last_check, $force)) !== false) {
      /* There's an updated check result to save */
      $status->save_check($update);
      cayl_enqueue_cache_links(array($item));
    }
    variable_set(CAYL_VAR_LAST_CHECK_RUN,time());
  } catch (Exception $e) {
    // If there is an error, log the error, and then continue. Otherwise the bad item will not be removed from the queue
    watchdog('cayl', "Error checking @url : @msg ", array('@url' => $item, '@msg' => $e->getMessage()), WATCHDOG_NOTICE);
  }
}

/**
 * If the total disk space usage is over the configured limit, delete enough items to bring it under
 */
function cayl_disk_space_purge() {
  $status = cayl_get_status();
  $purge = $status->get_items_to_purge(variable_get('cayl_max_disk',1000) * 1024 * 1024);
  if ($purge) {
    $storage = cayl_get_storage();
    foreach ($purge as $item) {
      $storage->clear_cache_item($item['id']);
      $status->delete($item['id']);
      watchdog('cayl', "Deleting to stay under disk space limits: @url", array('@url' => $item['url']), WATCHDOG_NOTICE);
    }
  }
}