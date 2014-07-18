<?php

require_once 'AmberChecker.php';
require_once 'AmberFetcher.php';
require_once 'AmberStorage.php';
require_once 'AmberStatus.php';

$db = "/var/lib/amber/amber.db";
$cache_location = "/usr/local/nginx/html/amber/cache";
date_default_timezone_set('UTC');

function main($argc, $argv) {
  global $db, $cache_location;
  $options = getopt("",array("action::", "db::", "cache::", "url::", "help"));
  if (isset($options["db"])) {
    $db = $options["db"];
  }
  if (isset($options["cache"])) {
    $cache_location = $options["cache"];
  }
  if (isset($options["help"])) {
    usage();
    return;
  }
  if (!isset($options["action"])) {
    $options["action"] = "dequeue";
  }
  switch ($options["action"]) {
    case false:
    case "dequeue":
      dequeue();
      break;
    case "check":
      schedule_checks();
      break;
    case "cache":
      if ($options["url"]) {
        cache($options["url"]);
      } else {
        print "Error: Provide URL to cache";
      }
      break;
    case "help":
    default:
      usage();
      break;
  }
}

function usage() {
  print "Usage: $argc [--action=dequeue|cache|check|help] [--db=path_to_database] [--cache=path_to_cache] [--url=url_to_cache]\n";
}

/* Download a single URL and save it to the cache */
function cache($url) {
  $fetcher = get_fetcher();
  $status = get_status();
  $checker = get_checker();
  $last_check = $status->get_check($url);
  if (($update = $checker->check(empty($last_check) ? array('url' => $url) : $last_check, true)) !== false) {
    $status->save_check($update);
    try {
      $cache_metadata = $fetcher->fetch($url);
    } catch (RuntimeException $re) {
      error_log(sprintf("Did not cache (%s): %s", $url, $re->getMessage()));
      $update['message'] = $re->getMessage();
      $status->save_check($update);        
      return;
    }
    if ($cache_metadata) {
      $status->save_cache($cache_metadata);
    }
  }
}

/* Pull an item off the "queue", and save it to the cache.
   Note that if this is run in parallel, it's possible that the same item could be processed multiple times
   To run until the queue is empty use the shell command: while php AmberRunner.php dequeue; do true ; done
*/
function dequeue() {
  $db_connection = get_database();
  $result = $db_connection->query('SELECT c.url FROM amber_queue c WHERE c.lock is NULL ORDER BY created ASC LIMIT 1');
  $row = $result->fetch();
  $result->closeCursor();
  if ($row and $row['url']) {
    $update_query = $db_connection->prepare('UPDATE amber_queue SET lock = :time WHERE url = :url');
    $update_query->execute(array('url' => $row['url'], 'time' => time()));
    print "Caching " . $row['url'] . "\n";
    cache($row['url']);
    $update_query = $db_connection->prepare('DELETE from amber_queue where url = :url');
    $update_query->execute(array('url' => $row['url']));
    // TODO: Need to determine behavior on failure
    exit(0);
  } else {
    print "No more items to cache\n";
    exit(1);
  }
}

/* Find all items that are due to be checked, and put them on the queue for checking */
function schedule_checks() {
  $db_connection = get_database();
  $status_service = get_status();
  $urls = $status_service->get_urls_to_check();
  foreach ($urls as $url) {
    $insert_query = $db_connection->prepare('INSERT OR IGNORE INTO amber_queue (url, created) VALUES(:url, :created)');
    $insert_query->execute(array('url' => $url, 'created' => time()));
  }
  print "Scheduled " . count($urls) . " urls for checking\n";
}

function get_database() {
  global $db;
  try {
    $db_connection = new PDO('sqlite:' . $db);
  } catch (PDOException $e) {
    print "Error: Cannot open database: " . $e->getMessage();
    exit(1);
  }
  return $db_connection;
}

function get_storage() {
  global $cache_location;
  return new AmberStorage($cache_location);
}

function get_fetcher() {
  return new AmberFetcher(get_storage(), array(
    'amber_max_file' => 1000,
    'header_text' => "This is a cached page",
  ));
}

function get_checker() {
  return new AmberChecker();
}

function get_status() {
  global $db;
  try {
    $db_connection = new PDO('sqlite:' . $db);
  } catch (PDOException $e) {
    print "Error: Cannot open database: " . $e->getMessage();
    return null;
  }
  return new AmberStatus($db_connection);
}

main($argc,$argv);


?>