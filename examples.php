<?php
/**
 * This file is just for playing with and contains some examples
 */
require_once  __DIR__ . '/vendor/autoload.php';

use FKRediSearch\Index;
use FKRediSearch\Search;
use FKRediSearch\Query;
use FKRediSearch\Document;
use FKRediSearch\Fields;
use FKRediSearch\Fields\TextField;
use FKRediSearch\Fields\NumericField;
use FKRediSearch\Fields\TagField;
use FKRediSearch\Fields\Geo;
use FKRediSearch\Fields\GeoLocation;


$client = \FKRediSearch\Setup::connect();

$index = new Index( $client );
$index->setIndexName('test');

$index->addTextField('title', 12)
    ->addTextField('content')
    ->addTextField('permalink')
    ->addTagField('category')
    ->addNumericField('date')
    ->addGeoField('location')
    ->create();

  
$synonym = array(
  array( 'boy', 'child', 'baby' ),
  array( 'girl', 'child', 'baby' ),
  array( 'man', 'person', 'adult' )
);
  
$index->synonymAdd( $synonym );

// $index->drop();

$document = new Document();
$document->setScore(0.3);
$document->setLanguage('english');
$document->setFields(
  array(
    'title'       => 'Document title like post title',
    'content'     => 'This is a lightweight implementation of redisearch',
    'permalink'   => 'https://testlenke.no',
    'category'    => 'search, fuzzy, synonym, phonetic',
    'date'        => strtotime( '2019-01-14 01:12:00' ),
    'location'    => new GeoLocation(-77.0366, 38.8977)
  )
);

// $index->add( $document );


 

// $search = new Search( $client );

// $results = $search->search( $index_name, 'awesome' );

$search = new Query( $client, 'test' );

$results = $search
        // ->sortBy( $fieldName, $order = 'ASC' )
        // ->geoFilter( $fieldName, $longitude, $latitude, $radius, $distanceUnit = 'km' )
        // ->numericFilter( $fieldName, $min, $max = null )
        // ->withScores() // If set, we also return the relative internal score of each document. this can be used to merge results from multiple instances
        // ->verbatim() // if set, we do not try to use stemming for query expansion but search the query terms verbatim.
        // ->withPayloads() // If set, we retrieve optional document payloads (see FT.ADD). the payloads follow the document id, and if WITHSCORES was set, follow the scores
        // ->noStopWords() //  If set, we do not filter stopwords from the query
        // ->slop() // If set, we allow a maximum of N intervening number of unmatched offsets between phrase terms. (i.e the slop for exact phrases is 0)
        // ->inKeys( $number, $keys ) // If set, we limit the result to a given set of keys specified in the list. the first argument must be the length of the list, and greater than zero. Non-existent keys are ignored - unless all the keys are non-existent.
        // ->inFields( $number, $fields ) // If set, filter the results to ones appearing only in specific fields of the document, like title or URL. num is the number of specified field arguments
        // ->limit( $offset, $pageSize = 10 ) // If set, we limit the results to the offset and number of results given. The default is 0 10
        // ->highlight( $fields, $openTag = '<strong>', $closeTag = '</strong>') 
        ->summarize( ['content'], 3, 12) // Use this option to return only the sections of the field which contain the matched text
        // ->return( $fields ) // Use this keyword to limit which fields from the document are returned. num is the number of fields following the keyword. If num is 0, it acts like NOCONTENT.
        // ->noContent() // If it appears after the query, we only return the document ids and not the content. This is useful if RediSearch is only an index on an external document collection
        ->search('doc*');

  ?>
  <pre>
  <?php print_r($results) ?>
  </pre>

