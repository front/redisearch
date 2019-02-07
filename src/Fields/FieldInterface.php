<?php

namespace FKRediSearch\Fields;

interface FieldInterface {
  public function getDefinition();
  public function getType();
  public function getName();
  public function getValue();
  public function setValue($value);
}
