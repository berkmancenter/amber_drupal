<?php

require_once("CAYLFetcher.php");

//class CAYLFetcherTest extends \PHPUnit_Framework_TestCase {
//
//
//}

class CAYLRobotsTest extends \PHPUnit_Framework_TestCase {

  public function testRobotsParse()
  {
    $this->assertFalse(CAYLRobots::url_permitted(NULL,"www.google.com"));
    $this->assertTrue(CAYLRobots::url_permitted("Donuts","www.google.com"));
  }

}
