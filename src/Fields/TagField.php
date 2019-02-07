<?php

namespace FKRediSearch\Fields;

class TagField extends AbstractField {
  use Sortable;
  use Noindex;

  protected $separator = ',';

  public function getType() {
    return 'TAG';
  }

  public function getSeparator() {
    return $this->separator;
  }

  public function setSeparator( $separator ) {
    $this->separator = $separator;
    return $this;
  }

  public function getDefinition() {
    $properties = parent::getDefinition();

    $properties[] = 'SEPARATOR';
    $properties[] = $this->getSeparator();

    if ( $this->isSortable() ) {
      $properties[] = 'SORTABLE';
    }
    
    if ( $this->isNoindex() ) {
      $properties[] = 'NOINDEX';
    }
    
    return $properties;
  }
}
