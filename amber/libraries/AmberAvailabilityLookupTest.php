<?php
require_once ("AmberNetworkUtils.php");
require_once ("AmberAvailabilityLookup.php");
class AmberNetClerkAvailabilityLookupTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
    date_default_timezone_set('UTC');
  }

  public function provider() {
    $lookup = new AmberNetClerkAvailabilityLookup(array());
    return array(array($lookup));
  }

  /**
   * @dataProvider provider
   */
  public function testParseBadResult(iAmberAvailabilityLookup $lookup) {
    $result = $lookup->parseResponse(FALSE);
    $this->assertEquals('{"data":[]}', $result);
  }

  /**
   * @dataProvider provider
   */
  public function testSingleURLResult(iAmberAvailabilityLookup $lookup) {
  	// Sample result from querying http://netclerk.dev.berkmancenter.org/laapi?country=BR&url=https://twitter.com
    $result = $lookup->parseResponse('{"data":[{"type":"statuses","id":"1210908","attributes":{"url":"https://twitter.com","country":"BR","code":200,"probability":1.0,"available":true,"created":"2015-09-07T00:00:00.000Z"}}]}');
    $this->assertEquals('{"data":[{"url":"https://twitter.com","status":"available"}]}', $result);
  }

  /**
   * @dataProvider provider
   */
  public function testMultipleURLResult(iAmberAvailabilityLookup $lookup) {
  	// Sample result from querying http://netclerk.dev.berkmancenter.org/laapi?country=BR&url[]=http://www.bbc.co.uk/news&url[]=https://twitter.com
    $result = $lookup->parseResponse('{"data":[{"type":"statuses","id":"1210908","attributes":{"url":"https://twitter.com","country":"BR","code":200,"probability":1.0,"available":true,"created":"2015-09-07T00:00:00.000Z"}},{"type":"statuses","id":"1211092","attributes":{"url":"http://www.bbc.co.uk/news","country":"BR","code":200,"probability":0.0,"available":false,"created":"2015-09-07T00:00:00.000Z"}}]}');
    $this->assertEquals('{"data":[{"url":"https://twitter.com","status":"available"},{"url":"http://www.bbc.co.uk/news","status":"unavailable"}]}', $result);
  }


}

