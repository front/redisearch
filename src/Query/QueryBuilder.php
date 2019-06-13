<?php

namespace FKRediSearch\Query;

use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use InvalidArgumentException;

/**
 * Allows building redisearch queries
 * @see https://oss.redislabs.com/redisearch/Query_Syntax.html
 */
class QueryBuilder {

  /**
   * @var string[] conditions not assigned to fields (fulltext matching)
   */
  protected $genericConditions = [];

  /**
   * @var string[] conditions assigned to specific fields
   */
  protected $conditions = [];

  /**
   * @var string operator which will join all conditions (AND or OR)
   */
  protected $conjunction;

  /**
   * @var boolean should empty search terms be replaced with a wildcard
   */
  protected $allOnEmpty;

  /**
   * @var boolean should search terms be split into words
   */
  protected $tokenize;

  /**
   * @var boolean should string be searched using prefix matching
   */
  protected $prefixMatching;

  /**
   * @var integer should string be searched using fuzzy matching
   */
  protected $fuzzyMatching;

  /**
   * Transforms conjunction operator into redisearch equivalent
   * @param string $conjunction conjunction operator (AND or OR)
   * @return string redisearch conjunctor
   */
  protected function getConjunction($conjunction) {
    $available = [
      'AND' => ' ',
      'OR' => '|',
    ];

    return $available[$conjunction] ?? $available['AND'];
  }

  protected function stringFromDataArray($data) {
    if (!isset($data['conjunction'], $data['values'])) {
      return NULL;
    }
    $values = $this->applySearchMethods($data['values']);
    return $this->createCondition($values, $data['conjunction']);
  }

  /**
   * Applies various search method to parameters, such as prefix matching
   * @param string[] $values an array of search terms
   * @return string[] an array of search terms with search methods applied
   */
  protected function applySearchMethods(array $values) {

    if ($this->tokenize) {
      $new_values = []; 
      foreach ($values as $value) {
        $new_values = array_merge($new_values, explode(' ', $value));
      }
      $values = $new_values;
    }

    foreach ($values as $i => $value) {
      $tmp = [];

      if ($this->prefixMatching) {
        $tmp[] = "$value*";
      }
      elseif ($this->fuzzyMatching && $this->fuzzyMatching >= 1 && $this->fuzzyMatching <= 3) {
        $fuzzy_border = str_repeat('%', $this->fuzzyMatching);
        $tmp[] = "$fuzzy_border$value$fuzzy_border";
      }

      if (count($tmp) == 0) {
        continue;
      }

      $value = implode($this->getConjunction('OR'), $tmp);
      if (count($tmp) > 1) {
        $value = "($value)";
      }
      $values[$i] = $value;
    }

    return $values;
  }

  /**
   * Creates a condition out of search terms and conjunction operator
   * @param string[] $values array of search terms
   * @param string|NULL $conjunction conjunction operator
   * @return string created condition string
   */
  protected function createCondition(array $values, $conjunction = NULL) {
    $conjunction = $conjunction ?? $this->conjunction;
    $condition = implode($this->getConjunction($conjunction), $values);
    return $condition;
  }

  /**
   * Constructs the object and sets parameters
   * @param array $params list of parameters to search query. Following keys allowed:
   * - conjunction
   * - allOnEmpty
   * - tokenize
   * - prefixMatching
   * - fuzzyMatching
   */
  public function __construct($params) {
    $this->conjunction = $params['conjunction'] ?? 'AND';
    $this->allOnEmpty = $params['allOnEmpty'] ?? TRUE;
    $this->tokenize = $params['tokenize'] ?? FALSE;
    $this->prefixMatching = $params['prefixMatching'] ?? FALSE;
    $this->fuzzyMatching = $params['fuzzyMatching'] ?? FALSE;
  }

  /**
   * Adds condition based on specific field
   * @param string $field field's name
   * @param string[] $values list of search terms
   * @param string $conjunction conjunction operator
   * @param boolean $exact whenever to apply search methods on search terms, such as tokenization
   * @return self
   */
  public function addCondition($field, array $values, $conjunction = 'AND', $exact = FALSE) {
    $this->conditions[$field] = $this->conditions[$field] ?? [];

    if ($exact) {
      $condition = $this->createCondition($values, $conjunction);
      $this->conditions[$field][] = $condition;
    }
    else {
      $this->conditions[$field][] = [
        'conjunction' => $conjunction,
        'values' => $values
      ];
    }

    return $this;
  }

  /**
   * Adds fullsearch condition (not based on specific field)
   * @param string[] $values list of search terms
   * @param string $conjunction conjunction operator
   * @param boolean $exact whenever to apply search methods on search terms, such as tokenization
   * @return self
   */
  public function addGenericCondition(array $values, $conjunction = 'AND', $exact = FALSE) {
    if ($exact) {
      $condition = $this->createCondition($values, $conjunction);
      $this->genericConditions[] = $condition;
    }
    else {
      $this->genericConditions[] = [
        'conjunction' => $conjunction,
        'values' => $values
      ];
    }

    return $this;
  }

  /**
   * Adds subcondition
   * @param QueryBuilder $subcondition a condition to be nested
   * @return self
   */
  public function addSubcondition(QueryBuilder $subcondition) {
    $this->genericConditions[] = $subcondition;

    return $this;
  }

  /**
   * Builds redisearch query string
   * @param boolean $subquery whether this is nested build call
   * @return string built query
   */
  public function buildRedisearchQuery($subquery = FALSE) {
    $query_array = [];

    // add general query values to search for
    foreach ($this->genericConditions as $value) {
      if ($value instanceof QueryBuilder) {

        // build a subquery and enclose in parenthesis
        $subvalue = $value->buildRedisearchQuery(TRUE);
        if (strlen($subvalue) > 0) {
          $value = '(' . $subvalue . ')';
        }
        else {
          $value = NULL;
        }
      }
      elseif (is_array($value)) {
        $value = $this->stringFromDataArray($value);
      }

      // filter out empty and duplicated values from main query
      if ($value !== NULL && !in_array($value, $query_array)) {
        $query_array[] = $value;
      }
    }

    // add field-specific values (no subqueries allowed)
    foreach ($this->conditions as $field => $values) {
      $field_values = [];
      foreach ($values as $i => $value) {
        if (is_array($value)) {
          $value = $this->stringFromDataArray($value);
        }

        if ($value !== NULL) {
          $field_values[] = $value;
        }
      }

      $value = $this->createCondition($field_values);
      $query_array[] = "(@$field:$value)";
    }

    // create query string and replace with asterisk if needed
    $query = $this->createCondition($query_array);
    if (strlen($query) == 0 && $this->allOnEmpty && !$subquery) {
      $query = '*';
    }

    return $query;
  }

  /**
   * Creates QueryBuilder instance from ConditionGroupInterface instance
   * @param ConditionGroupInterface $condition
   * @param string[] $params a list of parameters passed to QueryBuilder constructor
   * @return QueryBuilder
   */
  public static function fromConditionGroup(ConditionGroupInterface $condition, array $params = []) {
    $params["conjunction"] = $condition->getConjunction();
    $object = new static($params);
    foreach ($condition->getConditions() as $subcondition) {

      if ($subcondition instanceof ConditionGroupInterface) {
        // recurse into nested groups
        $object->addSubcondition(static::fromConditionGroup($subcondition, $params));
      }
      elseif ($subcondition instanceof ConditionInterface) {
        // conditions are simple field conditions
        $field = $subcondition->getField(); 
        $value = $subcondition->getValue(); 
        $operator = $subcondition->getOperator();

        // filter as much as possible - discard unsupported operators
        if ($operator == '=') {
          $object->addCondition($field, (array) $value, 'AND', TRUE);
        }
      }
    }

    return $object;
  }

  /**
   * Sets tokenize status, should be applied before adding conditions for consistency
   * @param boolean $value new status
   * @return self
   */
  public function setTokenize($value = TRUE) {
    $this->tokenize = $value;
    return $this;
  }

  /**
   * Sets fuzzy matching status, should be applied before adding conditions for consistency
   * @param int|false $value new status
   * @return self
   */
  public function setFuzzyMatching($value = 1) {
    $this->fuzzyMatching = $value;
    return $this;
  }

  /**
   * Sets prefix matching status, should be applied before adding conditions for consistency
   * @param boolean $value new status
   * @return self
   */
  public function setPrefixMatching($value = TRUE) {
    $this->prefixMatching = $value;
    return $this;
  }
}

