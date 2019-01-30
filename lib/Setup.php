<?php

namespace FKRediSearch\RediSearch;

use FKRediSearch\RedisRaw\PredisAdapter;

class Setup {
  /**
  * Create connection to redis server.
  * @since    0.1.0
  * @param
  * @return
  */
  public static function connect( $server = '127.0.0.1', $port = 6379, $password = null, $database = 0 ) {
    // Connect to server
    $client = ( new PredisAdapter() )->connect( $server, $port, $database, $password );
    return $client;
  }
  
}