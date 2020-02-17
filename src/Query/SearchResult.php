<?php

namespace FKRediSearch\Query;

class SearchResult {
  protected $count;
  protected $documents;

  protected static function arrayToObject($documents) {
    foreach ($documents as $i => $doc) {
      $documents[$i] = (object) $doc;
    }
    return $documents;
  }

  public function __construct( $count = 0, $documents = 0 ) {
    $this->count = $count;
    $this->documents = $documents;
  }

  public function getCount() {
    return $this->count;
  }

  public function getDocuments() {
    return $this->documents;
  }

  public static function searchResult( $rawRediSearchResult, $documentsAsArray, $withIds = true, $withScores = false, $withPayloads = false ) {
    if ( !$rawRediSearchResult ) {
      return new static();
    }
    if ( !is_array($rawRediSearchResult) ) {
      return new static();
    }

    // return count if there's no body
    $count = array_shift( $rawRediSearchResult );
    if ( count( $rawRediSearchResult ) === 0 ) {
      return new static( $count );
    }

    // calculate width of each document
    $results_count = count($rawRediSearchResult);
    $docWidth = 1;
    $docWidth += $withIds ? 1 : 0;
    $docWidth += $withScores ? 1 : 0;
    $docWidth += $withPayloads ? 1 : 0;
    if ( $results_count % $docWidth != 0 ) {
      throw new \UnexpectedValueException('Malformed redisearch result');
    }

    // get data from redisearch response in friendlier format
    $rows = [];
    for ($i = 0; $i < $results_count; $i += $docWidth) {
      $rows[] = array_slice($rawRediSearchResult, $i, $docWidth);
    }

    $documents = [];
    foreach ($rows as $row_data) {
      $document = [];
      $document['id'] = $withIds ? array_shift($row_data) : NULL;
      $document['score'] = $withScores ? array_shift($row_data) : NULL;
      $document['payload'] = $withPayloads ? array_shift($row_data) : NULL;

      // Add fields to document
      if ( count($row_data) > 0 && is_array($row_data[0]) ) {
        $fields = $row_data[0];
        for ($i = 0; $i < count($fields); $i += 2) {
          $document[$fields[$i]] = $fields[$i + 1];
        }
      }

      $documents[] = $document;
    }

    // Transform result into object if needed
    if (!$documentsAsArray) {
      $documents = static::arrayToObject($documents);
    }

    return new static($count, $documents);
  }

  public static function spellcheckResult($rawRediSearchResult, $documentsAsArray) {
    if ( !$rawRediSearchResult ) {
      return new static();
    }
    if ( !is_array($rawRediSearchResult) ) {
      return new static();
    }

    $documents = [];
    foreach ($rawRediSearchResult as $row_data) {
      $document = [];

      $term = array_shift($row_data);
      if ($term == 'TERM') {
        $document['term'] = array_shift($row_data);
      }

      if (!isset($document['term']) || count($row_data) != 1) {
        throw new \UnexpectedValueException('Malformed data - not a spellcheck result');
      }

      // Add suggestions to document
      $document['suggestions'] = [];
      foreach ($row_data[0] as $suggestion) {
        list ($score, $word) = $suggestion;

        // discard suggestions shorter than 3 letters
        if (strlen($word) > 2) {
          $document['suggestions'][$word] = $score;
        }
      }

      $documents[] = $document;
    }

    // Transform result into object if needed
    if (!$documentsAsArray) {
      $documents = static::arrayToObject($documents);
    }

    return new static(count($documents), $documents);
  }
}
