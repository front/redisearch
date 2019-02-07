<?php

namespace FKRediSearch\Fields;

trait Noindex {
  protected $isNoindex = false;

  public function isNoindex() {
    return $this->isNoindex;
  }

  public function setNoindex( $noindex ) {
    $this->isNoindex = $noindex;
    return $this;
  }
}
