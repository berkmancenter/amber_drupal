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
 * @return array|null
 */

function cayl_batch_cache_all_setup() {
  foreach (array(CAYL_QUEUE_CHECK_NEW, CAYL_QUEUE_CHECK_EXISTING) as $queue_name) {
    $queue = DrupalQueue::get($queue_name);
    $queue->createQueue();
    while ($item = $queue->claimItem()) {
      //TODO: What happens if the batch is cancelled? How do we ensure the items can be processed again?
      $operations[] = array('cayl_batch_cache_process', array($item, $queue));
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
 * Take a queue item with a URL, and check AND cache it immediately
 * @param $item object from a queue with the url to be checked
 * @param $queue object where the item came from. Required so we can remove the item from the queue once processed
 * @param $context
 */
function cayl_batch_cache_process($item, $queue, $context) {
  $fetcher = cayl_get_fetcher();
  $checker = cayl_get_checker();
  $status_service = cayl_get_status();
  $url = $item->data;
  watchdog('cayl', "Checking and caching: @url ", array('@url' => $url), WATCHDOG_DEBUG);
  $last_check = $status_service->get_check($url);
  if (($update = $checker->check(empty($last_check) ? array('url' => $url) : $last_check)) !== false) {
    $status_service->save_check($update);
    $fetcher->fetch($url);
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
    drupal_set_message(t('An error occurred while processing @operation with arguments : @args', array('@operation' => $error_operation[0], '@args' => print_r($error_operation[0], TRUE))));
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
    $cache_metadata = $fetcher->fetch($item);
    if ($cache_metadata !== false) {
      /* Clear caches that could contain HTML with versions of the links that don't contain data- attributes */
      /* TODO: Ideally we would clear the cache only once per cron job */
      cache_clear_all('*', 'cache_filter',TRUE);
      cache_clear_all('*', 'cache_field',TRUE);
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
function cayl_process_check_queue($item) {
  watchdog('cayl', "Checking: @url ", array('@url' => $item), WATCHDOG_DEBUG);
  try {
    $checker = cayl_get_checker();
    $status = cayl_get_status();
    $last_check = $status->get_check($item);
    if (($update = $checker->check(empty($last_check) ? array('url' => $item) : $last_check)) !== false) {
      /* There's an updated check result to save */
      $status->save_check($update);

      //TODO: Check configuration settings that might say we shouldn't update cache for existing items
      cayl_enqueue_cache_links(array($item));
    }
    variable_set(CAYL_VAR_LAST_CHECK_RUN,time());
  } catch (Exception $e) {
    // If there is an error, log the error, and then continue. Otherwise the bad item will not be removed from the queue
    watchdog('cayl', "Error checking @url : @msg ", array('@url' => $item, '@msg' => $e->getMessage()), WATCHDOG_NOTICE);
  }

}

