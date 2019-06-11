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

  protected $genericConditions = [];
  protected $conditions = [];
  protected $conjunction;
  protected $allOnEmpty;
  protected $partialSearch;

  protected function getConjunction($conjunction) {
    $available = [
      'AND' => ' ',
      'OR' => '|',
    ];

    return $available[$conjunction] ?? $available['AND'];
  }

  protected function applySearchMethods(array $values) {
    if ($this->partialSearch) {
      foreach ($values as $i => $value) {
        $values[$i] = "$value*";
      }
    }

    return $values;
  }

  protected function createCondition(array $values, $conjunction = NULL) {
    $conjunction = $conjunction ?? $this->conjunction;
    $condition = implode($this->getConjunction($conjunction), $values);
    return $condition;
  }

  public function __construct($params) {
    $this->conjunction = $params['conjunction'] ?? 'AND';
    $this->allOnEmpty = $params['allOnEmpty'] ?? TRUE;
    $this->partialSearch = $params['partialSearch'] ?? FALSE;
  }

  public function addCondition($field, array $values, $conjunction = 'AND') {
    $condition = $this->createCondition($values, $conjunction);
    $this->conditions[$field] = $this->conditions[$field] ?? [];
    $this->conditions[$field][] = $condition;

    return $this;
  }

  public function addGenericCondition(array $values, $conjunction = 'AND') {
    $values = $this->applySearchMethods($values);
    $condition = $this->createCondition($values, $conjunction);
    $this->genericConditions[] = $condition;

    return $this;
  }

  public function addSubcondition(QueryBuilder $subcondition) {
    $this->genericConditions[] = $subcondition;

    return $this;
  }

  public function buildRedisearchQuery($subquery = FALSE) {
    $query_array = [];

    // add general query values to search for
    foreach ($this->genericConditions as $value) {
      if ($value instanceof QueryBuilder) {

        // build a subquery and enclose in parenthesis
        $subvalue = $value->buildRedisearchQuery(TRUE);
        if (strlen($subvalue) > 0) {
          $value = "(" . $subvalue . ")";
        }
        else {
          $value = NULL;
        }
      }

      // filter out empty and duplicated values from main query
      if ($value !== NULL && !in_array($value, $query_array)) {
        $query_array[] = $value;
      }
    }

    // add field-specific values (no subqueries allowed)
    foreach ($this->conditions as $field => $values) {
      $values = $this->createCondition($values);
      $query_array[] = "@$field:$values";
    }

    // create query string and replace with asterisk if needed
    $query = $this->createCondition($query_array);
    if (strlen($query) == 0 && $this->allOnEmpty && !$subquery) {
      $query = '*';
    }

    return $query;
  }

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
          $object->addCondition($field, (array) $value);
        }
      }
    }

    return $object;
  }

}

