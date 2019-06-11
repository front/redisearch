<?php
namespace FKRediSearch\Query;

class SearchResult {
  protected $count;
  protected $documents;

  public function __construct( $count, $documents ) {
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
      return false;
    }

    // return count if there's no body
    $count = array_shift( $rawRediSearchResult );
    if ( count( $rawRediSearchResult ) === 0 ) {
      return new SearchResult( $count, [] );
    }

    // calculate width of each document
    $results_count = count($rawRediSearchResult);
    $docWidth = count($rawRediSearchResult) / $count;
    if ( floor($docWidth) != $docWidth ) {
      throw new UnexpectedValueException("Malformed redis result");
    }

    // get data from redisearch response in friendlier format
    $rows = [];
    for ($i = 0; $i < $results_count; ++$i) {
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
      foreach ($documents as $i => $doc) {
        $documents[$i] = (object) $doc;
      }
    }

    return new SearchResult($count, $documents);
  }
}
