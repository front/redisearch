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
  * Prepare items (posts) to be indexed.
  * @since    0.1.0
  * @param
  * @return object $this
  */
  public function add() {
    $index_meta = get_option( 'wp_redisearch_index_meta' );
    if ( empty( $index_meta ) ) {
      $index_meta['offset'] = 0;
    }
    $posts_per_page = apply_filters( 'wp_redisearch_posts_per_page', Settings::get( 'wp_redisearch_indexing_batches', 20 ) );

    $default_args = Settings::query_args();
    $default_args['posts_per_page'] = $posts_per_page;
    $default_args['offset'] = $index_meta['offset'];

    $args = apply_filters( 'wp_redisearch_posts_args', $default_args);

    /**
     * filter wp_redisearch_before_index_wp_query
     * Fires before wp_query. This is useful if you want for some reasons, manipulate WP_Query
     * 
     * @since 0.2.2
     * @param array $args             Array of arguments passed to WP_Query
     * @return array $args            Array of manipulated arguments
		 */
    $args = apply_filters( 'wp_redisearch_before_index_wp_query', $args );

    $query = new \WP_Query( $args );

    /**
     * filter wp_redisearch_after_index_wp_query
     * Fires after wp_query. This is useful if you want to manipulate results of WP_Query
     * 
     * @since 0.2.2
     * @param array $args            Array of arguments passed to WP_Query
     * @param object $query          Result object of WP_Query
		 */
    $query = apply_filters( 'wp_redisearch_after_index_wp_query', $query, $args );

    $index_meta['found_posts'] = $query->found_posts;

    if ( $index_meta['offset'] >= $index_meta['found_posts'] ) {
      $index_meta['offset'] = $index_meta['found_posts'];
    }
    
    if ( $query->have_posts() ) {
      $index_name = Settings::indexName();
      
      while ( $query->have_posts() ) {
        $query->the_post();
        $indexing_options = array();

        $title = get_the_title();
        $permalink = get_permalink();
        $content = wp_strip_all_tags( get_the_content(), true );
        $id = get_the_id();
        // Post language. This could be useful to do some stop word, stemming and etc.
        $indexing_options['language'] = apply_filters( 'wp_redisearch_index_language', 'english', $id );
        $indexing_options['fields'] = $this->prepare_post( get_the_id() );

        $this->addPosts($index_name, $id, $indexing_options);

        /**
         * Action wp_redisearch_after_post_indexed fires after post added to the index.
         * Since this action called from within post loop, all Wordpress functions for post are available in the calback.
         * Example:
         * To get post title, you can simply call 'get_the_title()' function
         * 
         * @since 0.2.0
         * @param array $client             Created redis client instance
         * @param array $index_name         Index name
         * @param array $indexing_options   Posts extra options like language and fields
         */
        do_action( 'wp_redisearch_after_post_indexed', $this->client, $index_name, $indexing_options );
      }
      $index_meta['offset'] = absint( $index_meta['offset'] + $posts_per_page );
      update_option( 'wp_redisearch_index_meta', $index_meta );
    }
    return $index_meta;
  }


  /**
  * Add to index or in other term, index items.
  * @since    0.1.0
  * @param integer $post_id
  * @param array $post
  * @param array $indexing_options
  * @return object $index
  */
  public function addPosts($index_name, $id, $indexing_options) {
    $command = array_merge( [$index_name, $id , 1, 'LANGUAGE', $indexing_options['language']] );

    $extra_params = isset( $indexing_options['extra_params'] ) ? $indexing_options['extra_params'] : array();
    $extra_params = apply_filters( 'wp_redisearch_index_extra_params', $extra_params );
    // If any extra options passed, merge it to $command
    if ( isset( $extra_params ) ) {
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
  public function deletePosts($index_name, $id) {
    $command = array( $index_name, $id , 'DD' );
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