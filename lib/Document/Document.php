<?php

namespace FKRediSearch\RediSearch;

class Document {
  protected $id;
  protected $score = 1.0;
  protected $noSave = false;
  protected $replace = false;
  protected $fields = array();
  protected $payload;
  protected $language;

  public function __construct( $id = null ) {
    $this->id = $id ?? uniqid(true);
  }

  public function getDefinition() {
    $properties = [
      $this->getId(),
      $this->getScore(),
    ];

    if ( $this->isNoSave() ) {
      $properties[] = 'NOSAVE';
    }

    if ( $this->isReplace() ) {
      $properties[] = 'REPLACE';
    }

    if ( !is_null( $this->getLanguage() ) ) {
      $properties[] = 'LANGUAGE';
      $properties[] = $this->getLanguage();
    }

    if ( !is_null( $this->getPayload() ) ) {
      $properties[] = 'PAYLOAD';
      $properties[] = $this->getPayload();
    }

    $properties[] = 'FIELDS';

    foreach ( $this->getFields() as $name => $value) {
      if ( isset( $value ) ) {
        $properties[] = $name;
        $properties[] = $value;
      }
    }

    return $properties;
  }

  public function setFields( $fields = array() ) {
    $this->fields = $fields;
    return $this;
  }

  public function getFields() {
    return $this->fields;
  }

  public function getId()  {
    return $this->id;
  }

  public function setId( $id ) {
    $this->id = $id;
    return $this;
  }

  public function getScore() {
    return $this->score;
  }

  public function setScore( $score ) {
    if ($score < 0.0 || $score > 1.0) {
      $score = 1.0;
    }

    $this->score = $score;
    return $this;
  }

  public function isNoSave() {
    return $this->noSave;
  }

  public function setNoSave( $noSave ) {
    $this->noSave = $noSave;
    return $this;
  }

  public function isReplace() {
    return $this->replace;
  }

  public function setReplace( $replace ) {
    $this->replace = $replace;
    return $this;
  }

  public function getPayload() {
    return $this->payload;
  }

  public function setPayload( $payload ) {
    $this->payload = $payload;
    return $this;
  }

  public function getLanguage() {
    return $this->language;
  }

  public function setLanguage( $language ) {
    $this->language = $language;
    return $this;
  }

}
