<?php

namespace FKRediSearch\RediSearch;

use FKRediSearch\FKRediSearch;
use FKRediSearch\RedisRaw\PredisAdapter;

class Index {

	/**
	 * @param object $client
	 */
  public $client;

	/**
	 * @param object $index
	 */
  private $index;

  public function __construct( $client ) {
    $this->client = $client;
  }

  /**
  * Drop existing index.
  * @since    0.1.0
  * @param
  * @return
  */
  public function drop( $index_name = null ) {
    if ( isset( $index_name ) ) {
      return $this->client->rawCommand( 'FT.DROP', [ $index_name ] );
    }
  }

  /**
  * Create index in redis server.
  * @since    0.1.0
  * @param
  * @return
  */
  public function create( $index_name = null, $schema = array(), $stop_words = null ) {
    if ( !isset( $index_name ) ) {
      return;
    }

    if ( empty( $schema ) ) {
      return;
    }

    $index_schema = array( $index_name );

    /**
     * Stop words support.
     * If disabled (equal to zero), then we will add no stop words.
     * @since 0.1.0
     */
    if ( $stop_words == 0 ) {
      $index_schema = array_merge( $index_schema, array( 'STOPWORDS', 0 ) );
    } else if ( is_array( $stop_words ) && !empty( $stop_words ) ) {
      $stop_words = array_map( 'trim', $stop_words );
      $stop_words_count = count( $stop_words );
      if ( $stop_words_count != 0 ) {
        $index_schema = array_merge( $index_schema, array( 'STOPWORDS', $stop_words_count ), $stop_words );
      }
    }

    $index_schema = array_merge( $index_schema, array( 'SCHEMA' ), $schema );

    $this->index = $this->client->rawCommand('FT.CREATE', $index_schema);

    return $this;
  }

  /**
  * Add documents to the index.
  * @since    0.1.0
  * @param
  * @return object $this
  */
  public function add($index_name = null, $id = 0, $score = 1, $indexing_options = null) {
    if ( !isset( $index_name ) ) {
      return;
    }

    if ( $id == 0 ) {
      return;
    }

    $command = array_merge( array( $index_name, $id, $score, 'LANGUAGE', $indexing_options['language'] ) );

    $extra_params = isset( $indexing_options['extra_params'] ) ? $indexing_options['extra_params'] : array();

    // If any extra options passed, merge it to $command
    if ( isset( $extra_params ) && !empty( $extra_params ) ) {
      $command = array_merge( $command, $extra_params );
    }

    $command = array_merge( $command, array( 'FIELDS' ), $indexing_options['fields'] );

    $index = $this->client->rawCommand('FT.ADD', $command);
    return $index;
  }

  /**
  * Delete post from index.
  * @since    0.1.0
  * @param
  * @return object $this
  */
  public function delete($index_name = null, $id = 0) {
    if ( !isset( $index_name ) ) {
      return;
    }

    if ( $id == 0 ) {
      return;
    }

    $command = array( $index_name, $id, 'DD' );
    $this->client->rawCommand('FT.DEL', $command);
    return $this;
  }

  /**
  * Write entire redisearch index to the disk to persist it.
  * @since    0.1.0
  * @param
  * @return
  */
  public function writeToDisk() {
    return $this->client->rawCommand('SAVE', []);
  }

}