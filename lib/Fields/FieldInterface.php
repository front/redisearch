<?php

namespace FKRediSearch\RediSearch\Fields;

interface FieldInterface {
  public function getDefinition();
  public function getType();
  public function getName();
  public function getValue();
  public function setValue($value);
}
