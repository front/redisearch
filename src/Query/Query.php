<?php

namespace FKRediSearch\Query;

use InvalidArgumentException;
use FKRediSearch\Query\QueryBuilder;

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
  protected $groupBy = '';
  protected $reduce = '';
  protected $apply = '';
  protected $filter = '';
  protected $withSchema = '';
  protected $distance = '';
  protected $terms = '';
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

  public function payload( $payload ) {
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

  public function withSchema() {
    $this->withSchema = 'WITHSCHEMA';
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

  public function distance( $distance ) {
    if ($distance < 1 || $distance > 4) {
      throw new InvalidArgumentException($distance);
    }
    $this->distance = "DISTANCE $distance";
    return $this;
  }

  public function terms( array $terms ) {
    $terms_list = [];
    foreach ($terms as $name => $include) {
      $include = $include ? 'INCLUDE' : 'EXCLUDE';
      $terms_list[] = "TERMS $include $name";
    }

    $this->terms = implode(' ', $terms_list);
  }

  /**
   * Apply a grouping to aggregation
   * @arg array $group an array of grouping field names
   * @return object this object
   */
  public function groupBy( array $group ) {
    $group = array_map(function($n) { return "@$n"; }, $group);
    $group = implode(' ', array_merge([count($group)], $group));
    $this->groupBy = "GROUPBY $group";
    return $this;
  }

  /**
   * Apply a reducer function on grouping aggregation. Has no effect if no grouping was used
   * @arg string $alias reducer's alias
   * @arg string $type_readable a readable name of function, e.g. "first value", "tolist"
   * @arg array $arguments an array of arguments for reducer function
   * @return object this object
   */
  public function reduce( array $reducers ) {
    $valid_reducers = [
      'COUNT',   'COUNT_DISTINCT',  'COUNT_DISTINCTISH',
      'SUM',     'MIN',             'MAX',
      'AVG',     'STDDEV',          'QUANTILE',
      'TOLIST',  'FIRST_VALUE',     'RANDOM_SAMPLE'
    ];

    $reducers_parsed = [];
    foreach ( $reducers as $alias => $reducer_data ) {
      $type_readable = array_shift($reducer_data);
      $type = mb_strtoupper(str_replace(' ', '_', $type_readable));

      // name supplied must be redis-compatible
      if ( in_array($type, $valid_reducers) ) {
        $reducer_data = array_map(function($a) { return "@$a"; }, $reducer_data);
        array_unshift($reducer_data, count($reducer_data));
        $reducer = "$type " . implode(' ', $reducer_data);
        // only non-numeric keys are aliases
        if (!preg_match('/^[0-9]+^/', $alias)) {
          $reducer .= " AS $alias";
        }
        $reducers_parsed[] = $reducer;
      } else {
        throw new BadMethodCallException("Unknown reducer: $type_readable");
      }
    }

    // apply joined reducers to object's field
    $this->reduce = 'REDUCE ' . implode(' ', $reducers_parsed);
    return $this;
  }

  public function apply( array $apply ) {
    foreach ( $apply as $alias => $name ) {
      $apply[$alias] = "APPLY $name AS $alias";
    }
    $this->apply = implode(' ', $apply);
    return $this;
  }

  /**
   * Add filter to aggregation.
   * @arg array $exp an array of filtering arguments. Examples:
   * [['a', '>=', 1]] - filter a to be greater than 1
   * [['a', '==', 5], '&&', ['b', '!']] - filter a to be equal 5 and b to be false
   * @return object this object
   */
  public function filter( array $expr ) {
    foreach ( $expr as $i => $element ) {
      if ( is_array($element) ) {
        $element[0] = '@' . $element[0];
        if ( count($element) == 3 ) {
          $tmpel = implode(' ', $element);
        } else {
          $tmpel = implode('', array_reverse($element));
        }
        $expr[$i] = $tmpel;
      }
    }
    $expr_string = implode(' ', $expr);
    $this->filter = "FILTER \"$expr_string\"";
    return $this;
  }

  public function searchQueryArgs( $query ) {
    if ($query instanceof QueryBuilder) {
      $query = $query->buildRedisearchQuery();
    }
    $queryParts = array_merge( (array) $query, $this->numericFilters, $this->geoFilters );
    $queryWithFilters = implode( ' ', $queryParts );
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

  public function aggregateQueryArgs( $query ) {
    if ($query instanceof QueryBuilder) {
      $query = $query->buildRedisearchQuery();
    }
    $queryParts = array_merge( (array) $query, $this->numericFilters, $this->geoFilters );
    $queryWithFilters = implode( ' ', $queryParts );

    // join together groupBy and reduce - they need one another
    if ( strlen($this->groupBy) > 0 && strlen($this->reduce) > 0 ) {
      $groupBy = implode(' ', [$this->groupBy, $this->reduce]);
    } else {
      $groupBy = NULL;
    }

    return array_filter(
        array_merge(
            trim($queryWithFilters) === '' ? array( $this->indexName ) : array( $this->indexName, $queryWithFilters ),
            explode( ' ', $this->limit ),
            array( $this->verbatim, $this->withSchema ),
            explode( ' ', $this->sortBy ),
            explode( ' ', $this->filter ),
            explode( ' ', $groupBy ),
            explode( ' ', $this->apply )
        ),
        function ( $item ) {
          return !is_null( $item ) && $item !== '';
        }
    );
  }

  public function spellcheckQueryArgs( $query ) {
    if ($query instanceof QueryBuilder) {
      $query = $query->buildRedisearchQuery();
    }
    $query = implode( ' ', (array) $query );

    return array_filter(
        array_merge(
            trim($query) === '' ? array( $this->indexName ) : array( $this->indexName, $query ),
            explode( ' ', $this->distance ),
            explode( ' ', $this->terms )
        ),
        function ( $item ) {
          return !is_null( $item ) && $item !== '';
        }
    );
  }

  public function search( $query = '', $documentsAsArray = false ) {
    $rawResult = $this->client->rawCommand( 'FT.SEARCH', $this->searchQueryArgs( $query ) );

    return SearchResult::searchResult(
      $rawResult,
      $documentsAsArray,
      true,
      $this->withScores !== '',
      $this->withPayloads !== ''
    );
  }

  public function explain( $query ) {
    return $this->client->rawCommand( 'FT.EXPLAIN', $this->searchQueryArgs( $query ) );
  }

  /**
   * Return all possible values for given field
   * @arg $fieldName string field's name in index
   * @return array tags list
   */
  public function tagVals( $fieldName ) {
    return $this->client->rawCommand( 'FT.TAGVALS', [$this->indexName, $fieldName]);
  }

  /**
   * Apply aggregation on redis query
   * @arg string $query redis query to be run
   * @arg boolean $documentAsArray
   * @return SearchResult the result of aggregation
   */
  public function aggregate( $query, $documentsAsArray = false ) {
    $rawResult = $this->client->rawCommand( 'FT.AGGREGATE', $this->aggregateQueryArgs( $query ) );

    return SearchResult::searchResult(
      $rawResult,
      $documentsAsArray,
      false
    );
  }

  public function spellcheck( $query, $documentsAsArray = false ) {
    $rawResult = $this->client->rawCommand( 'FT.SPELLCHECK', $this->spellcheckQueryArgs( $query ) );

    return SearchResult::spellcheckResult(
      $rawResult,
      $documentsAsArray
    );
  }

}
