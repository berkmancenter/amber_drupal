<?php

class CAYLStorage {

  function get_metadata($url) {
    if ($url == "http://nodata.example.com") {
      return array();
    } else {
      return array(
        'cache' => array('cayl' =>
            array(
            'date' => '2014-02-11T10:22:46Z',
            'location' => 'CAYL/cache/0a137b375cc3881a70e186ce2172c8d1',
            ),
          ),
        'status' => array('cayl' =>
            array(
              'default' => 'up',
              'IR' => 'down',
            )
          )
      );
    }
  }
}
