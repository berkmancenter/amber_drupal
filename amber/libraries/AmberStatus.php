<?php

require_once 'AmberDB.php';

interface iAmberStatus {
  public function get_check($url, $source = 'amber');
  public function get_cache($url, $source = 'amber');
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

class AmberStatus implements iAmberStatus {

  public function __construct(iAmberDB $db) {
    $this->db = $db;
  }

  /**
   * Get information for a URL about it's most recent check
   * @param $url string to lookup
   * @param $source string with the name of the source of the check (e.g. 'amber', 'herdict')
   * @return array|mixed
   */
  public function get_check($url, $source = 'amber') {
    return $this->get_item($url, 'amber_check');
  }

  public function get_cache($url, $source = 'amber') {
    return $this->get_item($url, 'amber_cache');
  }

  private function get_item($url, $table) {
    // $query = $this->db->prepare("SELECT * FROM $table WHERE url = ?");
    // $query->execute(array($url));
    // $result = $query->fetch(PDO::FETCH_ASSOC);
    // $query->closeCursor();
    $result = $this->db->select("SELECT * FROM $table WHERE url = ?", array($url));
    return $result;
  }

  public function get_summary($url) {
    // $query = $this->db->prepare(
    //   ' SELECT ca.location, ca.date, ch.status, ca.size ' .
    //   ' FROM amber_cache ca, amber_check ch ' .
    //   ' WHERE ca.url = ? AND ca.id = ch.id');
    // $query->execute(array($url));
    // $result = ($query->rowCount() == 1) ? $query->fetch(PDO::FETCH_ASSOC) : array();
    // $query->closeCursor();
    $result = $this->db->select(' SELECT ca.location, ca.date, ch.status, ca.size ' .
                                ' FROM amber_cache ca, amber_check ch ' .
                                ' WHERE ca.url = ? AND ca.id = ch.id', 
                                array($url));
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
    if (!isset($data['message'])) {
      $data['message'] = "";
    }

    if (!isset($data['id'])) {
      $data['id'] = md5($data['url']);
      //TODO: Remove duplication of this with AmberStorage
    }
    // $count_query = $this->db->prepare("SELECT COUNT(id) FROM amber_check WHERE id = :id");
    // $count_query->execute(array('id' => $data['id']));
    // $result = $count_query->fetchColumn();
    $result = $this->db->select("SELECT COUNT(id) as count FROM amber_check WHERE id = ?", array($data['id']));
    $params = array($data['url'], $data['status'], $data['last_checked'], $data['next_check'], 
                    $data['message'], $data['id']);
    if ($result['count']) {
      $updateQuery = 'UPDATE amber_check ' .
                     'SET ' .
                     'url = ?, ' .
                     'status = ?, ' .
                     'last_checked = ?, ' .
                     'next_check = ?, ' .
                     'message = ? ' .
                     'WHERE id = ?';
      $this->db->update($updateQuery, $params);
    } else {
      $updateQuery = 'INSERT into amber_check ' .
                     '(url, status, last_checked, next_check, message, id) ' .
                     'VALUES(?, ?, ?, ?, ?, ?)';
      $this->db->insert($updateQuery, $params);
    }
    // $updateQuery->execute($params);
    // $updateQuery->closeCursor();
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
    $result = $this->db->select("SELECT COUNT(id) as count FROM amber_cache WHERE id = ?", array($data['id']));
    $params = array($data['url'], $data['location'], $data['date'], $data['type'], 
                    $data['size'], $data['id']);
    // $count_query->execute(array($data['id']));
    // $result = $count_query->fetchColumn();
    if ($result['count']) {
      $updateQuery = 'UPDATE amber_cache ' .
                                        'SET ' .
                                        'url = ?, ' .
                                        'location = ?, ' .
                                        'date = ?, ' .
                                        'type = ?, ' .
                                        'size = ? ' .
                                        'WHERE id = ?';
      $this->db->update($updateQuery, $params);
    } else {
      $updateQuery = 'INSERT into amber_cache ' .
                                        '(url, location, date, type, size, id) ' .
                                        'VALUES(?, ?, ?, ?, ?, ?)';
      $this->db->insert($updateQuery, $params);
    }
    // $updateQuery->execute($params);
    // $updateQuery->closeCursor();

    return true;
  }

  /**
   * Get a list of URLs which are overdue for checking.
   */
  public function get_urls_to_check() {
    $result = array();
    // $query = $this->db->prepare('SELECT url FROM amber_check WHERE next_check < ? ORDER BY next_check ASC');
    // if ($query->execute(array(time()))) {
    //   $result = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    //   $query->closeCursor();
    $rows = $this->db->selectAll('SELECT url FROM amber_check WHERE next_check < ? ORDER BY next_check ASC', 
                                    array(time()));
    if ($result === FALSE) {
      error_log(join(":", array(__FILE__, __METHOD__, "Error retrieving URLs to check from database")));
      return array();
    } else {
      foreach ($rows as $row) {
        $result[] = $row['url'];
      }
    }
    return $result;
  }

  public function save_view($id) {
    // $count_query = $this->db->prepare("SELECT COUNT(id) FROM amber_activity WHERE id = ?");
    // $count_query->execute(array($id));
    // $result = $count_query->fetchColumn();
    $result = $this->db->select("SELECT COUNT(id) as count FROM amber_activity WHERE id = ?", array($id));
    $params = array(time(), $id);
    if ($result['count']) {
      $updateQuery = 'UPDATE amber_activity ' .
                                        'SET views = views + 1, ' .
                                        'date = ? ' .
                                        'WHERE id = ?';
      $this->db->update($updateQuery, $params);
    } else {
      $updateQuery = 'INSERT into amber_activity ' .
                                        '(date, views, id) ' .
                                        'VALUES(?, 1, ?)';
      $this->db->insert($updateQuery, $params);
    }
    // $updateQuery->execute(array(time(), $id));
    // $updateQuery->closeCursor();
  }

  /**
   * Get total disk space usage of the cache
   * @return string
   */
  public function get_cache_size() {
    $result = $this->db->select('SELECT sum(size) as sum FROM amber_cache');
    // $query->execute();
    // $result = $query->fetchColumn();
    // $query->closeCursor();
    return $result['sum'];
  }

  /**
   * Identify the cached items that must be deleted to keep the total disk usage below the desired maximum
   * @param $max_size
   */
  public function get_items_to_purge($max_disk) {
    $result = array();
    $current_size = $this->get_cache_size();
    if ($current_size > $max_disk) {
      
      /* Sqlite and Mysql have different names for a function we need */
      if ($this->db->original_db()->getAttribute(PDO::ATTR_DRIVER_NAME) == "sqlite")
        $max_function = "max";
      else
        $max_function = "greatest";

      // $query = $this->db->original_db()->prepare(
      //   "SELECT cc.id, cc.url, size FROM amber_cache cc " .
      //   "LEFT JOIN amber_activity ca ON cc.id = ca.id " .
      //   "ORDER BY ${max_function}(IFNULL(ca.date,0),cc.date) ASC");
      // $query->execute();
      $rows = $this->db->selectAll("SELECT cc.id, cc.url, size FROM amber_cache cc " .
                                   "LEFT JOIN amber_activity ca ON cc.id = ca.id " .
                                   "ORDER BY ${max_function}(IFNULL(ca.date,0),cc.date) ASC");
      $size_needed = $current_size - $max_disk;
      foreach ($rows as $row) {
        $size_needed = $size_needed - $row['size'];
        $result[] = array('id' => $row['id'], 'url' => $row['url']);
        if ($size_needed < 0) {
          break;
        }
      }
    }
    return $result;
  }

  /**
   * Delete all status information. Do NOT delete activity data.
   */
  public function delete_all() {
    // $this->db->prepare("DELETE FROM amber_cache")->execute();
    // $this->db->prepare("DELETE FROM amber_check")->execute();
    $this->db->delete("DELETE FROM amber_cache");
    $this->db->delete("DELETE FROM amber_check");
  }

  /**
   * Delete an item from the cache and check tables. Do NOT delete activity data.
   * @param $id
   */
  public function delete($id) {
    foreach (array('amber_cache', 'amber_check') as $table) {
      // $this->db->prepare("DELETE FROM $table WHERE id = ?")->execute(array($id));
      $this->db->delete("DELETE FROM $table WHERE id = ?", array($id));
    }
  }


} 