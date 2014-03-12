<?php
/**
 * Created by PhpStorm.
 * User: jlicht
 * Date: 3/5/14
 * Time: 3:19 PM
 */

interface iCAYLFetcher {
  public function fetch($url);
}

class CAYLFetcher implements iCAYLFetcher {

  /**
   * @param $storage CAYLStorage that will be used to save the item
   */
  function __construct(iCAYLStorage $storage) {
    $this->storage = $storage;
  }

  /**
   * Fetch the URL and associated assets and pass it on to the designated Storage service
   * @param $url
   */
  public function fetch($url) {
    $existing_cache = $this->storage->lookup_url($url);
    if (!empty($existing_cache)) {
      // TODO: Check to see if we should refresh the cache
    }

    // Check the robots.txt
    if (!$this->robots_allowed($url)) {
      //TODO: Figure out how to return an error message
      return;
    }

    // Send a HEAD request
    // TODO: Evaluate whether this is actually worth doing

    // Send a GET request
    $root_item = $this->open_url($url,array(CURLOPT_HEADER => TRUE));

    // Decide whether the item should be cached
    if (!$this->cacheable_item($root_item)) {
      //TODO: Figure out how to return an error message
      return;
    }

    // Get other assets
    if (($content_type = $root_item['headers']['Content-Type']) &&
        (strpos(strtolower($content_type),"text/html") !== FALSE)) {

      // TODO: Download other assets that are hosted on the same server
      // TODO: Check total file size of all assets to see if below limit
      // TODO: Rewrite links in core file (if HTML)

    }

    if ($this->storage) {
      $this->storage->save($url,$root_item['body'],array());
    }

  }


  private function cacheable_item($data) {
    // TODO: Add logic to actually test if we should cache it, based on file size, content-type, etc.
    return TRUE;
  }

  /**
   * Find out if the access to the given URL is permitted by the robots.txt
   * @param $url
   * @return bool
   */
  private function robots_allowed($url) {
    $p = parse_url($url);
    $p['path'] = "robots.txt";
    $robots_url = $p['scheme'] . "://" . $p['host'] . ($p['port'] ? ":" . $p['port'] : '') . '/robots.txt';
    $data = $this->open_url($robots_url, array(CURLOPT_FAILONERROR => FALSE));
    return (!$data || CAYLRobots::url_permitted($data,$url));
  }

  private function curl_installed() {
    return in_array("curl", get_loaded_extensions());
  }

  private function extract_headers($raw_headers) {
    $headers = array();
      if ($raw_headers) {
      foreach (explode(PHP_EOL,$raw_headers) as $line) {
        $header = explode(":",$line);
        if (count($header) == 2) {
          $headers[$header[0]] = trim($header[1]);
        }
      }
    }
    return $headers;
  }

  /**
   * Open a URL, and return an array with dictionary of header information and a stream to the contents of the URL
   * @param $url string of resource to download
   * @return array dictionary of header information and a stream to the contents of the URL
   */
  private function open_url($url, $additional_options = array()) {
    if ($this->curl_installed()) {
      if (($ch = curl_init($url)) === FALSE) {
        error_log(join(":", array(__FILE__, __METHOD__, $url, "CURL init error")));
        return FALSE;
      }

      $tmp_header_file_name = tempnam(sys_get_temp_dir(),'cayl');
      $tmp_body_file_name = tempnam(sys_get_temp_dir(),'cayl');
      $tmp_header_file = fopen($tmp_header_file_name ,"wr");
      $tmp_body_file = fopen($tmp_body_file_name ,"wr");
      $header_size = 0;

      try {
        if (($tmp_header_file === FALSE) || ($tmp_body_file === FALSE)) {
          throw new RuntimeException(join(":", array(__FILE__, __METHOD__, "Error creating temporary files for CURL download")));
        }

        $options = array(
          CURLOPT_FAILONERROR => TRUE,      /* Don't ignore HTTP errors */
          CURLOPT_FOLLOWLOCATION => TRUE,   /* Follow redirects */
          CURLOPT_MAXREDIRS => 10,          /* No more than 10 redirects */
          CURLOPT_CONNECTTIMEOUT => 10,     /* 10 second timeout */
          CURLOPT_RETURNTRANSFER => 1,      /* Return the output as a string */
          CURLOPT_FILE => $tmp_body_file,
          CURLOPT_WRITEHEADER => $tmp_header_file,
        );

        if (curl_setopt_array($ch, $additional_options + $options) === FALSE) {
          throw new ErrorException(join(":", array(__FILE__, __METHOD__, "Error setting CURL options", $url, curl_error($ch))));
        }

        if (($data = curl_exec($ch)) === FALSE) {
          throw new ErrorException(join(":", array(__FILE__, __METHOD__, "Error executing CURL request", $url, curl_error($ch))));
        }

        $header_size = curl_getinfo($ch,CURLINFO_HEADER_SIZE);
        curl_close($ch);
        fclose($tmp_header_file);
        fclose($tmp_body_file);

      } catch (RuntimeException $e) {
        error_log($e->getMessage());
        curl_close($ch);
        fclose($tmp_header_file);
        fclose($tmp_body_file);
        return FALSE;
      }

      $headers = $this->extract_headers(file_get_contents($tmp_header_file_name));

      // Create new file for the content without the http headers. Better to do this as part of saving the file
      $tmp_body_file_name_stripped = tempnam(sys_get_temp_dir(),'cayl');
      $tmp_body_file_stripped = fopen($tmp_body_file_name_stripped ,"w");
      $tmp_body_file = fopen($tmp_body_file_name,"r");
      fseek($tmp_body_file,$header_size);
      while ($line = fgets($tmp_body_file)) {
        fputs($tmp_body_file_stripped,$line);
      }
      fclose($tmp_body_file_stripped);
      fclose($tmp_body_file);
      //TODO: Clean up all temp files

      $body = fopen($tmp_body_file_name_stripped,"r");
      return array("headers" => $headers, "body" => $body);

    } else {
      // TODO: If curl is not installed, see if remote file opening is enabled, and fall back to that method
      error_log(join(":", array(__FILE__, __METHOD__, "CURL not installed")));
      return FALSE;
    }
  }
}

class CAYLRobots {

  public static function url_permitted($robots, $url) {
    //TODO: Implement parsing and checking against the robots.txt file
    return true;
  }

}