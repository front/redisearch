<?php
namespace FKRediSearch\RediSearch;

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

  public static function searchResult( $rawRediSearchResult, $documentsAsArray, $withScores = false, $withPayloads = false, $noContent = false ) {
    $docWidth = $noContent ? 1 : 2;
    if ( !$rawRediSearchResult ) {
      return false;
    }

    if ( count( $rawRediSearchResult ) === 1 ) {
      return new SearchResult( 0, [] );
    }

    if ( $withScores ) {
      $docWidth++;
    }
    
    if ( $withPayloads ) {
      $docWidth++;
    }

    $count = array_shift( $rawRediSearchResult );
    $documents = [];

    for ($i = 0; $i < count( $rawRediSearchResult ); $i += $docWidth ) {
      $document = $documentsAsArray ? [] : new \stdClass();
      $documentsAsArray ?
        $document['id'] = $rawRediSearchResult[$i] :
        $document->id = $rawRediSearchResult[$i];

      if ( $withScores ) {
        $documentsAsArray ? $document['score'] = $rawRediSearchResult[ $i + 1 ] : $document->score = $rawRediSearchResult[ $i + 1 ];
      }
      
      if ($withPayloads) {
            $j = $withScores ? 2 : 1;
              $documentsAsArray ?
                  $document['payload'] = $rawRediSearchResult[$i+$j] :
                  $document->payload = $rawRediSearchResult[$i+$j];
      }
      
      if (!$noContent) {
        $fields = $rawRediSearchResult[$i + ($docWidth - 1)];
        
        if (is_array($fields)) {
          for ($j = 0; $j < count($fields); $j += 2) {
            $documentsAsArray ? $document[$fields[$j]] = $fields[$j + 1] : $document->{$fields[$j]} = $fields[$j + 1];
          }
        }
      }

      $documents[] = $document;
    }
      
    return new SearchResult($count, $documents);
  }
}