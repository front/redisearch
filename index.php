<?php

require_once  __DIR__ . '/vendor/autoload.php';

use FKRediSearch\RediSearch\Index;

$index_name = 'testing';

$schema = array(
  'post_title', 'TEXT', 'WEIGHT', 5.0, 'SORTABLE',
  'post_content', 'TEXT',
  'post_id', 'NUMERIC', 'SORTABLE',
  'menu_order', 'NUMERIC',
  'permalink', 'TEXT',
  'post_date', 'NUMERIC', 'SORTABLE',
);

$client = \FKRediSearch\RediSearch\Setup::connect();

$index = new Index( $client );

$index->create( $index_name, $schema );
