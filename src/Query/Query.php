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
  protected $withSortKey = '';
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

  public function __construct( \FKRediSearch\RedisRaw\PredisAdapter $client, string $indexName ) {
    $this->client = $client;
    $this->indexName = $indexName;
  }

  /**
   * If it appears after the query, just document ids will be returned not the content.
   * This is useful if RediSearch is only an index on an external document collection.
   *
   * @return object Query
   */
  public function noContent() {
    $this->noContent = 'NOCONTENT';
    return $this;
  }

  /**
   * Use this to limit which fields from the document are returned.
   * num is the number of fields following the keyword.
   * If num is 0, it acts like NOCONTENT .
   *
   * @param array $fields
   *
   * @return object Query
   */
  public function return( array $fields ) {
    $count = empty( $fields ) ? 0 : count( $fields );
    $field = implode(' ', $fields);
    $this->return = "RETURN $count $field";
    return $this;
  }

  /**
   * Use this option to return only the sections of the field which contain the matched text.
   * for more details, check https://oss.redislabs.com/redisearch/Highlight/
   *
   * @param array $fields
   * @param int $fragmentCount
   * @param int $fragmentLength
   * @param string $separator
   *
   * @return $this
   */
  public function summarize( array $fields, int $fragmentCount = 3, int $fragmentLength = 50, string $separator = '...' ) {
    $count = empty($fields) ? 0 : count($fields);
    $field = implode(' ', $fields);
    $this->summarize = "SUMMARIZE FIELDS $count $field FRAGS $fragmentCount LEN $fragmentLength SEPARATOR $separator";
    return $this;
  }

  /**
   * Use this option to format occurrences of matched text.
   * for more details, check https://oss.redislabs.com/redisearch/Highlight/
   *
   * @param array $fields
   * @param string $openTag
   * @param string $closeTag
   *
   * @return $this
   */
  public function highlight( array $fields, string $openTag = '<strong>', string $closeTag = '</strong>' ) {
    $count = empty($fields) ? 0 : count($fields);
    $field = implode(' ', $fields);
    $this->highlight = "HIGHLIGHT FIELDS $count $field TAGS $openTag $closeTag";
    return $this;
  }

  /**
   * If set, a custom query expander will be used instead of the stemmer.
   * Check https://oss.redislabs.com/redisearch/Extensions/
   *
   * @param $expander
   *
   * @return $this
   */
  public function expander( $expander ) {
    $this->expander = "EXPANDER $expander";
    return $this;
  }

  /**
   * Add an arbitrary, binary safe payload that will be exposed to custom scoring functions.
   *
   * @param $payload
   *
   * @return $this
   */
  public function payload( $payload ) {
    $this->payload = "PAYLOAD $payload";
    return $this;
  }

  /**
   * If the parameters appear after the query, results will be limited to the offset and number of results given.
   * The default is 0 10.
   *
   * @param int $offset
   * @param int $pageSize
   *
   * @return $this
   */
  public function limit( int $offset, int $pageSize = 10 ) {
    $this->limit = "LIMIT $offset $pageSize";
    return $this;
  }

  /**
   * If set, filter the results to ones appearing only in specific fields of the document, like title or URL.
   * num is the number of specified field arguments.
   *
   * @param int $number
   * @param array $fields
   *
   * @return $this
   */
  public function inFields( int $number, array $fields ) {
    $this->inFields = "INFIELDS $number " . implode(' ', $fields);
    return $this;
  }

  /**
   *  If set, limits the result to a given set of keys specified in the list.
   * The first argument must be the length of the list, and greater than zero.
   * Non-existent keys are ignored - unless all the keys are non-existent.
   *
   * @param int $number
   * @param array $keys
   *
   * @return $this
   */
  public function inKeys( int $number, array $keys ) {
    $this->inKeys = "INKEYS $number " . implode(' ', $keys);
    return $this;
  }

  /**
   *  It allows a maximum of N intervening number of unmatched offsets between phrase terms.
   * (i.e the slop for exact phrases is 0)
   *
   * @param int $slop
   *
   * @return $this
   */
  public function slop( int $slop ) {
    $this->slop = "SLOP $slop";
    return $this;
  }

  /**
   * If set, stopwords won't be filtered from the query.
   *
   * @return $this
   */
  public function noStopWords() {
    $this->noStopWords = 'NOSTOPWORDS';
    return $this;
  }

  /**
   * If set, optional document payloads retrieved.
   * The payloads follow the document id, and if WITHSCORES was set, follow the scores.
   *
   * @return $this
   */
  public function withPayloads() {
    $this->withPayloads = 'WITHPAYLOADS';
    return $this;
  }

  /**
   * If set, relative internal score of each document will be returned with the results.
   * This can be used to merge results from multiple instances.
   *
   * @return $this
   */
  public function withScores() {
    $this->withScores = 'WITHSCORES';
    return $this;
  }

  /**
   * Only relevant in conjunction with SORTBY.
   * Returns the value of the sorting key, right after the id and score and /or payload if requested.
   * This is usually not needed by users, and exists for distributed search coordination purposes.
   *
   * @return $this
   */
  public function withSortKey() {
    $this->withSortKey = 'WITHSORTKEYS';
    return $this;
  }

  /**
   * If set, we do not try to use stemming for query expansion but search the query terms verbatim.
   *
   * @return $this
   */
  public function verbatim() {
    $this->verbatim = 'VERBATIM';
    return $this;
  }

  public function withSchema() {
    $this->withSchema = 'WITHSCHEMA';
    return $this;
  }

  /**
   * This methods, adds filter for numeric fields.
   *
   * @param string $fieldName
   * @param int $min
   * @param int|null $max
   *
   * @return $this
   */
  public function numericFilter( string $fieldName, int $min, int $max = null ) {
    $max = $max ?? '+inf';
    $this->numericFilters[] = "@$fieldName:[$min $max]";
    return $this;
  }

  /**
   * Filters the results to a given radius from lon and lat.
   * Radius is given as a number and units.
   * For more details, visit: https://redis.io/commands/georadius?_ga=2.96317980.1854485156.1601882802-570936883.1598442031
   *
   * @param string $fieldName
   * @param int $longitude
   * @param int $latitude
   * @param int $radius
   * @param string $distanceUnit
   *
   * @return $this
   */
  public function geoFilter( string $fieldName, int $longitude, int $latitude, int $radius, string $distanceUnit = 'km' ) {
    $geo_units = array( 'm', 'km', 'mi', 'ft' );
    if ( !in_array($distanceUnit, $geo_units) ) {
      throw new InvalidArgumentException($distanceUnit);
    }

    $this->geoFilters[] = "@$fieldName:[$longitude $latitude $radius $distanceUnit]";
    return $this;
  }

  /**
   * If specified, the results are ordered by the value of this field.
   * This applies to both text and numeric fields.
   *
   * @param string $fieldName
   * @param string $order
   *
   * @return $this
   */
  public function sortBy( string $fieldName, string $order = 'ASC' ) {
    $this->sortBy = "SORTBY $fieldName $order";
    return $this;
  }

  /**
   * If set, custom scoring function defined by the user applies.
   *
   * @param $scoringFunction
   *
   * @return $this
   */
  public function scorer( $scoringFunction ) {
    $this->scorer = "SCORER $scoringFunction";
    return $this;
  }

  /**
   * If set, stemmer for the supplied language during search for query expansion will be used.
   * If querying documents in Chinese, this should be set to chinese in order to properly tokenize the query terms.
   * Defaults to English.
   * If an unsupported language is sent, the command returns an error.
   *
   * @param string $languageName
   *
   * @return $this
   */
  public function language( string $languageName ) {
    $this->language = "LANGUAGE $languageName";
    return $this;
  }

  /**
   * The maximal Levenshtein distance for spelling suggestions (default: 1, max: 4).
   * @param int $distance
   *
   * @return $this
   */
  public function distance( int $distance ) {
    if ($distance < 1 || $distance > 4) {
      throw new InvalidArgumentException($distance);
    }
    $this->distance = "DISTANCE $distance";
    return $this;
  }

  /**
   * Specifies an inclusion ( INCLUDE ) or exclusion ( EXCLUDE ) custom dictionary named {dict}.
   * Refer to FT.DICTADD , FT.DICTDEL and FT.DICTDUMP for managing custom dictionaries.
   *
   * @param array $terms
   *
   * @return $this
   */
  public function terms( array $terms ) {
    $terms_list = [];
    foreach ($terms as $name => $include) {
      $include = $include ? 'INCLUDE' : 'EXCLUDE';
      $terms_list[] = "TERMS $include $name";
    }

    $this->terms = implode(' ', $terms_list);
    return $this;
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
   *
   * @param array $reducers
   *
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
   *
   * @param array $expr
   *
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

  /**
   * Prepare search query
   *
   * @param $query
   *
   * @return array
   */
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
            array( $this->verbatim, $this->withScores, $this->withSortKey, $this->withPayloads, $this->noStopWords, $this->noContent),
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

  /**
   * @param $query
   *
   * @return array
   */
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

  /**
   * @param $query
   *
   * @return array
   */
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

  /**
   * Apply search query and return the results
   *
   * @param string $query
   * @param bool $documentsAsArray
   *
   * @return SearchResult
   */
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

  /**
   * Explain the results, useful for debugging
   *
   * @param $query
   *
   * @return mixed
   */
  public function explain( $query ) {
    return $this->client->rawCommand( 'FT.EXPLAIN', $this->searchQueryArgs( $query ) );
  }

  /**
   * Return all possible values for given field
   *
   * @param string $fieldName
   *
   * @return array tags list
   */
  public function tagVals( string $fieldName ) {
    return $this->client->rawCommand( 'FT.TAGVALS', [$this->indexName, $fieldName]);
  }

  /**
   * Apply aggregation on redis query
   *
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

  /**
   * @param $query
   * @param bool $documentsAsArray
   *
   * @return SearchResult
   */
  public function spellcheck( $query, bool $documentsAsArray = false ) {
    $rawResult = $this->client->rawCommand( 'FT.SPELLCHECK', $this->spellcheckQueryArgs( $query ) );

    return SearchResult::spellcheckResult(
      $rawResult,
      $documentsAsArray
    );
  }

}
