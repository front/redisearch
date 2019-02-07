<?php

namespace FKRediSearch\Fields;

class NumericField extends AbstractField {
  use Sortable;
  use Noindex;

  public function getType() {
    return 'NUMERIC';
  }

  public function getDefinition() {
    $properties = parent::getDefinition();
    
    if ( $this->isSortable() ) {
      $properties[] = 'SORTABLE';
    }
    
    if ( $this->isNoindex() ) {
      $properties[] = 'NOINDEX';
    }
    
    return $properties;
  }
}
