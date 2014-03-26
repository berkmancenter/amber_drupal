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

  /**
   * @dataProvider provider
   */
  public function testSaveNoAssets(iCAYLStorage $storage, $file) {
    $storage->save("www.example.com", $file, array(), array());
    $metadata = $storage->get_metadata("www.example.com");
    $this->assertTrue(isset($metadata['cache']['cayl']['location']) && $metadata['cache']['cayl']['location']);
    $path = join(DIRECTORY_SEPARATOR,array($this->get_storage_path(),$metadata['id']));
    $this->assertTrue(file_exists($this->get_storage_path()));
    $this->assertTrue(file_exists($path));
  }

  /**
   * @dataProvider provider
   */
  public function testSaveOneAsset(iCAYLStorage $storage, $file) {
    $file = tmpfile();
    fwrite($file,"I am a temporary file");
    rewind($file);
    $assets = array(array('url' => 'http://www.example.com/man/is/free.jpg', 'body' => $file));

    $storage->save("www.example.com", $file, array(), $assets);
    $metadata = $storage->get_metadata("www.example.com");
    $this->assertTrue(isset($metadata['cache']['cayl']['location']) && $metadata['cache']['cayl']['location']);
    $path = join(DIRECTORY_SEPARATOR,array($this->get_storage_path(),$metadata['id']));
    $this->assertTrue(file_exists($path));
    $this->assertTrue(file_exists(join(DIRECTORY_SEPARATOR,array($path,'assets','man','is','free.jpg'))));

  }

  /**
   * @dataProvider provider
   */
  public function testSaveOneAssetQueryStringName(iCAYLStorage $storage, $file) {
    $file = tmpfile();
    fwrite($file,"I am a temporary file");
    rewind($file);
    $assets = array(array('url' => 'http://www.example.com/man/is/free/?t=js&amp;bv=&amp;os=&amp;tz=&amp;lg=&amp;rv=&amp;rsv=&amp;pw=%2F&amp;cb=1438832272', 'body' => $file));

    $storage->save("www.example.com", $file, array(), $assets);
    $metadata = $storage->get_metadata("www.example.com");
    $this->assertTrue(isset($metadata['cache']['cayl']['location']) && $metadata['cache']['cayl']['location']);
    $path = join(DIRECTORY_SEPARATOR,array($this->get_storage_path(),$metadata['id']));
    $this->assertTrue(file_exists($path));
    $this->assertTrue(file_exists(join(DIRECTORY_SEPARATOR,array($path,'assets','man','is','free','?t=js&amp;bv=&amp;os=&amp;tz=&amp;lg=&amp;rv=&amp;rsv=&amp;pw=%2F&amp;cb=1438832272'))));

  }


  /**
   * @dataProvider provider
   */
  public function testSaveOneAssetQueryStringNameTwo(iCAYLStorage $storage, $file) {
    $file = tmpfile();
    fwrite($file,"I am a temporary file");
    rewind($file);
    $assets = array(array('url' => 'http://www.example.com/traffic/?t=px&bv=JavaScript+Disabled&os=&tz=default&lg=&rv=&rsv=&pw=%2F&cb=1382655937', 'body' => $file));

    $storage->save("www.example.com", $file, array(), $assets);
    $metadata = $storage->get_metadata("www.example.com");
    $this->assertTrue(isset($metadata['cache']['cayl']['location']) && $metadata['cache']['cayl']['location']);
    $path = join(DIRECTORY_SEPARATOR,array($this->get_storage_path(),$metadata['id']));
    $this->assertTrue(file_exists($path));
    $this->assertTrue(file_exists(join(DIRECTORY_SEPARATOR,array($path,'assets','traffic','?t=px&bv=JavaScript+Disabled&os=&tz=default&lg=&rv=&rsv=&pw=%2F&cb=1382655937'))));

  }



  private function get_storage_path() {
    return join(DIRECTORY_SEPARATOR,array(realpath(sys_get_temp_dir()),"cayl"));
  }
}
 