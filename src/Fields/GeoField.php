<?php

namespace FKRediSearch\Fields;

class GeoField extends AbstractField {
  use Noindex;
  
  public function getType() {
    return 'GEO';
  }
  
  public function getDefinition() {
    $properties = parent::getDefinition();
    
    if ( $this->isNoindex() ) {
      $properties[] = 'NOINDEX';
    }
    
    return $properties;
  }
}
