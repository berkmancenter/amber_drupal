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

  /**
   * @dataProvider provider
   */
  public function testLookupURL(ICAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $metadata = $storage->lookup_url("www.example.com");
    $this->assertTrue(isset($metadata['cache']['cayl']['date']));
    $this->assertTrue(isset($metadata['cache']['cayl']['location']));
  }

  /**
   * @dataProvider provider
   */
  public function testLookupBogusURL(ICAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $metadata = $storage->lookup_url("www.pancakes.com");
    $this->assertTrue(empty($metadata));
  }

  /**
   * @dataProvider provider
   */
  public function testSaveTwice(ICAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $metadata = $storage->lookup_url("www.example.com");
    rewind($file);
    $storage->save("www.example.com",$file);
    $metadata2 = $storage->lookup_url("www.example.com");
    $this->assertTrue($metadata2['cache']['cayl']['date'] >= $metadata['cache']['cayl']['date']);
    $this->assertTrue($metadata2['cache']['cayl']['location'] == $metadata['cache']['cayl']['location']);
  }


}
 