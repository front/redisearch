<?php

namespace FKRediSearch\Fields;

abstract class AbstractField implements FieldInterface {
  protected $name;
  protected $value;

  public function __construct( $name, $value = null ) {
    $this->name = $name;
    $this->value = $value;
  }

  public function getName() {
    return $this->name;
  }

  public function getValue() {
    return $this->value;
  }

  public function setValue( $value ) {
    $this->value = $value;
    return $this;
  }
  
  public function getDefinition() {
    return [
      $this->getName(),
      $this->getType(),
    ];
  }
}
