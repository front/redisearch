<?php
/**
 * Create a temporary index which expires after 30 seconds
 */
require_once  '../vendor/autoload.php';

use FKRediSearch\Index;
use FKRediSearch\Setup;

$client = Setup::connect();

$index = new Index( $client );

$index->setIndexName('idx');

$index->on('HASH')
      ->setPrefix('doc:')
      ->setDefaultLang('norwegian')
//      ->setTemporary(30)
      ->setScoreField('score')
      ->addTextField('title', 12)
      ->addTextField('content')
      ->addTextField('permalink')
      ->addTagField('category')
      ->addNumericField('date')
      ->addGeoField('location')
      ->create();

echo 'Index have been created: ' . $index->getIndexName();

