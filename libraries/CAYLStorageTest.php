<?php
/**
 * Created by PhpStorm.
 * User: jlicht
 * Date: 3/10/14
 * Time: 2:18 PM
 */

require_once("CAYLStorage.php");

class CAYLStorageTest extends PHPUnit_Framework_TestCase {

  var $storagepath;

  public function setUp() {
    date_default_timezone_set('UTC');
  }

  public function tearDown() {
    $this->rrmdir($this->storagepath);
  }

  public function provider() {
    $this->storagepath = join(PATH_SEPARATOR,array(realpath(sys_get_temp_dir()),"cayl"));
    if (!file_exists($this->storagepath))
      mkdir($this->storagepath,0777);

    $storage = new CAYLStorage($this->storagepath);
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

  /**
   * @dataProvider provider
   */
  public function testRetrieve(iCAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $metadata = $storage->lookup_url("www.example.com");
    $this->assertFalse(empty($metadata['id']));
    $data = $storage->get($metadata['id']);
    $this->assertSame($data,"I am a temporary file");
  }

  /**
   * @dataProvider provider
   */
  public function testBogusRetrieve(iCAYLStorage $storage, $file) {
    $data = $storage->get("xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx");
    $this->assertNull($data);
  }

  /**
   * Recursively delete all files in a directory
   * Credit: http://stackoverflow.com/a/3338133
   * @param $dir
   */
  private function rrmdir($dir) {
     if (is_dir($dir)) {
       $objects = scandir($dir);
       foreach ($objects as $object) {
         if ($object != "." && $object != "..") {
           if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
         }
       }
       reset($objects);
       rmdir($dir);
     }
   }
}
 