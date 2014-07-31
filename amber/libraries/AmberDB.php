 <?php

/* Allow Amber PHP libraries to use the database connection provided
   by the platform on which they are running (e.g. Wordpress, Drupal)
 */
interface iAmberDB {
 
  public function select($sql, $options);
  public function selectAll($sql, $options);
  public function insert($sql, $options);
  public function update($sql, $options);
  public function delete($sql, $options);


}

Class AmberPDO implements iAmberDB {

  public function __construct(PDO $db) {
      $this->db = $db;
    }

    public function original_db() {
      return $this->db;
    }

    private function execute($sql, $options) {
      $query = $this->db->prepare($sql);
      // TODO: Replace %d/%s/%something with '?'
      $query->execute($options);
      return $query;
    }

    public function select($sql, $options = array()) {

      $query = $this->execute($sql, $options);
      if (!$query) {
        return false;
      }
      $result = $query->fetch(PDO::FETCH_ASSOC);
      $query->closeCursor();
      return $result;
    }

    public function selectAll($sql, $options = array()) {

      $query = $this->execute($sql, $options);
      if (!$query) {
        return false;
      }
      $result = $query->fetchAll(PDO::FETCH_ASSOC);
      $query->closeCursor();
      return $result;
    }

    public function insert($sql, $options = array()) {
      $query = $this->execute($sql, $options);
      $query->closeCursor();      
    }
  
    public function update($sql, $options = array()) {
      $query = $this->execute($sql, $options);
      $query->closeCursor();      
    }
  
    public function delete($sql, $options = array()) {
      $query = $this->execute($sql, $options);
      $query->closeCursor();      
    }
  
}


?>
