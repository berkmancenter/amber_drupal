<?php
/**
 * Functions relating to queue management and batch processing
 */

/**
 * Implements hook_cron
 */
function cayl_cron() {
  $status_service = cayl_get_status();
  $urls = $status_service->get_urls_to_check();
  if ($urls) {
    cayl_enqueue_check_links($urls, CAYL_QUEUE_CHECK_EXISTING);
  }
}

/**
 * Add a list of URLs to the queue of items to be cached
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
      $status_service->save_cache($cache_metadata);
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