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

  	private function convert_to_question_marks($sql) {
  		$sql = str_replace('%s', '?', $sql);
  		$sql = str_replace('%d', '?', $sql);
		return $sql;  		
  	}

  	private function execute($sql, $options) {
	    $query = $this->db->prepare($this->convert_to_question_marks($sql));
	    $query->execute($options);
	    return $query;
  	}

  	public function select($sql, $options = array()) {

  		$query = $this->execute($this->convert_to_question_marks($sql), $options);
  		if (!$query) {
  			return false;
  		}
  		$result = $query->fetch(PDO::FETCH_ASSOC);
  		$query->closeCursor();
  		return $result;
  	}

  	public function selectAll($sql, $options = array()) {

  		$query = $this->execute($this->convert_to_question_marks($sql), $options);
  		if (!$query) {
  			return false;
  		}
  		$result = $query->fetchAll(PDO::FETCH_ASSOC);
  		$query->closeCursor();
  		return $result;
  	}

  	public function insert($sql, $options = array()) {
      $query = $this->execute($this->convert_to_question_marks($sql), $options);
      $query->closeCursor();      
  	}
	
  	public function update($sql, $options = array()) {
  		$query = $this->execute($this->convert_to_question_marks($sql), $options);
      $query->closeCursor();      
  	}
	
  	public function delete($sql, $options = array()) {
      $query = $this->execute($this->convert_to_question_marks($sql), $options);
      $query->closeCursor();      
  	}	
}

Class AmberWPDB implements iAmberDB {

	public function __construct(wpdb $db) {
    	$this->db = $db;
  	}

  	public function select($sql, $options = array())
  	{
  		$query = $this->db->prepare($sql, $options);
  		return $this->db->get_row($query, ARRAY_A);
  	}

  	public function selectAll($sql, $options = array())
  	{
  		# code...
  	}

  	public function insert($sql, $options = array())
  	{
  		$query = $this->db->prepare($sql, $options);
  		$this->db->query($query,$options);
  	}

  	public function update($sql, $options = array())
  	{
  		$query = $this->db->prepare($sql, $options);
  		$this->db->query($query,$options);
  	}

  	public function delete($sql, $options = array())
  	{
  		$query = $this->db->prepare($sql, $options);
  		$this->db->query($query,$options);
  	}


}
?>