<?php

require_once("CAYLFetcher.php");

//class CAYLFetcherTest extends \PHPUnit_Framework_TestCase {
//
//
//
//}

class CAYLRobotsTest extends \PHPUnit_Framework_TestCase {

  public function testRobotsParse()
  {
    $this->assertTrue(CAYLRobots::url_permitted(NULL,"www.google.com"));
    $this->assertTrue(CAYLRobots::url_permitted("Donuts","www.google.com"));
  }

}

class CAYLAssetHelperTest extends \PHPUnit_Framework_TestCase {

  public function provider() {
    return array(array(new CAYLAssetHelper()));
  }

  /**
   * @dataProvider provider
   */
  public function testNullParse(CAYLAssetHelper $a)
  {
    $result = $a->extract_assets("");
    $this->assertTrue(empty($result));
  }

  /**
   * @dataProvider provider
   */
  public function testBogusHTMLParse(CAYLAssetHelper $a)
  {
    $result = $a->extract_assets("<SDFSD>SDFfalsdhf>la<sasdfasdfasdf<DFSFd");
    $this->assertTrue(empty($result));
  }

  /**
   * @dataProvider provider
   */
  public function testOneImage(CAYLAssetHelper $a)
  {
    $s = <<<EOF
<body><img src="../peacock.png">And the band played on....</body>
EOF;

    $result = $a->extract_assets($s);
    $this->assertTrue(count($result) == 1);
    $this->assertEquals($result[0],"../peacock.png");
  }

  /**
   * @dataProvider provider
   */
  public function testTwoImages(CAYLAssetHelper $a)
  {
    $s = <<<EOF
<body><img src="../peacock.png">And the band played on....And the <img src="http://band.com/band.jpg"/> said to the
<a href="leader.html">leader</a>.</body>
EOF;

    $result = $a->extract_assets($s);
    $this->assertEquals(count($result),2);
    $this->assertEquals($result[0],"../peacock.png");
    $this->assertEquals($result[1],"http://band.com/band.jpg");
  }

  /**
   * @dataProvider provider
   */
  public function testStylesheet(CAYLAssetHelper $a)
  {
    $s = <<<EOF
<head><link href="banana.css" rel="stylesheet" type="text.css"></head>
<body>And the band played on....And the BAND said to the
<a href="leader.html">leader</a>.</body>
EOF;

    $result = $a->extract_assets($s);
    $this->assertTrue(count($result) == 1);
    $this->assertTrue($result[0] == "banana.css");
  }

  /**
   * @dataProvider provider
   */
  public function testJavascript(CAYLAssetHelper $a)
  {
    $s = <<<EOF
<head><script src="banana.js" ></head>
<body>And the band played on....And the BAND said to the
<a href="leader.html">leader</a>.</body>
EOF;

    $result = $a->extract_assets($s);
    $this->assertTrue(count($result) == 1);
    $this->assertTrue($result[0] == "banana.js");
  }

  /**
   * @dataProvider provider
   */
  public function testMix(CAYLAssetHelper $a)
  {
    $s = <<<EOF
<head><link href="banana.css" rel="stylesheet" type="text.css"><script src="banana.js" type="text/javascript"></head><body><img src="../peacock.png">And the band played on....And the <img src="http://band.com/band.jpg"/> said to the
<a href="leader.html">leader</a>.</body>
EOF;

    $result = $a->extract_assets($s);
    $this->assertEquals(count($result),4);
    sort($result);
    $this->assertTrue($result[0] == "../peacock.png");
    $this->assertTrue($result[1] == "banana.css");
    $this->assertTrue($result[2] == "banana.js");
    $this->assertEquals($result[3],"http://band.com/band.jpg");

  }

  /**
   * @dataProvider provider
   */
  public function testExpandReferencesSimple(CAYLAssetHelper $a)
  {
    $url = "http://example.com";
    $assets = array("banana.jpg", 'scripts/ban.js');
    $result = $a->expand_asset_references($url,$assets);
    $this->assertEquals($result['banana.jpg']['url'],'http://example.com/banana.jpg');
    $this->assertEquals($result['scripts/ban.js']['url'],'http://example.com/scripts/ban.js');
  }

  /**
   * @dataProvider provider
   */
  public function testExpandReferencesMix(CAYLAssetHelper $a)
  {
    $url = "http://example.com";
    $assets = array("banana.jpg", 'scripts/ban.js', 'http://example.com/example.jpg', 'http://othersite.org/frank/james.css', '//example.com/funky.jpg', '/abs.css');
    $result = $a->expand_asset_references($url,$assets);
    $this->assertEquals(count($result),5);
    $this->assertEquals($result['banana.jpg']['url'],'http://example.com/banana.jpg');
    $this->assertEquals($result['scripts/ban.js']['url'],'http://example.com/scripts/ban.js');
    $this->assertEquals($result['http://example.com/example.jpg']['url'],'http://example.com/example.jpg');
    $this->assertEquals($result['//example.com/funky.jpg']['url'],'http://example.com/funky.jpg');
    $this->assertEquals($result['/abs.css']['url'],'http://example.com/abs.css');
  }

  /**
   * @dataProvider provider
   */
  public function testWatermarkBanner(CAYLAssetHelper $a)
  {
    $s = <<<EOF
<html><head><script src="banana.js" ></head>
<body>And the band played on....And the BAND said to the
<a href="leader.html">leader</a>.</body></html>
EOF;
    $expected_result = <<<EOF
<html><head><script src="banana.js" ></head>
<body>And the band played on....And the BAND said to the
<a href="leader.html">leader</a>.<div style="position:absolute;top:0;left:0;width:100%;height:30px;z-index:999;background-color:rgba(0,0,0,0.5);;color:white;text-align:center;line-height:30px;">
This is a cached page</div></body></html>
EOF;
    $result = $a->insert_banner($s);
    $this->assertEquals($result,$expected_result);
  }


}
