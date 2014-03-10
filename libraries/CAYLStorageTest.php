<?php
/**
 * Created by PhpStorm.
 * User: jlicht
 * Date: 3/10/14
 * Time: 2:18 PM
 */

require_once("CAYLStorage.php");

class CAYLStorageTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    date_default_timezone_set('UTC');
  }

  public function provider() {
    $storage = new CAYLStorage(realpath(sys_get_temp_dir()));
    $file = tmpfile();
    fwrite($file,"I am a temporary file");
    rewind($file);
    return array(array($storage, $file));
  }

  /*
   * @dataProvider provider
   */
  public function testLookupURL($storage, $file) {

    $storage->save("www.example.com",$file);
    $metadata = $storage->lookup_url("www.example.com");
    $this->assertTrue(isset($metadata['cache']['cayl']['date']));
    $this->assertTrue(isset($metadata['cache']['cayl']['location']));

  }

}
 