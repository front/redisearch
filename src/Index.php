<?php

namespace FKRediSearch;

use FKRediSearch\Fields\FieldInterface;
use FKRediSearch\Fields\GeoField;
use FKRediSearch\Fields\NumericField;
use FKRediSearch\Fields\TextField;
use FKRediSearch\Fields\TagField;

class Index {

	/**
	 * @var object
	 */
	public $client;

	/**
	 * @var string
	 */
	private $indexName;

	/**
	 * @var bool
	 */
	private $noOffsetsEnabled = FALSE;

	/**
	 * @var bool
	 */
	private $noFieldsEnabled = FALSE;

	/**
	 * @var array|int
	 */
	private $stopWords = NULL;

	/**
	 * @var string
	 */
	private $on = 'hash';

	/**
	 * @var array|string
	 */
	private $prefix = '*';

	/**
	 * @var string
	 */
	private $language = 'english';

	/**
	 * @var string
	 */
	private $langField = NULL;

	/**
	 * @var float
	 */
	private $score = 1.0;

	/**
	 * @var string
	 */
	private $scoreField = NULL;

	/**
	 * @var string
	 */
	private $payloadField = NULL;

	/**
	 * @var int
	 */
	private $maxFields = NULL;

	/**
	 * @var bool
	 */
	private $temporary = FALSE;

	/**
	 * @var int
	 */
	private $expires = 1;

	/**
	 * @var bool
	 */
	private $noHighlight = FALSE;

	/**
	 * @var bool
	 */
	private $nofreqs = FALSE;

	/**
	 * @var bool
	 */
	private $skipInScan = FALSE;


  /**
   * Index constructor.
   *
   * @param RedisRaw\PredisAdapter $client The redis client instance
   */
	public function __construct( RedisRaw\PredisAdapter $client ) {
		$this->client = $client;
	}

  /**
   * Drop existing index.
   *
   * @param bool $deleteHash
   *
   * @return bool
   */
	public function drop( bool $deleteHash = FALSE ) {
	  $dropOptions = array(
      $this->getIndexName()
    );
	  if ( $deleteHash ) {
	    $dropOptions[] = 'DD';
    }
		return $this->client->rawCommand( 'FT.DROPINDEX', $dropOptions );
	}

	/**
	 * Create index with passed fields and settings
	 *
	 * @return mixed
	 */
	public function create() {
		$properties = array( $this->getIndexName() );

		$properties = array_merge( $properties, array('ON', $this->on) );

		if ( is_array( $this->getPrefix() ) ) {
		  $prefixCount = count( $this->getPrefix() );
      $properties = array_merge( $properties, array_merge( array('PREFIX', $prefixCount), $this->getPrefix()) );
    } else {
		  $properties = array_merge( $properties, array('PREFIX', 1, $this->getPrefix()) );
    }

		$properties = array_merge( $properties, array('LANGUAGE', $this->getDefaultLang()) );

		if ( $this->hasLangField() ) {
			$properties = array_merge( $properties, array('LANGUAGE_FIELD', $this->getLangField()) );
		}

		$properties = array_merge( $properties,  array('SCORE', $this->getScore()) );

		if ( $this->isScoreFieldSet() ) {
			$properties = array_merge( $properties, array( 'SCORE_FIELD', $this->getScoreField()) );
		}

		if ( $this->isPayloadFieldSet() ) {
		  $properties = array_merge( $properties, array( 'PAYLOAD_FIELD', $this->getPayloadField()) );
    }

		if ( $this->isMaxFieldsSet() ) {
		  $properties = array_merge( $properties, array( 'MAXTEXTFIELDS', $this->getMaxFields()) );
    }

		if ( $this->isNoOffsetsEnabled() ) {
			$properties[] = 'NOOFFSETS';
		}

		if ( $this->isTemporary() ) {
		  $properties = array_merge( $properties, array( 'TEMPORARY', $this->getExpirationTime()) );
    }

    if ( $this->isNoHighlight() ) {
      $properties[] = 'NOHL';
    }

    if ( $this->isNoFieldsEnabled() ) {
      $properties[] = 'NOFIELDS';
    }

    if ( $this->isNoFreqsSet() ) {
      $properties[] = 'NOFREQS';
    }

    if ( $this->isSkipInitialScanSet() ) {
      $properties[] = 'SKIPINITIALSCAN';
    }

		if ( ! is_null( $this->stopWords ) ) {
			$properties[] = 'STOPWORDS';
			$properties[] = count( $this->stopWords );
			$properties   = array_merge( $properties, $this->stopWords );
		}

		if ( $this->stopWords == 0 ) {
			$properties[] = 'STOPWORDS';
			$properties[] = 0;
		}

		$properties[] = 'SCHEMA';

		$fieldDefinitions = [];
		foreach ( get_object_vars( $this ) as $field ) {
			if ( $field instanceof FieldInterface ) {
				$fieldDefinitions = array_merge( $fieldDefinitions, $field->getDefinition() );
			}
		}

		if ( count( $fieldDefinitions ) === 0 ) {
			return $this;
		}

		return $this->client->rawCommand( 'FT.CREATE', array_merge( $properties, $fieldDefinitions ) );
	}

  /**
   * The Redis structure which the index will be created based on
   * Currently, only HASH supported, but other types will be supported in the future
   *
   * @param string $on
   *
   * @return object Index
   */
	public function on( string $on = 'HASH' ) {
	  $this->on = $on;
	  return $this;
  }

  /**
   * Tells the index which keys it should index.
   * You can add several prefixes to index.
   * Since the argument is optional, the default is * (all keys)
   *
   * @param array|string $prefix
   *
   * @return object Index
   */
  public function setPrefix( $prefix = '*' ) {
    $this->prefix = $prefix;

    return $this;
  }

  /**
   * @return array|string
   */
  public function getPrefix() {
    return $this->prefix;
  }

  /**
   * If set, term offsets won't be stores for documents
   * This saves memory, but does not allow exact searches or highlighting.
   * Note: Implies NOHL .
   *
   * @param bool $noOffsetsEnabled
   *
   * @return object Index
   */
	public function setNoOffsetsEnabled( bool $noOffsetsEnabled ) {
		$this->noOffsetsEnabled = $noOffsetsEnabled;

		return $this;
	}

  /**
   * @return bool
   */
  public function isNoOffsetsEnabled() {
    return $this->noOffsetsEnabled;
  }


	/**
	 * @return bool
	 */
	public function isNoFieldsEnabled() {
		return $this->noFieldsEnabled;
	}

  /**
   * @param bool $noFieldsEnabled
   *
   * @return object Index
   */
	public function setNoFieldsEnabled( bool $noFieldsEnabled ) {
		$this->noFieldsEnabled = $noFieldsEnabled;

		return $this;
	}


	/**
	 * @param string $indexName
	 *
	 * @return string
	 */
	public function setIndexName( string $indexName ) {
		$this->indexName = $indexName;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getIndexName() {
		return ! is_string( $this->indexName ) || $this->indexName === '' ? self::class : $this->indexName;
	}

  /**
   * @param array $stopWords Array of custom stop words
   *
   * @return object Index
   */
	public function setStopWords( array $stopWords = NULL ) {
		$this->stopWords = $stopWords;

		return $this;
	}

  /**
   * @return object Index
   */
	public function noStopWords() {
		$this->stopWords = 0;

		return $this;
	}

  /**
   * If set indicates the default language for documents in the index.
   * Default to English.
   * Note: A stemmer is used for the supplied language during indexing. If an unsupported language is sent, the command returns an error. The supported languages are:
   * "arabic", "danish", "dutch", "english", "finnish", "french", "german", "hungarian", "italian", "norwegian", "portuguese", "romanian", "russian", "spanish", "swedish", "tamil", "turkish" "chinese"
   *
   * @param string $language
   *
   * @return object Index
   */
	public function setDefaultLang( string $language ) {
	  $this->language = $language;
	  return $this;
  }

  public function getDefaultLang() {
	  return $this->language;
  }

  /**
   * If set indicates the document field that should be used as the document language.
   *
   * @param string $field
   *
   * @return object Index
   */
  public function setLangField( string $field ) {
	  $this->langField = $field;
	  return $this;
  }

  /**
   * @return bool
   */
  public function hasLangField() {
    return isset( $this->langField );
  }

  /**
   * @return bool
   */
  public function getLangField() {
    return isset( $this->langField );
  }

  /**
   * If set indicates the default score for documents in the index.
   * Default score is 1.0.
   *
   * @param float $score
   *
   * @return object Index
   */
  public function setScore( float $score ) {
    $this->score = $score;
    return $this;
  }

  /**
   * @return float
   */
  public function getScore() {
    return $this->score;
  }

  /**
   * If set indicates the document field that should be used as the document's rank based on the user's ranking.
   * Ranking must be between 0.0 and 1.0. If not set the default score is 1.
   *
   * @param string $scoreField
   *
   * @return object Index
   */
  public function setScoreField( string $scoreField ) {
    $this->scoreField = $scoreField;
    return $this;
  }

  /**
   * @return bool
   */
  public function isScoreFieldSet() {
    return isset( $this->scoreField );
  }

  /**
   * @return string|null
   */
  public function getScoreField() {
    return $this->scoreField;
  }

  /**
   *  If set indicates the document field that should be used as a binary safe payload string to the document,
   * that can be evaluated at query time by a custom scoring function, or retrieved to the client.
   *
   * @param string $payloadField
   *
   * @return object Index
   */
  public function setPayloadField( string $payloadField ) {
    $this->payloadField = $payloadField;
    return $this;
  }

  /**
   * @return bool
   */
  public function isPayloadFieldSet() {
    return isset( $this->payloadField );
  }

  /**
   * @return string|null
   */
  public function getPayloadField() {
    return $this->payloadField;
  }

  /**
   * For efficiency, RediSearch encodes indexes differently if they are created with less than 32 text fields.
   * This option forces RediSearch to encode indexes as if there were more than 32 text fields,
   * which allows you to add additional fields (beyond 32) using FT.ALTER .
   *
   * @param int $maxFields
   *
   * @return object Index
   */
  public function setMaxFields( int $maxFields ) {
    $this->maxFields = $maxFields;
    return $this;
  }

  /**
   * @return bool
   */
  public function isMaxFieldsSet() {
    return isset( $this->maxFields );
  }

  public function getMaxFields() {
    return $this->maxFields;
  }

  /**
   * Marks index as temporary and sets expiration time in seconds
   * @param int $expires Index expiration time in seconds
   *
   * @return object Index
   */
  public function setTemporary( int $expires ) {
    $this->temporary = TRUE;
    $this->expires = $expires;
    return $this;
  }

  /**
   * @return bool
   */
  public function isTemporary() {
    return $this->temporary;
  }

  /**
   * @return int
   */
  public function getExpirationTime() {
    return $this->expires;
  }

  /**
   * Conserves storage space and memory by disabling highlighting support.
   * If set, we do not store corresponding byte offsets for term positions.
   * Note: NOHL is also implied by NOOFFSETS .
   *
   * @param bool $noHighlight
   *
   * @return object Index
   */
  public function setNoHighlight( bool $noHighlight ) {
    $this->noHighlight = $noHighlight;
    return $this;
  }

  /**
   * @return bool
   */
  public function isNoHighlight() {
    return $this->noHighlight;
  }

  /**
   * If set, we avoid saving the term frequencies in the index.
   * This saves memory but does not allow sorting based on the frequencies of a given term within the document.
   *
   * @param bool $noFreqs
   *
   * @return object Index
   */
  public function setNoFreqs( bool $noFreqs ) {
    $this->nofreqs = $noFreqs;
    return $this;
  }

  /**
   * @return bool
   */
  public function isNoFreqsSet() {
    return $this->nofreqs;
  }

  /**
   * If set, we do not scan and index.
   *
   * @param bool $noInitialScan
   *
   * @return object Index
   */
  public function skipInitialScan( bool $noInitialScan ) {
    $this->skipInScan = $noInitialScan;
    return $this;
  }

  /**
   * @return bool
   */
  public function isSkipInitialScanSet() {
    return $this->skipInScan;
  }

  /**
   * Get field from the index info.
   * This is useful when we want to retrieve the score field from an existing index.
   *
   * @param string $field The field key you want to get
   *
   * @return false|mixed
   */
  public function getFieldFromInfo( string $field ) {
    $indexInfo = $this->getInfo();
    if ( empty( $indexInfo) || !is_array( $indexInfo ) ) {
      return FALSE;
    }

    return $indexInfo['index_definition'][ $field ];
  }

  /**
   * @param array $synonymList
   *
   * @return void
   */
	public function synonymAdd( array $synonymList = array() ) {
		if ( empty( $synonymList ) || ! is_array( $synonymList ) ) {
			return;
		}

		foreach ( $synonymList as $key => $synonym ) {
			$synonymGroup   = array_map( 'trim', $synonym );
			$synonymCommand = array_merge( array( $this->getIndexName() ), array( "synonymGroup:$key" ), $synonymGroup );
			$this->client->rawCommand( 'FT.SYNUPDATE', $synonymCommand );
		}
	}

  /**
   * @param
   *
   * @return void
   */
	public function synonymDump() {
		$this->client->rawCommand( 'FT.SYNDUMP', array( $this->getIndexName() ) );
	}

	/**
	 * @return array
	 */
	protected function getFields() {
		$fields = [];
		foreach ( get_object_vars( $this ) as $field ) {
			if ( $field instanceof FieldInterface ) {
				$fields[ $field->getName() ] = $field;
			}
		}

		return $fields;
	}

  /**
   * Add documents to the index.
   *
   * @param Document $document
   *
   * @return object $this
   * @since    0.1.0
   *
   */
	public function add( Document $document ) {
		$properties = $document->getDefinition();

		array_unshift( $properties, $document->getId() );

		if ( $document->getScore() !== NULL && $this->getFieldFromInfo('score_field') ) {
		  $properties = array_merge( $properties, array( $this->getFieldFromInfo('score_field'), $document->getScore() ) );
    }

		if ( $document->getLanguage() !== NULL && $this->getFieldFromInfo('language_field') ) {
		  $properties = array_merge( $properties, array( $this->getFieldFromInfo('language_field'), $document->getLanguage() ) );
    }

		return $this->client->rawCommand( 'HSET', $properties );
	}

  /**
   * @param string $name
   * @param float $weight
   * @param bool $sortable
   * @param bool $noindex
   *
   * @return object Index
   */
	public function addTextField( string $name, float $weight = 1.0, bool $sortable = FALSE, bool $noindex = FALSE ) {
		$this->$name = ( new TextField( $name ) )->setSortable( $sortable )->setNoindex( $noindex )->setWeight( $weight );

		return $this;
	}

  /**
   * @param string $name
   * @param bool $sortable
   * @param bool $noindex
   *
   * @param string $separator
   *
   * @return object Index
   */
	public function addTagField( string $name, bool $sortable = FALSE, bool $noindex = FALSE, string $separator = ',' ) {
		$this->$name = ( new TagField( $name ) )->setSortable( $sortable )->setNoindex( $noindex )->setSeparator( $separator );

		return $this;
	}

  /**
   * @param string $name
   * @param bool $sortable
   * @param bool $noindex
   *
   * @return object Index
   */
	public function addNumericField( string $name, bool $sortable = FALSE, bool $noindex = FALSE ) {
		$this->$name = ( new NumericField( $name ) )->setSortable( $sortable )->setNoindex( $noindex );

		return $this;
	}

  /**
   * @param string $name
   *
   * @param bool $noindex
   *
   * @return object Index
   */
	public function addGeoField( string $name, bool $noindex = FALSE ) {
		$this->$name = ( new GeoField( $name ) )->setNoindex( $noindex );

		return $this;
	}

  /**
   * Delete post from index.
   *
   * @param string|null $id
   *
   * @return object $this
   * @since    0.1.0
   */
	public function delete( string $id = NULL ) {
		if ( $id === NULL ) {
			return NULL;
		}

		$command = array( $this->indexName, $id );
		$this->client->rawCommand( 'DEL', $command );

		return $this;
	}

  /**
   * Write entire redisearch index to the disk to persist it.
   *
   * @return object Index
   * @since    0.1.0
   */
	public function writeToDisk() {
		return $this->client->rawCommand( 'SAVE', [] );
	}

  /**
   * Returns information and statistics on the index.
   *
   * @param string|null $indexName
   *
   * @return array
   */
	public function getInfo( string $indexName = NULL ) {
    $indexInfo = $this->client->rawCommand( 'FT.INFO', array( empty( $indexName ) ? $this->indexName : $indexName ) );

		if ( empty( $indexInfo ) || !is_array( $indexInfo ) ) {
		  return NULL;
    }
    array_walk_recursive( $indexInfo, function(&$item, $key) {
      $item = (string) $item;
    } );

		return $this->normalizeInfoArray( $indexInfo );
	}

  /**
   * @param array $redisArray
   *
   * @return array
   */
  private function normalizeInfoArray( array $redisArray ) {
    $newArray = array();
    for ( $i = 0; $i < count( $redisArray ); $i += 2 ) {
      if ( $redisArray[$i] === 'fields' ) {
        foreach ( $redisArray[ $i + 1 ] as $field ) {
          $fieldName = $field[0];
          array_shift( $field );
          $newArray[ $redisArray[ $i ] ][ $fieldName ] = $this->normalizeInfoArray( $field );
        }

      } elseif ( $redisArray[$i] === 'prefixes' ) {
        foreach ( $redisArray[$i + 1] as $field ) {
          $newArray[ $redisArray[$i] ][] = $field;
        }

      } elseif ( (gettype( $redisArray[$i] ) === 'string' && gettype( $redisArray[$i + 1] ) === 'string' ) ||
                 ( gettype( $redisArray[$i + 1] ) === 'array' && empty( $redisArray[$i + 1] ) )
      ) {
        $newArray[ $redisArray[$i] ] = $redisArray[$i + 1];

      } elseif ( gettype( $redisArray[$i + 1] ) === 'array' && !empty( $redisArray[$i + 1] ) ) {
        $newArray[ $redisArray[$i] ] = $this->normalizeInfoArray( $redisArray[$i + 1] );
      }
    }
    return $newArray;
  }

}
