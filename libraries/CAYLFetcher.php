<?php

//TODO: Namespace

interface iCAYLFetcher {
  public function fetch($url);
}

class CAYLFetcher implements iCAYLFetcher {

  /**
   * @param $storage CAYLStorage that will be used to save the item
   */
  function __construct(iCAYLStorage $storage) {
    $this->storage = $storage;
    $this->assetHelper = new CAYLAssetHelper();
  }

  /**
   * Fetch the URL and associated assets and pass it on to the designated Storage service
   * @param $url
   * @return
   */
  public function fetch($url) {
    $existing_cache = $this->storage->get_metadata($url);
    if (!empty($existing_cache)) {
      // TODO: Check to see if we should refresh the cache
    }

    // Check the robots.txt
    if (!CAYLRobots::robots_allowed($url)) {
      return false;
    }

    // Send a GET request
    $root_item = CAYLNetworkUtils::open_url($url);

    // Decide whether the item should be cached
    if (!$this->cacheable_item($root_item)) {
      return false;
    }

    $size = $root_item['info']['size_download'];

    // Get other assets
    if (($content_type = $root_item['headers']['Content-Type']) &&
        (strpos(strtolower($content_type),"text/html") !== FALSE)) {

      $body = stream_get_contents($root_item['body']);
      $asset_paths = $this->assetHelper->extract_assets($body);
      $assets = $this->assetHelper->expand_asset_references($url, $asset_paths);
      $assets = $this->download_assets($assets);
      foreach ($assets as $key => $value) {
        $size += $value['info']['size_download'];
      }
      $body = $this->assetHelper->rewrite_links($body, $assets);
      $body = $this->assetHelper->insert_banner($body);
      $stream = fopen('php://temp','rw');
      fwrite($stream, $body);
      fclose($root_item['body']);
      $root_item['body'] = $stream;

      // TODO: Check total file size of all assets to see if below limit
    }

    if ($this->storage && $root_item) {
      rewind($root_item['body']);
      $this->storage->save($url, $root_item['body'], $root_item['headers'], isset($assets) ? $assets : array());
    }
    if ($root_item) {
      fclose($root_item['body']);
    }
    $storage_metadata = $this->storage->get_metadata($url);
    print $size;
    return array (
      'id' => $storage_metadata['id'],
      'url' => $storage_metadata['url'],
      'type' => $storage_metadata['type'],
      'date' => strtotime($storage_metadata['cache']['cayl']['date']),
      'location' => $storage_metadata['cache']['cayl']['location'],
      'size' => $size
    );
  }


  private function cacheable_item($data) {
    // TODO: Add logic to actually test if we should cache it, based on file size, content-type, etc.
    return TRUE;
  }

  /**
   * Download a list of assets (img,css,js) that are used by a page
   * @param $url string path to the page from which the assets are referenced
   * @param $assets array of strings of relative paths of assets
   * @return array where keys are asset paths, and values are body/header dictionaries returned from open_url, along with
   *         another key containing the absolute path to the asset
   */
  private function download_assets($assets) {
    $result = array();
    foreach ($assets as $key => $asset) {
      $f = CAYLNetworkUtils::open_url($asset['url']);
      if ($f) {
        $result[$key] = array_merge($f,$asset);
      }
    }
    return $result;
  }

}

class CAYLAssetHelper {

  /**
   * Extract a list of assets to be downloaded to go along with an HTML file
   * @param $file
   */
  public function extract_assets($body) {
    if ($body) {
      $dom = new DOMDocument;
      $old_setting = libxml_use_internal_errors(true);
      $dom->loadHTML($body);
      libxml_clear_errors();
      libxml_use_internal_errors($old_setting);

      $refs = $this->extract_dom_tag_attributes($dom, 'img', 'src');
      $refs = array_merge($refs,$this->extract_dom_tag_attributes($dom, 'script', 'src'));
      $refs = array_merge($refs,$this->extract_dom_link_references($dom));
      $refs = array_merge($refs,$this->extract_dom_style_references($dom));
      return $refs;
    } else {
      return array();
    }
  }
  /**
   * Given a base URL and a list of assets referenced from that page, return an array list of absolute URIs
   * to each of the assets keyed by the path used to reference it
   * @param $base
   * @param $assets
   */
  public function expand_asset_references($base, $assets) {
    $result = array();
    $p = parse_url($base);
    if ($p) {
      $path_array = explode('/',isset($p['path']) ? $p['path'] : "");
      array_pop($path_array);
      $base = $p['scheme'] . "://" . $p['host'] . (isset($p['port']) ? ":" . $p['port'] : '') . join('/',$path_array);
      foreach ($assets as $asset) {
        $asset_copy = $asset;
        if (version_compare(phpversion(), '5.4.7', '<') && (strpos($asset,"//") === 0)) {
          /* Workaround for bug in parse_url: http://us2.php.net/parse_url#refsect1-function.parse-url-changelog */
          $asset_copy = "${p['scheme']}:${asset_copy}";
        }
        $asset_url = parse_url($asset_copy);
        if ($asset_url) {
          if ((isset($asset_url['host']) && ($asset_url['host'] == $p['host'])) || !isset($asset_url['host'])) {
            $asset_copy = preg_replace("/^\\//","", $asset_url['path']); /* Remove leading '/' */
            $asset_path = join('/',array($base, $asset_copy));
            $result[$asset]['url'] = $asset_path;
          }
        }
      }
    }
    return $result;
  }

  public function rewrite_links($body, array $assets) {
    $result = $body;
    if ($body && !empty($assets)) {
      foreach ($assets as $key => $asset) {
        $p = "assets" . parse_url($asset['url'],PHP_URL_PATH);
        $result = str_replace($key,$p,$result);
      }
    }
    return $result;
  }

  public function insert_banner($body) {
    $banner = <<<EOD
<div style="position:absolute;top:0;left:0;width:100%;height:30px;z-index:999;background-color:rgba(0,0,0,0.5);;color:white;text-align:center;line-height:30px;">
This is a cached page</div>
EOD;
    //TODO: Translation
    $result = str_ireplace("</body>","${banner}</body>",$body);
    return $result;
  }

  /**
   * Extract references to external files that use an @import directive in  <style> tag
   * @param $dom
   * @return array
   */
  private function extract_dom_style_references($dom) {
    $attributes = array();
    foreach ($dom->getElementsByTagName('style') as $t) {
      if (preg_match("/@import\s*['\"](.*)['\"]/",$t->nodeValue,$matches)) {
        $attributes[] = $matches[1];
      }
    }
    return $attributes;
  }

  private function extract_dom_tag_attributes($dom, $tag, $attribute) {
    $attributes = array();
    foreach ($dom->getElementsByTagName($tag) as $t) {
      if ($t->hasAttribute($attribute)) {
        $attributes[] = $t->getAttribute($attribute);
      }
    }
    return $attributes;
  }

  private function extract_dom_link_references($dom) {
    $attributes = array();
    foreach ($dom->getElementsByTagName('link') as $t) {
      if ($t->hasAttribute('rel') && ($t->getAttribute('rel') == 'stylesheet')) {
        $attributes[] = $t->getAttribute('href');
      }
    }
    return $attributes;
  }

}

class CAYLNetworkUtils {

  private static function curl_installed() {
    return in_array("curl", get_loaded_extensions());
  }

  /**
   * Transform raw HTTP headers into a dictionary
   * @param $raw_headers string of headers from the HTTP response header
   * @return array
   */
  private static function extract_headers($raw_headers) {
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
  public static function open_url($url, $additional_options = array()) {
    if (CAYLNetworkUtils::curl_installed()) {
      if (($ch = curl_init($url)) === FALSE) {
        error_log(join(":", array(__FILE__, __METHOD__, $url, "CURL init error")));
        return FALSE;
      }

      $tmp_header_file_name = tempnam(sys_get_temp_dir(),'cayl');
      $tmp_body_file_name = tempnam(sys_get_temp_dir(),'cayl');
      $tmp_header_file = fopen($tmp_header_file_name ,"wr");
      $tmp_body_file = fopen($tmp_body_file_name ,"wr");

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
          CURLOPT_HEADER => TRUE,           /* Return header information as part of the file */
          CURLOPT_FILE => $tmp_body_file,
          CURLOPT_WRITEHEADER => $tmp_header_file,
        );

        if (curl_setopt_array($ch, $additional_options + $options) === FALSE) {
          throw new RuntimeException(join(":", array(__FILE__, __METHOD__, "Error setting CURL options", $url, curl_error($ch))));
        }

        if (($data = curl_exec($ch)) === FALSE) {
          //TODO: We probably don't want to clutter up the log every time we try to a access a file or asset that's unavailable. Handle better
          throw new RuntimeException(join(":", array(__FILE__, __METHOD__, "Error executing CURL request", $url, curl_error($ch))));
        }

        $response_info = curl_getinfo($ch);
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

      $headers = CAYLNetworkUtils::extract_headers(file_get_contents($tmp_header_file_name));

      // Create new file for the content without the http headers. It would be better to do this as part of saving the file
      $tmp_body_file_name_stripped = tempnam(sys_get_temp_dir(),'cayl');
      $tmp_body_file_stripped = fopen($tmp_body_file_name_stripped ,"w");
      $tmp_body_file = fopen($tmp_body_file_name,"r");
      fseek($tmp_body_file,$response_info['header_size']);
      while ($line = fgets($tmp_body_file)) {
        fputs($tmp_body_file_stripped,$line);
      }
      fclose($tmp_body_file_stripped);
      fclose($tmp_body_file);
      //TODO: Clean up all temp files

      $body = fopen($tmp_body_file_name_stripped,"r");
      return array("headers" => $headers, "body" => $body, "info" => $response_info);

    } else {
      // TODO: If curl is not installed, see if remote file opening is enabled, and fall back to that method
      error_log(join(":", array(__FILE__, __METHOD__, "CURL not installed")));
      return FALSE;
    }
  }

}

class CAYLRobots {

  /**
   * Is the URL allowed by the robots.txt file.
   * @param $robots
   * @param $url
   * @return bool
   */
  public static function url_permitted($robots, $url) {
    require_once("robotstxtparser.php");
    $parser = new robotstxtparser($robots);
    return !$parser->isDisallowed($url);
  }

  /**
   * Find out if the access to the given URL is permitted by the robots.txt
   * @param $url
   * @return bool
   */
  public static function robots_allowed($url) {
    $p = parse_url($url);
    $p['path'] = "robots.txt";
    $robots_url = $p['scheme'] . "://" . $p['host'] . ($p['port'] ? ":" . $p['port'] : '') . '/robots.txt';
    $data = CAYLNetworkUtils::open_url($robots_url, array(CURLOPT_FAILONERROR => FALSE));
    $body = stream_get_contents($data['body']);
    return (!$body || CAYLRobots::url_permitted($body, $url));
  }




}