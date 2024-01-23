<?php

namespace Tritrics\AflevereApi\v1\Models;

use Tritrics\AflevereApi\v1\Data\Collection;
use Tritrics\AflevereApi\v1\Data\Model;

/**
 * Model for Kirby's fields: color
 */
class ColorModel extends Model
{
  /**
   * Get additional field data (besides type and value)
   * Method called by setModelData()
   * 
   * @return Collection 
   */
  protected function getProperties()
  {
    $res = new Collection();
    $meta = $res->add('meta');
    if ($this->blueprint->has('format')) {
      $meta->add('format', $this->blueprint->node('format')->get());
    } else {
      $meta->add('format', 'hex');
    }
    if ($this->blueprint->has('format')) {
      $meta->add('alpha', $this->blueprint->node('alpha')->get());
    } else {
      $meta->add('alpha', false);
    }
    return $res;
  }

  /**
   * Get the value of model as it's returned in response.
   * Mandatory method.
   * 
   * @return String
   */
  protected function getValue()
  {
    return $this->model->value();
  }
}
