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

$indexInfo = $index->writeToDisk();

echo 'Index <b>' . $index->getIndexName() . '</b> have been written to disk for persistence.';