<?php

namespace FKRediSearch;

class Document {

  /**
   * @var mixed|string
   */
  protected $id;

  /**
   * @var float
   */
  protected $score = NULL;

  /**
   * @var bool
   */
  protected $replace = FALSE;

  /**
   * @var array
   */
  protected $fields = array();

  /**
   * @var string
   */
  protected $language = NULL;


  public function __construct( string $id = null ) {
    $this->id = $id ?? uniqid(true);
  }

  /**
   * Get set fields as a one dimensional array
   *
   * @return array
   */
  public function getDefinition() {
    $properties = array();

    foreach ( $this->getFields() as $name => $value) {
      if ( isset( $value ) ) {
        $properties[] = $name;
        $properties[] = $value;
      }
    }

    return $properties;
  }

  /**
   * Setting associative array of field => values
   *
   * @param array $fields
   *
   * @return object Document
   */
  public function setFields( array $fields = array() ) {
    $this->fields = $fields;
    return $this;
  }

  /**
   * Get fields
   *
   * @return array
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Get document ID
   * @return string
   */
  public function getId()  {
    return $this->id;
  }

  /**
   * Set document ID
   * @param string $id
   *
   * @return object Document
   */
  public function setId( string $id ) {
    $this->id = $id;
    return $this;
  }

  /**
   * Get document score
   *
   * @return float|null
   */
  public function getScore() {
    return $this->score;
  }

  /**
   * Set score for the document.
   *
   * @param float $score
   *
   * @return object Document
   */
  public function setScore( float $score ) {
    if ($score < 0.0 || $score > 1.0) {
      $score = 1.0;
    }

    $this->score = $score;
    return $this;
  }

  public function isReplace() {
    return $this->replace;
  }

  public function setReplace( bool $replace ) {
    $this->replace = $replace;
    return $this;
  }

  /**
   * Return document language if set specifically
   *
   * @return string|null
   */
  public function getLanguage() {
    return $this->language;
  }

  /**
   * Return document specific language if set
   * @param string|null $language
   *
   * @return object Document
   */
  public function setLanguage( string $language ) {
    $this->language = $language;
    return $this;
  }

}
