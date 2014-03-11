<?php

/**
 * The metadata is of the form:
 *
 * {
 *  "id" : ID,
 *  "cache":
 *   {
 *     "cayl" : {
 *        "date" : "2014-02-11T10:22:46Z",
 *        "location" : "/CAYL_PREFIX/cache/ID",
 *      },
 *      [...additional cache sources go here...]
 *    }
 *  "status" :
 *   {
 *      "cayl" : {
 *        "default" : "up",
 *        "IR" : "down"
 *      }.
 *      [...additional link checker results go here...]
 *    }
 * }
 *
 * TODO: Resolve distinction between this (CAYLStorage) as a single implementation of the storage functionality,
 * and its role as managing references to all stored data. As designed, references to other copies of the stored
 * data are mixed in with the metadata for THIS copy of the stored data (on disk, locally).
 *
 *
 */

interface iCAYLStorage {
  function lookup_url($url);
  function save($url, $root, array $assets = array());
  function get($id);
}

class CAYLStorage implements iCAYLStorage {

  function __construct($file_root = '/private/tmp/cayl/cache') {
    $this->file_root = $file_root;
    $this->url_prefix = 'CAYL';
    $this->name = 'cayl'; // Used to identify the metadata that belongs to this implementation of iCAYLStorage
  }

  function lookup_url($url) {
    return $this->get_cache_metadata($this->url_hash($url));
  }

  /**
   * Save a file to the cache
   * @param $url string original location of the file that we're saving
   * @param $root resource the file to be saved
   * @param array $assets any additional assets that should be saved (e.g. CSS, javascript)
   * @return bool success or failure
   */
  function save($url, $root, array $assets = array()) {
    $id = $this->url_hash($url);
    $cache_metadata = $this->get_cache_metadata($id);
    $dir = join(DIRECTORY_SEPARATOR, array($this->file_root, $id));
    if (empty($cache_metadata)) {
      if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
          error_log(join(":", array(__FILE__, __METHOD__, "Could not create directory for saving file", $dir)));
          return false;
        }
      }
      $cache_metadata = array(
        'id' => $id,
        'cache' => array (
          $this->name => array()
        )
      );
    }
    $cache_metadata['cache'][$this->name]['date'] = date(DATE_ISO8601);
    $cache_metadata['cache'][$this->name]['location'] = join("/", array($this->url_prefix, 'cache',$id));

    // Save metadata
    $this->save_cache_metadata($id, $cache_metadata);

    // Save root file
    $root_file = fopen(join(DIRECTORY_SEPARATOR,array($dir,$id)),'w');
    if (!$root_file) {
      error_log(join(":", array(__FILE__, __METHOD__, "Could not save cache file", $dir)));
      return false;
    }
    while ($line = fgets($root)) {
      fputs($root_file,$line);
    }
    fclose($root_file);

    // Save asset files

    // Check files sizes against maximum files size permitted
    // Check for overall file size, and purge old files if necessary

    return true;
  }


  function get($id) {
    $result = NULL;
    if ($path = $this->get_cache_item_path($id)) {
      if (file_exists($path)) {
        $result = file_get_contents($path);
      }
    }
    return $result;
  }

  /**
   * Return an MD5 hash for a normalized form of the URL to be used as a cached document id
   * @param string $url to be hashed
   * @return string MD5 hash of the url
   */
  private function url_hash($url) {
    //TODO: Normalize URLs (consider: https://github.com/glenscott/url-normalizer)
    return md5($url);
  }

  /**
   * Validate that a path points to a file within our cache directory. Used to ensure that calls to this module
   * cannot retrieve arbitrary files from the file system.
   * If the file does not exist, return TRUE
   * @param $path string to be validated
   * @return bool
   */
  private function is_within_cache_directory($path) {
    if (!realpath($path)) {
      // File does not exist.
      return TRUE;
    }
    if (strpos(realpath($path),realpath($this->file_root)) !== 0) {
      /* File is outside root directory for cache files */
      error_log(join(":", array(__FILE__, __METHOD__, "Attempt to access file outside file root", realpath($path), realpath($this->file_root))));
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Get the path to the metadata for a cached item
   * @param $id string
   * @return string path to the file that contains the metadata
   */
  private function get_cache_item_metadata_path($id) {
    $path = join(DIRECTORY_SEPARATOR, array($this->file_root, $id, "${id}.json"));
    return ($this->is_within_cache_directory($path)) ? $path : NULL;
  }

  /**
   * Get the path to the root cached item
   * @param $id string
   * @return string path to the file that contains the root cached item
   */
  private function get_cache_item_path($id) {
    $path = join(DIRECTORY_SEPARATOR, array($this->file_root, $id, $id));
    return ($this->is_within_cache_directory($path)) ? $path : NULL;
  }

  /**
   * Get the metadata for a cached document as a dictionary
   * @param string $id cached document id
   * @return array metadata
   */
  private function get_cache_metadata($id) {
    $path = realpath($this->get_cache_item_metadata_path($id));
    if ($path === false) {
      /* File does not exist. Do not log an error, since there are many cases in which this is expected */
      return array();
    }
    if (!$this->is_within_cache_directory($path)) {
      /* File is outside root directory for cache files */
      return array();
    }
    try {
      $file = file_get_contents($path);
    } catch (Exception $e) {
      error_log(join(":", array(__FILE__, __METHOD__, "Could not read file", $path)));
      return array();
    }
    $result = json_decode($file,true);
    if (null == $result) {
      $result = array();
      error_log(join(":", array(__FILE__, __METHOD__, "Could not parse file", $path)));
    };
    return $result;
  }

  private function save_cache_metadata($id, $metadata) {
    $path = $this->get_cache_item_metadata_path($id);
    $file = fopen($path,'w');
    if (!$file) {
      error_log(join(":", array(__FILE__, __METHOD__, "Could not open metadata file for saving", $path)));
      return false;
    }
    // JSON_UNESCAPED_SLASHES is only defined if PHP >= 5.4
    if (fwrite($file,json_encode($metadata, defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0)) === FALSE) {
      error_log(join(":", array(__FILE__, __METHOD__, "Could not write metadata file", $path)));
      return false;
    }
    if (!fclose($file)) {
      error_log(join(":", array(__FILE__, __METHOD__, "Could not close metadata file", $path)));
      return false;
    }
    return true;
  }

}
