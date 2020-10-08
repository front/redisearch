<?php
/**
 * Create a temporary index which expires after 30 seconds
 */
require_once  '../vendor/autoload.php';

use FKRediSearch\Document;
use FKRediSearch\Fields\GeoLocation;
use FKRediSearch\Index;
use FKRediSearch\Setup;

$client = Setup::connect();

$index = new Index( $client );

$index->setIndexName('idx');

$document = new Document();
$document->setScore(0.2);
$document->setId('doc:123');
$document->setLanguage('english');
$document->setFields(
  array(
    'title'       => 'Document with score 0.2',
    'content'     => 'This is a lightweight implementation of redisearch',
    'permalink'   => 'https://testlenke.no',
    'category'    => 'search, fuzzy, synonym, phonetic',
    'date'        => strtotime( '2019-01-14 01:12:00' ),
    'location'    => new GeoLocation(-77.0366, 38.8977)
  )
);

$index->add( $document );

echo 'Document added to the index: ' . $document->getId();

