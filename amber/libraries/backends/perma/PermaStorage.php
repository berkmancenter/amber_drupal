<?php

class PermaStorage implements iAmberStorage {

  function __construct(array $options) {
    $this->apiKey = isset($options['perma_api_key']) ? $options['perma_api_key'] : "";
    $this->apiUrl = isset($options['perma_api_url']) ? $options['perma_api_url'] : "https://api.perma-stage.org";
  }


  function get($id) {
  	throw new Exception("Not implemented for PermaStorage");
  }

  function get_asset($id, $path) {
  	throw new Exception("Not implemented for PermaStorage");  	
  }

  function get_metadata($key) {
  	throw new Exception("Not implemented for PermaStorage");  	
  }
  
  function get_id($url) {
  	throw new Exception("Not implemented for PermaStorage");  	
  }
  
  function save($url, $root, array $headers = array(), array $assets = array()) {
  	throw new Exception("Not implemented for PermaStorage");  	
  }
  
  function delete_all() {
  	throw new Exception("Not implemented for PermaStorage");  	
  }

  function delete($cache_metadata) {
    if (!$this->apiKey) {
      throw new InvalidArgumentException("Missing required API key for accessing Perma");      
    }

    $api_endpoint = join("",array(
    	$this->apiUrl,
    	'/v1/archives/',
    	$cache_metadata['provider_id'],
    	'/?api_key=',
    	$this->apiKey));

    $curl_options = array(
      CURLOPT_CUSTOMREQUEST => "DELETE",
      CURLOPT_FOLLOWLOCATION => TRUE,
    );

    $perma_result = AmberNetworkUtils::open_single_url($api_endpoint, $curl_options);
	if (isset($perma_result['info']['http_code']) && ($perma_result['info']['http_code'] == 204)) {
		return TRUE;
	} else {
		return FALSE;
	}
  }

}
