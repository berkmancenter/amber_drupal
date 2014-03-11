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

  return $commands;
}

function drush_cayl_scan($nid = NULL) {

  if (!$nid) {
    drush_set_error(dt("Specify either a node ID to scan, or 'all'."));
    return FALSE;
  }
  if ($nid) {
    if ($nid == 'all') {
      $query = new EntityFieldQuery();
      $query->entityCondition('entity_type', 'node')
        ->propertyCondition('status', 1);
      $result = $query->execute();
      if (!empty($result['node'])) {
        $nids = array_keys($result['node']);
        foreach ($nids as $nid) {
          $node = node_load($nid, NULL, TRUE);
          if (!$node) {
            drush_set_error(dt("Could not load node"));
            return FALSE;
          }
          cayl_cache_node($node);
        }
      }
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



function drush_cayl_clear_cache() {
  cayl_clear_cache();
  drush_print("Cache cleared");
}