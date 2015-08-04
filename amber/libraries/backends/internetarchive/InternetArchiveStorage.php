<?php

class InternetArchiveStorage implements iAmberStorage {

  function __construct(array $options) {
  }


  function get($id) {
  	throw new Exception("Not implemented for InternetArchiveStorage");
  }

  function get_asset($id, $path) {
  	throw new Exception("Not implemented for InternetArchiveStorage");  	
  }

  function get_metadata($key) {
  	throw new Exception("Not implemented for InternetArchiveStorage");  	
  }
  
  function get_id($url) {
  	throw new Exception("Not implemented for InternetArchiveStorage");  	
  }
  
  function save($url, $root, array $headers = array(), array $assets = array()) {
  	throw new Exception("Not implemented for InternetArchiveStorage");  	
  }
  
  function delete_all() {
  	throw new Exception("Not implemented for InternetArchiveStorage");  	
  }

  function delete($cache_metadata) {
    throw new Exception("Not implemented for InternetArchiveStorage");    
  }

}
