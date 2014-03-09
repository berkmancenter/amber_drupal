<?php

/**
 * The metadata is of the form:
 *
 * {
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
 *
 *
 *
 */

interface iCAYLStorage {
  function lookup_url($url);
}

class CAYLStorage implements iCAYLStorage {

  function __construct($file_root = '/private/tmp/cayl/cache') {
    $this->file_root = $file_root;
  }

  function lookup_url($url) {
    return $this->get_cache_metadata($this->url_hash($url));
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
   * Get the metadata for a cached document as a dictionary
   * @param string $id cached document id
   * @return array metadata
   */
  private function get_cache_metadata($id) {
    $path = realpath(join(DIRECTORY_SEPARATOR, array($this->file_root, $id, "${id}.json")));
    if ($path === false) {
      /* File does not exist. Do not log an error, since there are many cases in which this is expected */
//      error_log(join(":", array(__FILE__, __METHOD__, "Cache metadata file inaccessible or does not exist", $id)));
      return array();
    }
    if (strpos($path,$this->file_root) !== 0) {
      /* File is outside root directory for cache files */
      error_log(join(":", array(__FILE__, __METHOD__, "Attempt to access file outside file root", $path)));
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

}
