<?php

namespace FKRediSearch;

use FKRediSearch\RedisRaw\PredisAdapter;

class Setup {

  /**
   * Create connection to the Redis Server.
   *
   * @param string $server
   * @param int    $port
   * @param null   $password
   * @param int    $database
   *
   * @return PredisAdapter
   */
  public static function connect( $server = '127.0.0.1', $port = 6379, $password = null, $database = 0 ) {
    // Connect to server
    $client = ( new PredisAdapter() )->connect( $server, $port, $database, $password );
    return $client;
  }
  
}