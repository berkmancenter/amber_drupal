<?php
/**
 * Functions relating to queue management and batch processing
 */

/**
 * Add a list of URLs to the queue of items to be cached
 * @param $links array of URLs to be cached
 */
function cayl_enqueue_cache_links($links) {
  $queue = DrupalQueue::get('cayl_cache');
  $queue->createQueue();
  foreach ($links as $link) {
    if ($link) {
      $queue->createItem($link);
    }
  }
}

/**
 * Process an item in the queue to be cached
 * @param $item
 */
function cayl_process_cache_queue($item) {
  watchdog('cayl', "Processing: @url ", array('@url' => $item), WATCHDOG_DEBUG);
  try {
    $fetcher = cayl_get_fetcher();
    $fetcher->fetch($item);
  } catch (Exception $e) {
    // If there is an error on fetching, log the error, and then continue. Otherwise the bad item
    // will not be removed from the queue
    watchdog('cayl', "Error processing @url : @msg ", array('@url' => $item, '@msg' => $e->getMessage()), WATCHDOG_NOTICE);
  }
}

/**
 * Implements hook_cron_queue_info
 */
function cayl_cron_queue_info() {
  return array(
    'cayl_cache' => array(
      'worker callback' => 'cayl_process_cache_queue',
    )
  );
}