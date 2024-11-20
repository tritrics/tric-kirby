<?php

namespace Tritrics\Ahoi\v1\Models;

/**
 * Default model for Kirby's blocks
 */
class BlockModel extends BaseFieldsModel
{
  /**
   */
  public function __construct()
  {
    parent::__construct(...func_get_args());
    $this->setData();
  }

  /**
   * Set model data.
   */
  private function setData(): void
  {
    $this->add('type', 'block');
    $this->add('block', $this->model->type());
    
    // fields
    if ($this->fields->count() > 0) {
      $this->add('fields', $this->fields);
    }
  }
}
