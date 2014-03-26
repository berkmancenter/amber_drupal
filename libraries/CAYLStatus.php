<?php
/**
 * Created by PhpStorm.
 * User: jlicht
 * Date: 3/20/14
 * Time: 4:06 PM
 */

interface iCAYLStatus {
  public function get_check($url, $source = 'cayl');
  public function get_cache($url, $source = 'cayl');
  public function get_summary($url);
  public function get_cache_size();
  public function save_check(array $data);
  public function save_cache(array $data);
  public function get_urls_to_check();
  public function save_view($id);
  public function get_items_to_purge($max_size);
  public function delete_all();
  public function delete($id);
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
    //TODO: Add additional keys for checks through proxies for specific countries
    return array('default' => $result);
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
    foreach (array('id', 'url', 'location', 'date', 'type', 'size') as $key) {
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
                                        'location = :location, ' .
                                        'date = :date, ' .
                                        'type = :type, ' .
                                        'size = :size ' .
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

  public function save_view($id) {
    $count_query = $this->db->prepare("SELECT COUNT(id) FROM cayl_activity WHERE id = :id");
    $count_query->execute(array('id' => $id));
    $result = $count_query->fetchColumn();

    if ($result) {
      $updateQuery = $this->db->prepare('UPDATE cayl_activity ' .
                                        'SET views = views + 1, ' .
                                        'date = :date ' .
                                        'WHERE id = :id');
    } else {
      $updateQuery = $this->db->prepare('INSERT into cayl_activity ' .
                                        '(id, views, date) ' .
                                        'VALUES(:id, 1, :date)');
    }
    $updateQuery->execute(array('id' => $id, 'date' => time()));
    $updateQuery->closeCursor();
  }

  /**
   * Get total disk space usage of the cache
   * @return string
   */
  public function get_cache_size() {
    $query = $this->db->prepare('SELECT sum(size) FROM cayl_cache');
    $query->execute();
    $result = $query->fetchColumn();
    $query->closeCursor();
    return $result;
  }

  /**
   * Identify the cached items that must be deleted to keep the total disk usage below the desired maximum
   * @param $max_size
   */
  public function get_items_to_purge($max_disk) {
    $result = array();
    $current_size = $this->get_cache_size();
    if ($current_size > $max_disk) {
      $query = $this->db->prepare(
        'SELECT cc.id, cc.url, size FROM cayl_cache cc ' .
        'LEFT JOIN cayl_activity ca ON cc.id = ca.id ' .
        'ORDER BY greatest(IFNULL(ca.date,0),cc.date) ASC');
      $query->execute();
      $size_needed = $current_size - $max_disk;
      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $size_needed = $size_needed - $row['size'];
        $result[] = array('id' => $row['id'], 'url' => $row['url']);
        if ($size_needed < 0) {
          break;
        }
      }
    }
    return $result;
  }


  public function delete_all() {
    $this->db->prepare("TRUNCATE cayl_cache")->execute();
    $this->db->prepare("TRUNCATE cayl_check")->execute();
    $this->db->prepare("TRUNCATE cayl_activity")->execute();
  }

  public function delete($id) {
    foreach (array('cayl_cache', 'cayl_check', 'cayl_activity') as $table) {
      $this->db->prepare("DELETE FROM $table WHERE id = :id")->execute(array('id' => $id));
    }
  }


} 