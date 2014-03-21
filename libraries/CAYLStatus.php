<?php
/**
 * Created by PhpStorm.
 * User: jlicht
 * Date: 3/20/14
 * Time: 4:06 PM
 */

interface iCAYLStatus {
  function get_check($url, $source = 'cayl');
  function get_cache($url, $source = 'cayl');
  function get_summary($url);
  function save_check(array $data);
  function save_cache(array $data);
  function get_urls_to_check();
  function clear_all();
}

class CAYLStatus implements iCAYLStatus {

  public function __construct(PDO $db) {
    $this->db = $db;
  }

  /**
   * Get information for a URL about it's most recent check
   * @param $url string to lookup
   * @param $source string with the name of the source of the check (e.g. 'cayl', 'herdict')
   * @return array|mixed
   */
  public function get_check($url, $source = 'cayl') {
    return $this->get_item($url, 'cayl_check');
  }

  public function get_cache($url, $source = 'cayl') {
    return $this->get_item($url, 'cayl_cache');
  }

  private function get_item($url, $table) {
    $query = $this->db->prepare("SELECT * FROM $table WHERE url = :url");
    $query->execute(array('url' => $url));
    $result = ($query->rowCount() == 1) ? $query->fetch(PDO::FETCH_ASSOC) : array();
    $query->closeCursor();
    return $result;
  }

  public function get_summary($url) {
    $query = $this->db->prepare(
      ' SELECT ca.location, ca.date, ch.status ' .
      ' FROM cayl_cache ca, cayl_check ch ' .
      ' WHERE ca.url = :url AND ca.id = ch.id');
    $query->execute(array('url' => $url));
    $result = ($query->rowCount() == 1) ? $query->fetch(PDO::FETCH_ASSOC) : array();
    $query->closeCursor();
    return $result;
  }

  /**
   * Save status information to the database
   * @param array $data
   * @return false on failure
   */
  public function save_check(array $data) {

    foreach (array('last_checked', 'next_check', 'status', 'url') as $key) {
      if (!array_key_exists($key,$data)) {
        error_log(join(":", array(__FILE__, __METHOD__, "Missing required key when updating status check", $key)));
        return false;
      }
    }

    if (!isset($data['id'])) {
      $data['id'] = md5($data['url']);
      //TODO: Remove duplication of this with CAYLStorage
    }
    $count_query = $this->db->prepare("SELECT COUNT(id) FROM cayl_check WHERE id = :id");
    $count_query->execute(array('id' => $data['id']));
    $result = $count_query->fetchColumn();

    if ($result) {
      $updateQuery = $this->db->prepare('UPDATE cayl_check ' .
                                        'SET last_checked = :last_checked, ' .
                                        'next_check = :next_check, ' .
                                        'status = :status, ' .
                                        'url = :url ' .
                                        'WHERE id = :id');
    } else {
      $updateQuery = $this->db->prepare('INSERT into cayl_check ' .
                                        '(id, url, status, last_checked, next_check) ' .
                                        'VALUES(:id, :url, :status, :last_checked, :next_check)');
    }
    $updateQuery->execute($data);
    $updateQuery->closeCursor();
    return true;
  }

  /**
   * Save metadata about a cache entry to the database
   * @param array $data
   * @return false on failure
   */
  public function save_cache(array $data) {
    foreach (array('url', 'location', 'date', 'type', 'size') as $key) {
      if (!array_key_exists($key,$data)) {
        error_log(join(":", array(__FILE__, __METHOD__, "Missing required key when updating cache", $key)));
        return false;
      }
    }

    $count_query = $this->db->prepare("SELECT COUNT(id) FROM cayl_cache WHERE id = :id");
    $count_query->execute(array('id' => $data['id']));
    $result = $count_query->fetchColumn();

    if ($result) {
      $updateQuery = $this->db->prepare('UPDATE cayl_cache ' .
                                        'SET url = :url, ' .
                                        'location = :location ' .
                                        'date = :date ' .
                                        'type = :type ' .
                                        'size = :size' .
                                        'WHERE id = :id');
    } else {
      $updateQuery = $this->db->prepare('INSERT into cayl_cache ' .
                                        '(id, url, location, date, type, size) ' .
                                        'VALUES(:id, :url, :location, :date, :type, :size)');
    }
    $updateQuery->execute($data);
    $updateQuery->closeCursor();
    return true;
  }

  /**
   * Get a list of URLs which are overdue for checking.
   */
  public function get_urls_to_check() {
    $result = array();
    $query = $this->db->prepare('SELECT url FROM cayl_check WHERE next_check < :time ORDER BY next_check ASC');
    if ($query->execute(array('time' => time()))) {
      $result = $query->fetchAll(PDO::FETCH_COLUMN, 0);
      $query->closeCursor();
    } else {
      error_log(join(":", array(__FILE__, __METHOD__, "Error retrieving URLs to check from database")));
    }
    return $result;
  }

  public function clear_all() {
    $this->db->prepare("TRUNCATE cayl_cache")->execute();
    $this->db->prepare("TRUNCATE cayl_check")->execute();
//    $this->db->prepare("TRUNCATE cayl_activity")->execute();
  }

} 