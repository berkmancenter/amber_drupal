<?php

function cayl_dashboard_summary_form() {

  $header = array(t("Global Statistics"),"");
  $rows = array();
  $rows[] = array(t('Number of items in the cache'), _cayl_cache_size());
  $rows[] = array(t('Number of items queued to be cached or updated'),_cayl_queue_size(CAYL_QUEUE_CACHE));
  $rows[] = array(
    t('Number of items queued to be checked for availability'),
    _cayl_queue_size(CAYL_QUEUE_CHECK_NEW) + _cayl_queue_size(CAYL_QUEUE_CHECK_EXISTING));
  $rows[] = array(t('Last time the caching process ran'), _cayl_last_run(CAYL_VAR_LAST_CACHE_RUN));
  $rows[] = array(t('Last time the website checking process ran'), _cayl_last_run(CAYL_VAR_LAST_CHECK_RUN));
  $rows[] = array(t('Disk space used'), _cayl_disk_usage());

  $build['cayl_summary_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );

  $build['buttons']['clear'] = array(
    '#type' => 'submit',
    '#value' => t('Clear all cached data'),
    '#submit' => array('cayl_dashboard_summary_form_clear')
  );
  $build['buttons']['cache'] = array(
    '#type' => 'submit',
    '#value' => t('Scan all nodes for links on the site to cache'),
    '#submit' => array('cayl_dashboard_summary_form_scan')
  );
  $build['buttons']['cache_now'] = array(
    '#type' => 'submit',
    '#value' => t('Cache all links which are scheduled to be cached'),
    '#submit' => array('cayl_dashboard_summary_form_cache')
  );

  return $build;
}

function cayl_dashboard_detail_form() {

  $build = array();
  $header = array(
    array('data' => t('Site')),
    array('data' => t('URL')),
    array('data' => t('Status'), 'field' => 'status'),
    array('data' => t('Last Checked'), 'field' => 'last_checked', 'sort' => 'desc'),
    array('data' => t('Cache Date'), 'field' => 'ca.date'),
    array('data' => t('Size'), 'field' => 'ca.size' ),
    array('data' => t('Last viewed'), 'field' => 'a.date' ),
    array('data' => t('Number of views'), 'field' => 'a.views' ),
    array('data' => t(''),  ),
    array('data' => t(''),  ),
  );

  $query = _cayl_dashboard_detail_query();
  $query = $query->extend('PagerDefault');
  $query = $query->extend('TableSort');
  $query = $query->limit(20);
  $query = $query->orderByHeader($header);
  $result = $query->execute();

  $rows = array();
  while ($r = $result->fetchAssoc()) {
    $host = parse_url($r['url'],PHP_URL_HOST);
    $rows[] = array(
      'site' => "$host (" . l('Blacklist', 'admin/reports/cayl/blacklist/' . $host, array('query' => array('destination' => current_path()))) . ")",
      'url' => $r['url'],
      'status' => $r['status'] ? t('Up') : t('Down'),
      'last_checked' => format_date($r['last_checked']),
      'date' => format_date($r['date']),
      'size' => format_size($r['size']),
      'a.date' => isset($r['a_date']) ? format_date($r['a_date']) : "",
      'views' => isset($r['views']) ? $r['views'] : 0,
      'view' => l('View', $r['location']),
      'delete' => l('Delete', 'admin/reports/cayl/delete/' . $r['id'], array('query' => array('destination' => current_path()))),
    );
  }

  // add the content
  $build['cayl_detail_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );
  // Add the pager
  $build['pager'] = array(
    '#theme' => 'pager',
    '#weight' => 5,
  );

  $build['buttons']['export'] = array(
    '#type' => 'submit',
    '#value' => t('Export as CSV'),
    '#submit' => array('cayl_dashboard_detail_form_export')
  );

  return $build;
}

function _cayl_cache_size() {
  $result = db_query("SELECT COUNT(*) FROM cayl_cache")->fetchCol();
  return $result[0];
}

function _cayl_queue_size($q) {
  $queue = DrupalQueue::get($q);
  return ($queue) ? $queue->numberOfItems() : 0;
}

function _cayl_last_run($var) {
  $time = variable_get($var,0);
  return ($time) ? format_date($time) : t('Never');
}

function _cayl_disk_usage() {
  $status = cayl_get_status();
  $max = format_size(variable_get('cayl_max_disk', '1000') * 1024 * 1024);
  $current = format_size($status->get_cache_size());
  return t('@current (of @max)', array('@current' => $current, '@max' => $max));
}

function cayl_dashboard_summary_form_clear($form, &$form_state) {
  cayl_clear_cache();
  drupal_set_message("All cached data has been deleted");
}

function cayl_dashboard_summary_form_scan($form, &$form_state) {
  $scan_batch = cayl_batch_scan_all_setup();
  if ($scan_batch) {
    batch_set($scan_batch);
  }
}

function cayl_dashboard_summary_form_cache($form, &$form_state) {
  $cache_batch = cayl_batch_cache_all_setup();
  if ($cache_batch) {
    batch_set($cache_batch);
  }
}

/**
 * Export contents of the dashboard detail page as CSV
 * @param $form
 * @param $form_state
 */
function cayl_dashboard_detail_form_export($form, &$form_state) {
  $query = _cayl_dashboard_detail_query();
  $result = $query->execute();

  $header = array(
    t('Site'),
    t('URL'),
    t('Status'),
    t('Last Checked'),
    t('Cache Date'),
    t('Size'),
    t('Last viewed'),
    t('Number of views')
  );

  $rows = array();
  while ($r = $result->fetchAssoc()) {
    $host = parse_url($r['url'],PHP_URL_HOST);
    $rows[] = array(
      'site' => $host,
      'url' => $r['url'],
      'status' => $r['status'] ? t('Up') : t('Down'),
      'last_checked' => date('c',$r['last_checked']),
      'date' => date('c',$r['date']),
      'size' => format_size($r['size']),
      'a.date' => isset($r['a_date']) ? date('c',$r['a_date']) : "",
      'views' => isset($r['views']) ? $r['views'] : 0,
    );
  }

  drupal_add_http_header('Content-Type', 'text/csv');
  drupal_add_http_header('Content-Disposition', 'attachment;filename=report.csv');

  $fp = fopen('php://output', 'w');
  fputcsv($fp, $header);
  foreach($rows as $line){
    fputcsv($fp, $line);
  }
  fclose($fp);
  drupal_exit();
}

/**
 * Get the base query for returning data for the dashboard detail page
 */
function _cayl_dashboard_detail_query() {
  $query = db_select('cayl_check', 'c');
  $query->join('cayl_cache', 'ca','ca.id = c.id');
  $query->leftJoin('cayl_activity', 'a','ca.id = a.id');
  $query = $query->fields('c', array('id', 'url','status','last_checked'));
  $query = $query->fields('ca', array('id', 'date', 'size', 'location'));
  $query = $query->fields('a', array('id', 'views', 'date'));
  return $query;
}

/**
 * Callback function to delete single item from the cache
 * @param $id
 */
function cayl_dashboard_delete_item($id) {
  if ($id) {
    cayl_delete_item($id);
    drupal_set_message("Deleted record with id $id");
  }
  drupal_goto(drupal_get_destination());
}

/**
 * Callback function to add a site to the list of sites not to cache
 * @param $id
 */
function cayl_dashboard_blacklist_site($site) {
  if ($site) {
    $list = variable_get('cayl_excluded_sites','');
    $list .= PHP_EOL . filter_xss($site);
    variable_set('cayl_excluded_sites',$list);
    drupal_set_message("Added $site to list of excluded sites");
  }
  drupal_goto(drupal_get_destination());
}