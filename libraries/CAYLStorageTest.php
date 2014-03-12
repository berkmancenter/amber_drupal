<?php
/**
 * Created by PhpStorm.
 * User: jlicht
 * Date: 3/10/14
 * Time: 2:18 PM
 */

require_once("CAYLStorage.php");

class CAYLStorageTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
    date_default_timezone_set('UTC');
  }

  protected function tearDown() {
    $storage = new CAYLStorage($this->get_storage_path());
    $storage->clear_cache();
  }

  public function provider() {
    $storage_path = $this->get_storage_path();
    if (!file_exists($storage_path))
      mkdir($storage_path,0777);

    $storage = new CAYLStorage($storage_path);
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
    $metadata = $storage->get_metadata("www.example.com");
    $this->assertTrue(isset($metadata['cache']['cayl']['date']));
    $this->assertTrue(isset($metadata['cache']['cayl']['location']));

  }

  /**
   * @dataProvider provider
   */
  public function testLookupBogusURL(ICAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $metadata = $storage->get_metadata("www.pancakes.com");
    $this->assertTrue(empty($metadata));
  }

  /**
   * @dataProvider provider
   */
  public function testSaveTwice(ICAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $metadata = $storage->get_metadata("www.example.com");
    rewind($file);
    $storage->save("www.example.com",$file);
    $metadata2 = $storage->get_metadata("www.example.com");
    $this->assertTrue($metadata2['cache']['cayl']['date'] >= $metadata['cache']['cayl']['date']);
    $this->assertTrue($metadata2['cache']['cayl']['location'] == $metadata['cache']['cayl']['location']);
  }

  /**
   * @dataProvider provider
   */
  public function testRetrieve(iCAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $metadata = $storage->get_metadata("www.example.com");
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
   * @dataProvider provider
   */
  public function testClearCache(iCAYLStorage $storage, $file) {
    $storage->save("www.example.com",$file);
    $storage->clear_cache();
    $metadata = $storage->get_metadata("www.example.com");
    $this->assertTrue(empty($metadata));
  }

  private function get_storage_path() {
    return join(DIRECTORY_SEPARATOR,array(realpath(sys_get_temp_dir()),"cayl"));
  }
}
 