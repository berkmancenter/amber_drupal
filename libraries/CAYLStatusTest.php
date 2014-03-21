<?php
/**
 * Created by PhpStorm.
 * User: jlicht
 * Date: 3/21/14
 * Time: 10:16 AM
 */

require_once("CAYLStatus.php");

class CAYLStatusTest extends PHPUnit_Framework_TestCase {

  public function provider() {
  }

//  function testSaveStatusValidation() {
//    $status = new CAYLStatus(new PDO('mysql:dbname=bogus;host=127.0.0.1'));
//    $this->assertFalse($status->save_check(array('id' => 'bogus')));
//    $this->assertFalse($status->save_check(array('id' => 'bogus', 'url' => 'www.example.com', 'last_checked' => 3453)));
//  }

}
 