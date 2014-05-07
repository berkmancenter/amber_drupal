<?php

require_once 'CAYLChecker.php';
require_once 'CAYLFetcher.php';
require_once 'CAYLStorage.php';
require_once 'CAYLStatus.php';

$db = "/var/lib/cayl/cayl.db";
$cache_location = "/usr/local/nginx/html/cayl/cache";
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
  print "Usage: $argc [--action=dequeue|cache|help] [--db=path_to_database] [--cache=path_to_cache] [--url=url_to_cache]\n";
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
      return;
    }
    if ($cache_metadata) {
      $status->save_cache($cache_metadata);
    }
  }
}

/* Pull an item off the "queue", and save it to the cache.
   Note that if this is run in parallel, it's possible that the same item could be processed multiple times
   To run until the queue is empty use the shell command: while php CAYLRunner.php dequeue; do true ; done
*/
function dequeue() {
  global $db;
  try {
    $db_connection = new PDO('sqlite:' . $db);
  } catch (PDOException $e) {
    print "Error: Cannot open database: " . $e->getMessage();
    return null;
  }
  $result = $db_connection->query('SELECT c.url FROM cayl_queue c WHERE c.lock is NULL ORDER BY created ASC LIMIT 1');
  $row = $result->fetch();
  $result->closeCursor();
  if ($row and $row['url']) {
    $update_query = $db_connection->prepare('UPDATE cayl_queue SET lock = :time WHERE url = :url');
    $update_query->execute(array('url' => $row['url'], 'time' => time()));
    print "Caching " . $row['url'] . "\n";
    cache($row['url']);
    $update_query = $db_connection->prepare('DELETE from cayl_queue where url = :url');
    $update_query->execute(array('url' => $row['url']));
    // TODO: Need to determine behavior on failure
    exit(0);
  } else {
    print "No more items to cache\n";
    exit(1);
  }
}

function get_storage() {
  global $cache_location;
  return new CAYLStorage($cache_location);
}

function get_fetcher() {
  return new CAYLFetcher(get_storage(), array(
    'cayl_max_file' => 1000,
    'header_text' => "This is a cached page",
  ));
}

function get_checker() {
  return new CAYLChecker();
}

function get_status() {
  global $db;
  try {
    $db_connection = new PDO('sqlite:' . $db);
  } catch (PDOException $e) {
    print "Error: Cannot open database: " . $e->getMessage();
    return null;
  }
  return new CAYLStatus($db_connection);
}

main($argc,$argv);


?>