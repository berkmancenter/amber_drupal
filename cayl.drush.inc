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
      'node' => 'The ID node of the scan',
    ),
  );

  return $commands;
}

function drush_cayl_scan($nid = NULL) {

  if (!$nid) {
    drush_set_error(dt("Node ID required"));
    return FALSE;
  }
  if ($nid) {
    $node = node_load($nid);
    if (!$node) {
      drush_set_error(dt("Could not load node"));
      return FALSE;
    }
    cayl_cache_node($node);
    drush_print("Queued links for caching");
  }

}