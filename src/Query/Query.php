<?php

namespace FKRediSearch\Query;

use InvalidArgumentException;

class Query {
  protected $return = '';
  protected $summarize = '';
  protected $highlight = '';
  protected $expander = '';
  protected $payload = '';
  protected $limit = '';
  protected $slop = null;
  protected $verbatim = '';
  protected $withScores = '';
  protected $withPayloads = '';
  protected $noStopWords = '';
  protected $noContent = '';
  protected $inFields = '';
  protected $inKeys = '';
  protected $numericFilters = [];
  protected $geoFilters = [];
  protected $sortBy = '';
  protected $scorer = '';
  protected $language = '';
  protected $client;
  private $indexName;

  public function __construct( $client, $indexName ) {
    $this->client = $client;
    $this->indexName = $indexName;
  }

  public function noContent() {
    $this->noContent = 'NOCONTENT';
    return $this;
  }

  public function return($fields) {
    $count = empty( $fields ) ? 0 : count( $fields );
    $field = implode(' ', $fields);
    $this->return = "RETURN $count $field";
    return $this;
  }

  public function summarize( $fields, $fragmentCount = 3, $fragmentLength = 50, $separator = '...' ) {
    $count = empty($fields) ? 0 : count($fields);
    $field = implode(' ', $fields);
    $this->summarize = "SUMMARIZE FIELDS $count $field FRAGS $fragmentCount LEN $fragmentLength SEPARATOR $separator";
    return $this;
  }

  public function highlight( $fields, $openTag = '<strong>', $closeTag = '</strong>' ) {
    $count = empty($fields) ? 0 : count($fields);
    $field = implode(' ', $fields);
    $this->highlight = "HIGHLIGHT FIELDS $count $field TAGS $openTag $closeTag";
    return $this;
  }

  public function expander( $expander ) {
    $this->expander = "EXPANDER $expander";
    return $this;
  }

  public function payload( $payload) {
    $this->payload = "PAYLOAD $payload";
    return $this;
  }

  public function limit( $offset, $pageSize = 10 ) {
    $this->limit = "LIMIT $offset $pageSize";
    return $this;
  }

  public function inFields( $number, $fields ) {
    $this->inFields = "INFIELDS $number " . implode(' ', $fields);
    return $this;
  }

  public function inKeys( $number, $keys ) {
    $this->inKeys = "INKEYS $number " . implode(' ', $keys);
    return $this;
  }

  public function slop( $slop ) {
    $this->slop = "SLOP $slop";
    return $this;
  }

  public function noStopWords() {
    $this->noStopWords = 'NOSTOPWORDS';
    return $this;
  }

  public function withPayloads() {
    $this->withPayloads = 'WITHPAYLOADS';
    return $this;
  }

  public function withScores() {
    $this->withScores = 'WITHSCORES';
    return $this;
  }

  public function verbatim() {
    $this->verbatim = 'VERBATIM';
    return $this;
  }

  public function numericFilter( $fieldName, $min, $max = null ) {
    $max = $max ?? '+inf';
    $this->numericFilters[] = "@$fieldName:[$min $max]";
    return $this;
  }

  public function geoFilter( $fieldName, $longitude, $latitude, $radius, $distanceUnit = 'km' ) {
    $geo_units = array( 'm', 'km', 'mi', 'ft' );
    if ( !in_array($distanceUnit, $geo_units) ) {
      throw new InvalidArgumentException($distanceUnit);
    }

    $this->geoFilters[] = "@$fieldName:[$longitude $latitude $radius $distanceUnit]";
    return $this;
  }

  public function sortBy( $fieldName, $order = 'ASC' ) {
    $this->sortBy = "SORTBY $fieldName $order";
    return $this;
  }

  public function scorer( $scoringFunction ) {
    $this->scorer = "SCORER $scoringFunction";
    return $this;
  }

  public function language( $languageName ) {
    $this->language = "LANGUAGE $languageName";
    return $this;
  }

  public function searchQueryArgs( $query ) {
      $queryParts = array_merge( [$query], $this->numericFilters, $this->geoFilters );
      $queryWithFilters = "'" . implode( ' ', $queryParts ) . "'";
      return array_filter(
          array_merge(
              trim($queryWithFilters) === '' ? array( $this->indexName ) : array( $this->indexName, $queryWithFilters ),
              explode( ' ', $this->limit ),
              explode( ' ', $this->slop ),
              array( $this->verbatim, $this->withScores, $this->withPayloads, $this->noStopWords, $this->noContent),
              explode( ' ', $this->inFields),
              explode( ' ', $this->inKeys ),
              explode( ' ', $this->return ),
              explode( ' ', $this->summarize ),
              explode( ' ', $this->highlight ),
              explode( ' ', $this->sortBy ),
              explode( ' ', $this->scorer ),
              explode( ' ', $this->language ),
              explode( ' ', $this->expander ),
              explode( ' ', $this->payload )
          ),
          function ( $item ) {
            return !is_null( $item ) && $item !== '';
          }
      );
  }

  public function search( $query = '', $documentsAsArray = false ) {
    $rawResult = $this->client->rawCommand( 'FT.SEARCH', $this->searchQueryArgs( $query ) );

    return $rawResult ? SearchResult::searchResult(
          $rawResult,
          $documentsAsArray,
          $this->withScores !== '',
          $this->withPayloads !== '',
          $this->noContent !== ''
      ) : new SearchResult(0, []);
  }

  public function explain( $query ) {
    return $this->client->rawCommand( 'FT.EXPLAIN', $this->searchQueryArgs( $query ) );
  }
  
}