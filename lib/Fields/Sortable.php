<?php

namespace FKRediSearch\RediSearch\Fields;

trait Sortable {
  protected $isSortable = false;

  public function isSortable() {
    return $this->isSortable;
  }

  public function setSortable( $sortable ) {
    $this->isSortable = $sortable;
    return $this;
  }
}
