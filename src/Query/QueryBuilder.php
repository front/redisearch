<?php

namespace FKRediSearch\Query;

use InvalidArgumentException;

/**
 * Allows building redisearch queries
 * @see https://oss.redislabs.com/redisearch/Query_Syntax.html
 */
class QueryBuilder {

  // characters which have special meaning in resis queries
  // their presence can cause unexpected behavior, so they should be escaped or removed
  const SYNTAX_CHARACTERS = '{}[]()":;@$%*-~';

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
   * @var bool whether to tokenize by unescaped non-word characters
   */
  protected $tokenize;

  /**
   * @var string reg-exp character class of characters to escape
   */
  protected $escape;

  /**
   * @var boolean should string be searched using prefix matching
   */
  protected $prefixMatching;

  /**
   * @var integer should string be searched using fuzzy matching
   */
  protected $fuzzyMatching;

  /**
   * @var integer Weight difference between fuzzy and exact match
   */
  protected $fuzzyDifference;

  /**
   * @var array list of stop words which to avoid in fuzzy matching
   */
  protected $stopWords;

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

  /**
   * Transform condition array into condition string (for lazy transformations)
   * @param array $data an assoc array with condition info, required keys:
   * - conjunction - conjunction operator (AND or OR)
   * - values - an array of values, before applying search methods such as tokenization
   * @return string redis query condition
   */
  protected function stringFromDataArray($data) {
    if (!isset($data['conjunction'], $data['values'])) {
      return NULL;
    }
    $values = $this->applySearchMethods($data['values']);
    return $this->createCondition($values, $data['conjunction']);
  }

  /**
   * Escape characters in string
   * @param string $string to escape
   * @param string $characterClass regexp character class to be escaped
   * @param boolean $escape whether to escape contents of $characters
   * @return string replaced string
   */
  protected function escapeCharacters($string, $characters, $escape = TRUE) {
    if ($escape) {
      // Escape every character to avoid ambiguity
      $characters = '\\' . implode('\\', str_split($characters));
    }
    $pattern = '\\\\?([' . $characters . '])';
    return preg_replace("/$pattern/u", '\\\\\1', $string);
  }

  /**
   * Remove unescaped syntax characters from string
   * @param string $string to filter
   * @return string filtered string
   */
  protected function removeSyntaxCharacters($string) {
    $characters = '\\' . implode('\\', str_split(self::SYNTAX_CHARACTERS));
    $pattern = "(?<!\\\\)[$characters]";
    return preg_replace("/$pattern/", '', $string);
  }

  /**
   * Applies various search method to parameters, such as prefix matching
   * @param string[] $values an array of search terms
   * @return string[] an array of search terms with search methods applied
   */
  protected function applySearchMethods(array $values) {

    // todo matching with negation (-) and optional terms (~)
    // escape characters
    if ($this->escape) {
      $values = array_map(function($el) {
        return $this->escapeCharacters($el, $this->escape);
      }, $values);
    }

    // split by unescaped non-word characters
    if ($this->tokenize) {
      $new_values = [];
      $pattern = '(?<!\\\\)[^\w]';
      foreach ($values as $value) {
        $split_data = preg_split("/$pattern/u", $value, NULL, PREG_SPLIT_NO_EMPTY);
        if ($split_data) {
          $new_values = array_merge($new_values, $split_data);
        }
      }
      $values = $new_values;
    }

    // remove unescaped syntax characters
    $values = array_map(function($el) {
      return $this->removeSyntaxCharacters($el);
    }, $values);

    foreach ($values as $i => $value) {
      $tmp = [];

      $tmp[] = $value . '=>{$weight:' . $this->fuzzyDifference * 2 . '}';

      if ($this->prefixMatching) {
//        $tmp[] = "$value";
        $tmp[] = $value . '*=>{$weight:' . $this->fuzzyDifference . '}';
      }

      $fuzzyInRange = $this->fuzzyMatching >= 1 && $this->fuzzyMatching <= 3;
      if ($fuzzyInRange) {
        $fuzzy_border = str_repeat('%', $this->fuzzyMatching);
        // escape everything which is not a word to avoid syntax error
        $tmp_string = $this->escapeCharacters($value, '^\w', FALSE);
        $tmp[] = "$fuzzy_border$tmp_string$fuzzy_border";
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
   * - escape
   * - prefixMatching
   * - fuzzyMatching
   * - stopWords
   */
  public function __construct($params = []) {
    $this->conjunction = $params['conjunction'] ?? 'AND';
    $this->allOnEmpty = $params['allOnEmpty'] ?? TRUE;
    $this->tokenize = $params['tokenize'] ?? FALSE;
    $this->escape = $params['escape'] ?? FALSE;
    $this->prefixMatching = $params['prefixMatching'] ?? FALSE;
    $this->fuzzyMatching = $params['fuzzyMatching'] ?? FALSE;
    $this->fuzzyDifference = $params['fuzzyDifference'] ?? 100;
    $this->stopWords = $params['stopWords'] ?? [];
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
    if (count($values) > 0) {
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
    if (count($values) > 0) {
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
   * Returns the array of present conditions made recursively
   * It can be used to determine if a given condition exists in a query or not
   * It does not return any info on the joining method of conditions
   * @param string|null $name the name of the condition field, empty if generic fields
   * @return array
   */
  public function getAllConditions($name = NULL) {
    $values = [];

    foreach ($this->genericConditions as $cond) {
      if ($cond instanceof self) {
        $values = array_merge($values, $cond->getAllConditions($name));
      }
      elseif ($name === NULL) {
        if (is_array($cond)) {
          $values = array_merge($values, $cond['values']);
        } else {
          $values[] = $cond;
        }
      }
    }

    if ($name !== NULL) {
      foreach ($this->conditions as $cond_name => $conds) {
        if ($cond_name == $name) {
          foreach ($conds as $cond) {
            if (is_array($cond)) {
              $values = array_merge($values, $cond['values']);
            } else {
              $values[] = $cond;
            }
          }
        }
      }
    }

    return $values;
  }

  /**
   * Sets conjunction operator
   * @param string $value new status
   * @return self
   */
  public function setConjunction($value) {
    $this->conjunction = $value;
    return $this;
  }

  /**
   * Sets tokenize status
   * @param boolean $value new status
   * @return self
   */
  public function setTokenize($value = TRUE) {
    $this->tokenize = $value;
    return $this;
  }

  /**
   * Sets fuzzy matching status
   * @param int|false $value new status
   * @param int $difference new status
   * @return self
   */
  public function setFuzzyMatching($value = 1, $difference = 100) {
    $this->fuzzyMatching = $value;
    $this->fuzzyDifference = $difference;
    return $this;
  }

  /**
   * Sets prefix matching status
   * @param boolean $value new status
   * @return self
   */
  public function setPrefixMatching($value = TRUE) {
    $this->prefixMatching = $value;
    return $this;
  }

  /**
   * Sets escape characters or disables escaping
   * @param string|FALSE $value new status
   * @return self
   */
  public function setEscape($value) {
    $this->escape = $value;
    return $this;
  }

  /**
   * Sets stop words
   * @param array $value new list of stop words
   * @return self
   */
  public function setStopWords(array $value) {
    $this->stopWords = $value;
    return $this;
  }
}

