<?php

require_once dirname( __FILE__ ) . '/../../AmberInterfaces.php';
require_once dirname( __FILE__ ) . '/../../AmberNetworkUtils.php';

class InternetArchiveFetcher implements iAmberFetcher {

  function __construct(iAmberStorage $storage, array $options) {
    $this->archive_Url = "http://web.archive.org/";
  }

  /**
   * Fetch the URL and associated assets and pass it on to the designated Storage service
   * @param $url
   * @return
   */
	public function fetch($url) {
    if (!$url) {
      throw new RuntimeException("Empty URL");
    }

    $api_endpoint = join("",array(
      $this->archive_Url,
      "save/",
      $url));

    $curl_options = array(
      // CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_VERBOSE => TRUE,
    );

    dpm($api_endpoint);
    $ia_result = AmberNetworkUtils::open_single_url($api_endpoint, $curl_options);
    dpm($ia_result);
    /* Make sure that we got a valid response from the Archive */
    if (($ia_result === FALSE) || ($ia_result['body'] === FALSE)) {      
      throw new RuntimeException(join(":",array("Error submitting URL to the Internet Archive")));
    }
    if (!isset($ia_result['headers']['Content-Location'])) {
      throw new RuntimeException("Internet Archive response did not include archive location");  
    }
    $location = $ia_result['headers']['Content-Location'];
    $result = array (
        'id' => md5($url),
        'url' => $url,
        'type' => '',
        'date' => time(),
        'location' => join("/", array($this->archiveUrl, $location)),
        'size' => 0,
        'provider' => 2, /* Internet Archive */
        'provider_id' => $location,
      );
    return $result;
	}

}