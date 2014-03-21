<?php

/**
 * @file
 * Drush interface to CAYL
 */

/**
 * Implements hook_drush_command().
 */
function cayl_drush_command() {
  $commands = array();

  $commands['cayl-scan'] = array(
    'description' => 'Scans content for links to cache',
    'arguments' => array(
      'node' => 'The ID node of the scan, or \'all\'',
    ),
  );
  $commands['cayl-clear-cache'] = array(
    'description' => 'Delete the contents of the cache',
  );
  $commands['cayl-cache-url'] = array(
    'description' => 'Cache a single URL',
    'arguments' => array(
      'url' => 'A URL to save to the cache',
    ),
    'aliases' => array('ccu'),
  );

  $commands['cayl-check-up'] = array(
    'description' => 'Check a single URL to see if it is up',
    'arguments' => array(
      'url' => 'A URL to check',
    ),
    'aliases' => array('cup'),
  );

  $commands['cayl-check-url'] = array(
    'description' => 'Run the availability check a single URL',
    'arguments' => array(
      'url' => 'A URL to check',
    ),
  );

  return $commands;
}

/**
 * Scan a single node, or all nodes, for links to cache
 * @param null $nid
 * @return bool
 */
function drush_cayl_scan($nid = NULL) {
  if (!$nid) {
    drush_set_error(dt("Specify either a node ID to scan, or 'all'."));
    return FALSE;
  }
  if ($nid) {
    if ($nid == 'all') {
      cayl_cache_all();
    } else {
      $node = node_load($nid);
      if (!$node) {
        drush_set_error(dt("Could not load node"));
        return FALSE;
      }
      cayl_cache_node($node);
    }
    drush_print("Queued links for caching");
  }
}

/**
 * Delete the entire cache
 */
function drush_cayl_clear_cache() {
  cayl_clear_cache();
  drush_print("Cache cleared");
}

function drush_cayl_cache_url($url) {
  if ($url) {
    cayl_process_cache_queue($url);
    drush_print("URL fetched");
  } else {
    drush_set_error(dt("Specify a URL to cache"));
    return FALSE;
  }
}

function drush_cayl_check_up($url) {
  if ($url) {
    $checker = cayl_get_checker();
    if ($checker->up($url)) {
      drush_print("Up");
    } else {
      drush_print("Down");
    }
  } else {
    drush_set_error(dt("Specify a URL to check"));
    return FALSE;
  }
}

function drush_cayl_check_url($url) {
  if ($url) {
    cayl_process_check_queue($url);
    drush_print("URL checked");
  } else {
    drush_set_error(dt("Specify a URL to check"));
    return FALSE;
  }
}


