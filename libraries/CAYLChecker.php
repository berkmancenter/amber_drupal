<?php

interface iCAYLChecker {
  public function get_urls_to_check();
  public function up($url);
  public function update_status($url);
}

class CAYLChecker implements iCAYLChecker {

  public function __construct(PDO $db, iCAYLStorage $storage) {
    $this->db = $db;
    $this->storage = $storage;
  }

  /**
   * Get a list of URLs which are due for checking.
   */
  public function get_urls_to_check() {
    $result = array();
    $query = $this->db->prepare('SELECT url FROM cayl_status WHERE next_check < :time ORDER BY next_check ASC');
    if ($query->execute(array('time' => time()))) {
      $result = $query->fetchAll(PDO::FETCH_COLUMN, 0);
      $query->closeCursor();
    } else {
      error_log(join(":", array(__FILE__, __METHOD__, "Error retrieving URLs to check from database")));
    }
    return $result;
  }

  /**
   * Check to see if a given URL is available (if it returns 200 status code)
   * @param $url
   */
  public function up($url) {

    $item = CAYLNetworkUtils::open_url($url,  array(CURLOPT_FAILONERROR => FALSE));
    if (isset($item['info']['http_code'])) {
      return ($item['info']['http_code'] == 200);
    } else {
      return false;
    }
  }

  /**
   * Check whether a URL is available, and update the status of the URL in the database
   *
   * @param $url
   * @return bool whether the cached copy of the URL should be updated
   */
  public function update_status($url) {

    // Get data for $url
    $query = $this->db->prepare('SELECT * FROM cayl_status WHERE url = :url');
    $query->execute(array('url' => $url));
    $result = ($query->rowCount() == 1) ? $query->fetch(PDO::FETCH_ASSOC) : array();
    $query->closeCursor();

    /* Make sure we're still scheduled to check the $url */
    $next_check_timestamp = isset($result['next_check']) ? $result['next_check'] : 0;
    if ($next_check_timestamp > time()) {
      return false;
    }

    /* Figure out for when we should schedule the next check */
    $date = new DateTime();
    if (!CAYLRobots::robots_allowed($url)) {
      $next = $date->add(new DateInterval("P6M"))->getTimestamp();
      $status = false;
      error_log(join(":", array(__FILE__, __METHOD__, "Blocked by robots.txt", $url)));
      //TODO: Log this in a user-accessible way (for the dashboard?)

    } else {
      $status = $this->up($url);
      $next = $this->next_check_date(isset($result['status']) ? $result['status'] : NULL,
                                     $result['last_checked'], $result['next_check'], $status);
    }

    $now = new DateTime();
    $updated = array(
            'id' => $result['id'],
            'last_checked' => $now->getTimestamp(),
            'next_check' => $next,
            'status' => isset($status) ? ($status ? 1 : 0) : NULL,
          );
    if (isset($result['id'])) {
      $updateQuery = $this->db->prepare('UPDATE cayl_status ' .
                                        'SET last_checked = :last_checked, ' .
                                        'next_check = :next_check, ' .
                                        'status = :status ' .
                                        'WHERE id = :id');
    } else {
      $updateQuery = $this->db->prepare('INSERT into cayl_status ' .
                                        '(id, url, status, last_checked, next_check) ' .
                                        'VALUES(:id, :url, :status, :last_checked, :next_check)');
      $updated['url'] = $url;
      $updated['id'] = $this->storage->get_id($url);
    }
    $updateQuery->execute($updated);
    $updateQuery->closeCursor();

    return $status;
  }

  /**
   * Get the unix timestamp for the date the url should next be checked, based on the new status and the previous
   * interval between checks
   * @param $status bool with the previous status for the URL from the database
   * @param $last_checked_timestamp integer with the timestamp of the previous check
   * @param $next_check_timestamp integer with the timestamp of the next scheduled check (which we're doing now)
   * @param $new_status bool with the current status of the URL
   * @return int with the unix timestamp of the date after which the url can be checked again
   */
  private function next_check_date($status, $last_checked_timestamp, $next_check_timestamp, $new_status) {
    $date = new DateTime();
    if (is_null($status) || ($new_status != (bool)($status))) {
      $next_timestamp = $date->add(new DateInterval("P1D"))->getTimestamp();
    } else {
      $last = new DateTime();
      $last->setTimestamp($last_checked_timestamp);
      $old_next = new DateTime();
      $old_next->setTimestamp($next_check_timestamp);
      $diff = $last->diff($old_next,true);
      if ($diff->days >= 30) {
        $next_timestamp = $date->add(new DateInterval("P30D"))->getTimestamp();
      } else {
        $next_timestamp = $date->add($diff)->add(new DateInterval("P1D"))->getTimestamp();
      }
    }
    return $next_timestamp;
  }

} 