<?php

namespace FKRediSearch\Fields;

class TextField extends AbstractField {
  use Sortable;
  use Noindex;

  protected $weight = 1.0;
  protected $noStem = false;

  public function getType() {
    return 'TEXT';
  }

  public function getWeight() {
    return $this->weight;
  }

  public function setWeight( $weight ) {
    $this->weight = $weight;
    return $this;
  }

  public function isNoStem() {
    return $this->noStem;
  }

  public function setNoStem( $noStem ) {
    $this->noStem = $noStem;
    return $this;
  }

  public function getDefinition() {
    $properties = parent::getDefinition();
    if ($this->isNoStem()) {
      $properties[] = 'NOSTEM';
    }
    
    $properties[] = 'WEIGHT';
    $properties[] = $this->getWeight();
    
    if ( $this->isSortable() ) {
      $properties[] = 'SORTABLE';
    }

    if ( $this->isNoindex() ) {
      $properties[] = 'NOINDEX';
    }
    
    return $properties;
  }
  }
