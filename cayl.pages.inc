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
    '#value' => t('Cache all links on the site'),
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
  );

  $query = db_select('cayl_check', 'c');
  $query->join('cayl_cache', 'ca','ca.id = c.id');
  $query->leftJoin('cayl_activity', 'a','ca.id = a.id');
  $query = $query->fields('c', array('id', 'url','status','last_checked'));
  $query = $query->fields('ca', array('id', 'date', 'size'));
  $query = $query->fields('a', array('id', 'views', 'date'));
  $query = $query->extend('PagerDefault');
  $query = $query->extend('TableSort');
  $query = $query->limit(20);
  $query = $query->orderByHeader($header);
  $result = $query->execute();

  $rows = array();
  while ($r = $result->fetchAssoc()) {
    $rows[] = array(
      'site' => parse_url($r['url'],PHP_URL_HOST),
      'url' => $r['url'],
      'status' => $r['status'] ? t('Up') : t('Down'),
      'last_checked' => format_date($r['last_checked']),
      'date' => format_date($r['date']),
      'size' => format_size($r['size']),
      'a.date' => isset($r['a_date']) ? format_date($r['a_date']) : "",
      'views' => isset($r['views']) ? $r['views'] : 0,
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
  return $build;
}

function _cayl_cache_size() {
  return db_query("SELECT COUNT(*) FROM cayl_cache")->fetchCol()[0];
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
  $file_path = join(DIRECTORY_SEPARATOR, array(DRUPAL_ROOT, variable_get('cayl_storage_location', 'sites/default/files/cayl')));
  $max = format_size(variable_get('cayl_max_disk', '1000') * 1024 * 1024);
  $current = format_size(_cayl_directory_size($file_path));
  return t('@current (of @max)', array('@current' => $current, '@max' => $max));
}

function cayl_dashboard_summary_form_clear($form, &$form_state) {
  cayl_clear_cache();
  drupal_set_message("All cached data has been deleted");
}

function cayl_dashboard_summary_form_cache($form, &$form_state) {
  $count = cayl_cache_all();
  drupal_set_message(t("Queued links from @count nodes to be cached",array('@count' => $count)));
}

/**
 * Get the directory size
 * From : http://stackoverflow.com/a/18288029
 * @param directory $directory
 * @return integer
 */
function _cayl_directory_size($directory) {
  $size = 0;
  if (file_exists($directory)) {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file){
      if ($file->getType() != 'dir') {
        $size += $file->getSize();
      }
    }
  }
  return $size;
}

function cayl_dashboard_delete_item($id) {
  if ($id) {
    cayl_delete_item($id);
    drupal_set_message("Deleted record with id $id");
  }
  drupal_goto(drupal_get_destination());
}

