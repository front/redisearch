<?php

namespace FKRediSearch\RediSearch;

class Search {

	/**
	 * @param object $client
	 */
  public $client;

  public function __construct( $client ) {
    $this->client = $client;
  }

  /**
  * Search in the index.
  * @since    0.1.0
  * @param object $query
  * @return
  */
  public function search( $index_name = null, $query = array(), $from = 0, $offset = 10 ) {
    if ( !isset( $index_name ) ) {
      return;
    }

    $search_results = $this->client->rawCommand('FT.SEARCH', [$index_name, $query, 'LIMIT', $from, $offset]);
    return $search_results;
  }
}