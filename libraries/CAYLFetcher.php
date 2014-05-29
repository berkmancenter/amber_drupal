<?php

//TODO: Namespace

interface iCAYLFetcher {
  public function fetch($url);
}

class CAYLFetcher implements iCAYLFetcher {

  /**
   * @param $storage CAYLStorage that will be used to save the item
   */
  function __construct(iCAYLStorage $storage, array $options) {
    $this->storage = $storage;
    $this->assetHelper = new CAYLAssetHelper();
    $this->maxFileSize = isset($options['cayl_max_file']) ? $options['cayl_max_file'] : 1000;
    $this->headerText = isset($options['header_text']) ? $options['header_text'] : "This is a cached page";
  }

  /**
   * Fetch the URL and associated assets and pass it on to the designated Storage service
   * @param $url
   * @return
   */
  //TODO: Find a cleaner way of dealing with temporary files in this function.
  public function fetch($url) {

    if (!$url) {
      throw new RuntimeException("Empty URL");
    }

    // Check the robots.txt
    if (!CAYLRobots::robots_allowed($url)) {
      throw new RuntimeException("Fetching ${url} blocked by robots.txt");
    }

    // Send a GET request
    $root_item = CAYLNetworkUtils::open_url($url);

    // Decide whether the item should be cached
    if (!$this->cacheable_item($root_item)) {
      throw new RuntimeException("Fetching ${url} halted due to file size or content-type");
    }

    $size = $root_item['info']['size_download'];

    // Get other assets
    if (isset($root_item['headers']['Content-Type']) &&
        ($content_type = $root_item['headers']['Content-Type']) &&
        (strpos(strtolower($content_type),"text/html") !== FALSE)) {

      $body = stream_get_contents($root_item['body']);
      $asset_paths = $this->assetHelper->extract_assets($body);
      $assets = $this->assetHelper->expand_asset_references($url, $asset_paths);
      $assets = $this->download_assets($assets, $url);

      $all_css_assets = array();
      foreach ($assets as &$value) {
        $size += $value['info']['size_download'];
        /* For CSS assets, parse the CSS file to find and download any referenced images, and rewrite the CSS file to use them */
        if (isset($value['headers']['Content-Type']) && (strpos($value['headers']['Content-Type'],'text/css') !== FALSE)) {
          $css_body = stream_get_contents($value['body']);
          $css_asset_paths = $this->assetHelper->extract_css_assets($css_body);
          $css_assets = $this->assetHelper->expand_asset_references($value['url'], $css_asset_paths);
          $css_assets = $this->download_assets($css_assets, $url);
          $all_css_assets = array_merge($all_css_assets, $css_assets);
          $css_body = $this->assetHelper->rewrite_links($css_body, $css_assets, $value['url']);
          $this->rewrite_file($value['body'], $css_body);
        }
      }
      $assets = array_merge($assets, $all_css_assets);
      $body = $this->assetHelper->rewrite_links($body, $assets);
      $body = $this->assetHelper->insert_banner($body, $this->headerText);
      $this->rewrite_file($root_item['body'],$body);

      /* Check total size of the file combined with its assets */
      if ($size > ($this->maxFileSize * 1024)) {
        throw new RuntimeException("Fetching ${url} halted due to file size of document + assets");
      }
    }

    if ($this->storage && $root_item) {
      rewind($root_item['body']);
      $this->storage->save($url, $root_item['body'], $root_item['headers'], isset($assets) ? $assets : array());
      fclose($root_item['body']);
      $storage_metadata = $this->storage->get_metadata($url);
      //TODO: If cannot retrieve storage metadata, or id/url/cache not populated (perhaps due to permissions errors
      //      in saving the cache), fail more gracefully instead of with errors because the keys are not set
      return array (
        'id' => $storage_metadata['id'],
        'url' => $storage_metadata['url'],
        'type' => isset($storage_metadata['type']) ? $storage_metadata['type'] : 'application/octet-stream',
        'date' => strtotime($storage_metadata['cache']['cayl']['date']),
        'location' => $storage_metadata['cache']['cayl']['location'],
        'size' => $size
      );
    } else {
      throw new RuntimeException("Saving ${url} halted due to empty content");
    }
  }

  /**
   * Change a file resource to have new contents. Current implementation creates a new temp file, and points the
   * resource at that
   * @param $resource resource to change contents of
   * @param $text new contents
   */
  private function rewrite_file(&$resource, $text) {
    $stream = fopen('php://temp','rw');
    fwrite($stream, $text);
    fclose($resource);
    $resource = $stream;
    rewind($resource);
  }

  private function cacheable_item($data) {

    if ($data['info']['size_download'] > ($this->maxFileSize * 1024)) {
      return false;
    }
    // TODO: Check for excluded content-type
    return TRUE;
  }

  /**
   * Download a list of assets (img,css,js) that are used by a page
   * @param $assets array of strings of relative paths of assets
   * @param $url string path to the page from which the assets are referenced
   * @return array where keys are asset paths, and values are body/header dictionaries returned from open_url, along with
   *         another key containing the absolute path to the asset
   */
  private function download_assets($assets, $url = '') {
    $result = array();
    foreach ($assets as $key => $asset) {
      $f = CAYLNetworkUtils::open_url($asset['url'], array(CURLOPT_REFERER => $url));
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
      $refs = array_merge($refs,$this->extract_dom_tag_attributes($dom, 'input', 'src'));
      $refs = array_merge($refs,$this->extract_dom_link_references($dom));
      $refs = array_merge($refs,$this->extract_dom_style_references($dom));
      $refs = array_merge($refs,$this->extract_css_assets($body));
      return $refs;
    } else {
      return array();
    }
  }

  public function extract_css_assets($body) {
    if ($body) {
      $re = '/url\(\s*["\']?([^\v()<>{}\[\]"\']+)[\'"]?\s*\)/';
      $count = preg_match_all($re, $body, $matches);
      return $count ? array_unique($matches[1]) : array();
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
      $server = $p['scheme'] . "://" . $p['host'] . (isset($p['port']) ? ":" . $p['port'] : '');
      $base = $server . join('/',$path_array);
      foreach ($assets as $asset) {
        $asset_copy = $asset;
        if (strpos($asset,"//") === 0) {
          /* Ensure that every URL has a scheme. Must be done before running parse_url due to bug in PHP < 5.4.7 */
          /* Workaround for bug in parse_url: http://us2.php.net/parse_url#refsect1-function.parse-url-changelog */
          $asset_copy = "${p['scheme']}:${asset_copy}";
        }
        $asset_url = parse_url($asset_copy);
        if ($asset_url) {
          if (!isset($asset_url['host'])) {
            $asset_copy = CAYLNetworkUtils::full_relative_path($asset_copy);
            if ($asset_copy && $asset_copy[0] == '/') {
              /* Absolute path */
              $asset_copy = preg_replace("/^\\//","", $asset_copy); /* Remove leading '/' */
              $asset_path = join('/',array($server, $asset_copy));
            } else {
              /* Relative path */
              $asset_path = join('/',array($base, $asset_copy));
            }
            $result[$asset]['url'] = $asset_path;
          } else {
            $result[$asset]['url'] = $asset_copy;
          }
        }
      }
    }
    return $result;
  }

  /**
   * Rewrite all asset links in an HTML document to point to relative paths underneath the asset directory
   * @param $body string HTML document
   * @param array $assets keyed by the url as it appears in the document, with the absolute URL as the value
   * @param $relative_to string the URL from which the asset's relative path is in relation to
   * @return mixed
   */
  public function rewrite_links($body, array $assets, $relative_to = '') {
    $result = $body;
    if ($body && !empty($assets)) {
      $base = "assets";
      if ($relative_to) {
        $relative_path = CAYLNetworkUtils::full_relative_path($relative_to);
        $base = str_repeat('../',substr_count($relative_path,'/')) . $base;
      }
      foreach ($assets as $key => $asset) {
        $url = CAYLNetworkUtils::full_relative_path($asset['url']);
        $p = $base . $url;
        $result = str_replace($key,$p,$result);
      }
    }
    $result = $this->rewrite_base_tag($result);
    return $result;
  }

  /** 
   * Rewrite the "<base href='foo'/>" tag in the header if it exists. This tag sets the base URL from which 
   * relative URLs are relative to, which gives us problems if it refers back to the original site.
   **/ 
  public function rewrite_base_tag($body) {
    $body = preg_replace('/<base\s+href=[\'"]\S+[\'"]\/?>/','',$body,1);
    return $body;
  }

/* <script type="text/javascript">window.onbeforeunload = function(e) { return "This page is trying to beat it"; }; window.onload = function() { window.onbeforeunload=null; }</script>  */
  public function insert_banner($body, $text) {
    $banner = <<<EOD
<div style="position:absolute;top:0;left:0;width:100%;height:30px;z-index:9999;background-color:rgba(0,0,0,0.75);color:white;text-align:center;font:bold 18px/30px sans-serif !important;">${text}</div>
EOD;
    $result = str_ireplace("</body>","${banner}</body>",$body,$count);
    if ($count == 0) {
      $result = $body . $banner;
    }
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
        $a = trim($t->getAttribute($attribute));
        /* Ignore data: URIs */
        if (strpos($a,'data:') !== 0) {
          $attributes[] = $a;
        }
      }
    }
    return $attributes;
  }

  private function extract_dom_link_references($dom) {
    $attributes = array();
    foreach ($dom->getElementsByTagName('link') as $t) {
      if ($t->hasAttribute('rel') && ($t->getAttribute('rel') == 'stylesheet')) {
        $attributes[] = trim($t->getAttribute('href'));
      }
    }
    return $attributes;
  }

}

class CAYLNetworkUtils {

  private static function curl_installed() {
    return in_array("curl", get_loaded_extensions());
  }

  public static function full_relative_path($url) {
    $dict = parse_url($url);
    $result = isset($dict['host']) ? "/" . $dict['host'] : '';
    $result .= isset($dict['path']) ? $dict['path'] : '';
    $result .= isset($dict['query']) ? '?' . $dict['query'] : '';
    return $result;
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
          CURLOPT_CONNECTTIMEOUT => 10,     /* 10 second connection timeout */
          CURLOPT_TIMEOUT => 30,            /* 30 second timeout for any CURL function */
          CURLOPT_RETURNTRANSFER => 1,      /* Return the output as a string */
          CURLOPT_HEADER => TRUE,           /* Return header information as part of the file */
          CURLOPT_FILE => $tmp_body_file,
          CURLOPT_WRITEHEADER => $tmp_header_file,
          CURLOPT_USERAGENT => "CAYL/0.1",
          // CURLOPT_VERBOSE => true,
          // CURLOPT_PROXY => 'localhost:8889',
          // CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
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
    $robots_url = $p['scheme'] . "://" . $p['host'] . (isset($p['port']) ? ":" . $p['port'] : '') . '/robots.txt';
    $data = CAYLNetworkUtils::open_url($robots_url, array(CURLOPT_FAILONERROR => FALSE));
    if (isset($item['info']['http_code']) && ($item['info']['http_code'] == 200)) {
      $body = stream_get_contents($data['body']);
      return (!$body || CAYLRobots::url_permitted($body, $url));
    } else {
      return true;
    }
  }

}