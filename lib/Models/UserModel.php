<?php

namespace Tritrics\Tric\v1\Models;

use Tritrics\Tric\v1\Data\Collection;

/**
 * Model for Kirby's user object
 */
class UserModel extends BaseModel
{
  /**
   * Marker if this model has child fields.
   * 
   * @var bool
   */
  protected $hasChildFields = true;

  /**
   * Get additional field data (besides type and value)
   * Method called by setModelData()
   */
  protected function getProperties (): Collection
  {
    $meta = new Collection();
    $meta->add('id', md5($this->model->id()));

    $res = new Collection();
    $res->add('meta', $meta);
    return $res;
  }
}