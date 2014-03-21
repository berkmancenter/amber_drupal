<?php

interface iCAYLChecker {
  public function up($url);
  public function check($last_check);
}

class CAYLChecker implements iCAYLChecker {

  public function __construct(iCAYLStatus $status) {
    $this->status_service = $status;
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
   * @param
   * @return bool updated
   */
  public function check($last_check) {
    $url = $last_check['url'];

    /* Make sure we're still scheduled to check the $url */
    $next_check_timestamp = isset($last_check['next_check']) ? $last_check['next_check'] : 0;
    if ($next_check_timestamp > time()) {
      return false;
    }

    $date = new DateTime();
    if (!CAYLRobots::robots_allowed($url)) {
      /* If blocked by robots.txt, schedule next check for 6 months out */
      $next = $date->add(new DateInterval("P6M"))->getTimestamp();
      $status = isset($last_check['status']) ? $last_check['status'] : NULL;
      error_log(join(":", array(__FILE__, __METHOD__, "Blocked by robots.txt", $url)));
      //TODO: Log this in a user-accessible way (for the dashboard?)

    } else {
      $status = $this->up($url);
      $next = $this->next_check_date(isset($last_check['status']) ? $last_check['status'] : NULL,
                                     $last_check['last_checked'], $last_check['next_check'], $status);
    }

    $now = new DateTime();
    $result = array(
            'id' => $last_check['id'],
            'url' => $last_check['url'],
            'last_checked' => $now->getTimestamp(),
            'next_check' => $next,
            'status' => isset($status) ? ($status ? 1 : 0) : NULL,
          );

    return $result;
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