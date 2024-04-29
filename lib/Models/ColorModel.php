<?php

namespace Tritrics\Tric\v1\Models;

use Tritrics\Tric\v1\Data\Collection;

/**
 * Model for Kirby's fields: color
 */
class ColorModel extends BaseModel
{
  /**
   * Get additional field data (besides type and value)
   * Method called by setModelData()
   */
  protected function getProperties(): Collection
  {
    $res = new Collection();
    if ($this->blueprint->has('format')) {
      $res->add('format', $this->blueprint->node('format')->get());
    } else {
      $res->add('format', 'hex');
    }
    if ($this->blueprint->has('format')) {
      $res->add('alpha', $this->blueprint->node('alpha')->get());
    } else {
      $res->add('alpha', false);
    }
    return $res;
  }

  /**
   * Get the value of model as it's returned in response.
   */
  protected function getValue(): string
  {
    return (string) $this->model->value();
  }
}
