<?php

namespace Tritrics\AflevereApi\v1\Data;

use ArrayIterator;
use Exception;
use IteratorAggregate;

/**
 * Handles array-like data and wraps it with handy functions.
 */
class Collection implements IteratorAggregate
{
  /**
   * The wrapped data
   * 
   * @var Array
   */
  protected $data = [];

  /**
   * @param Mixed optionally give initial data
   */
  public function __construct ()
  {
    if (func_num_args() > 0) {
      $this->set(func_get_arg(0));
    }
  }

  /**
   * Delegate function calls to $data.
   * 
   * @param Mixed $method 
   * @param Mixed $args 
   * @return Mixed 
   */
  final public function __call ($method, $args)
  {
    return call_user_func_array([$this->data, $method], $args);
  }

  /**
   * Make this class an iterator class.
   * 
   * @return ArrayIterator 
   */
  final public function getIterator() : ArrayIterator
  {
    return new ArrayIterator($this->data);
  }

  /**
   * Find a (sub-)node with given key(s).
   * 
   * @param Array $keys
   * @return Collection
   */
  final public function node (...$keys)
  {
    $key = array_shift($keys);
    if ($this->has($key)) {
      if (count($keys) > 0) {
        if ($this->data[$key] instanceof Collection) {
          return call_user_func_array(array($this->data[$key], 'node'), $keys);
        }
      } else {
        return $this->data[$key];
      }
    }
    return new Collection();
  }

  /**
   * Set the value of this node. Adds new Collections if given value is an array.
   * 
   * @param Mixed $mixed
   */
  final public function set ($mixed)
  {
    if (is_array($mixed)) {
      if ( ! $this->isCollection()) {
        $this->data = [];
      }
      foreach ($mixed as $key => $value) {
        $this->add($key, $value);
      }
    } else {
      $this->data = $mixed;
    }
  }

  /**
   * Adds a new node to array, optionally set value with second argument.
   * Method fails if data isn't an array. Giving an array with keys will
   * add nesting nodes.
   * 
   * @param String|integer|array $keys
   * @param Mixed the value for the new node
   * @return Collection
   */
  final public function add ($keys /*, mixed */)
  {
    // $keys is an array of keys -> nested adding
    $key = is_array($keys) ? array_shift($keys) : $keys;
    if ( ! $this->isCollection() || ! $this->isKey($key)) {
      return;
    }

    // more keys left, so create node and call this function again with
    // the rest of the keys
    if (is_array($keys) && count($keys)) {
      if ( ! isset($this->data[$key]) || ! $this->data[$key] instanceof Collection) {
        $this->data[$key] = new Collection();
      }
      $args = func_get_args();
      $args[0] = $keys;
      return call_user_func_array([ $this->data[$key], "add" ], $args);
    }

    // finally adding, depending if $value is given or not
    if (func_num_args() === 2) {
      $value = func_get_arg(1);
      if ($value instanceof Collection) {
        $this->data[$key] = $value;
      } else {
        $this->data[$key] = new Collection($value);
      }
    } else {
      $this->data[$key] = new Collection();
    }
    return $this->data[$key];
  }

  /**
   * Same like add() + set(), but for numerical index.
   * 
   * @param Mixed $mixed the value of the new node
   * @return Collection
  */
  final public function push ($mixed)
  {
    if ( ! $this->isCollection()) {
      $this->data = [];
    }
    $this->data[] = new Collection($mixed);
    return end($this->data);
  }

  /**
   * Merge a Collection into $data (not a deep merge, simply top-level keys)
   * 
   * @param Collection $Collection
   */
  final public function merge (Collection $data)
  {
    if ($this->isCollection() && $data->isCollection()) {
      foreach ($data as $key => $value) {
        $this->data[$key] = $value;
      }
    }
  }

  final public function first()
  {
    return $this->node(0);
  }

  /**
   * Get value from $data
   * 
   * @return Mixed
   */
  final public function get () : array|string|int|float|null
  {
    // $data is an array
    if ($this->isCollection()) {
      $childs = [];
      foreach ($this->data as $key => $value) {
        $childs[$key] = $value->get();
      }
      return $childs;
    }
    
    // single node, but object
    elseif (is_object($this->data) && method_exists($this->data, 'get')) {
      return $this->data->get();
    }
    
    // endpoint, single value
    else {
      return $this->data;
    }
  }

  /**
   * Check if a key in $data exists.
   * 
   * @param String|integer $key
   * @return Booleanean
   */
  final public function has ($key)
  {
    return $this->isCollection() && $this->isKey($key) && isset($this->data[$key]);
  }

  /**
   * Unset/delete a subnode of $data.
   * 
   * @param Mixed $key 
   * @return Void
   */
  final public function unset ($key)
  {
    if ($this->isCollection() && isset($this->data[$key])) {
      unset ($this->data[$key]);
    }
  }

  /**
   * Compare $data with a given value.
   * 
   * @param Mixed $compare 
   * @return Boolean 
   */
  final public function is ($compare)
  {
    if (!$this->isCollection()) {
      return $this->data === $compare;
    }
    return false;
  }

  /**
   * Return the keys of $data.
   * 
   * @return int[]|string[]|null 
   */
  final public function keys ()
  {
    $data = $this->get();
    if(is_array($data)) {
      return array_keys($data);
    }
    return null;
  }

  /**
   * Check if $data is empty.
   * 
   * @return Boolean 
   */
  final public function isEmpty ()
  {
    return $this->data === [];
  }

  /**
   * Check, if $data is an array
   * 
   * @return Boolean
   */
  final public function isCollection ()
  {
    return is_array($this->data);
  }

  /**
   * Check, if $data is a numeric array
   * 
   * @return Boolean
   */
  final public function isNumeric ()
  {
    if (!$this->isCollection()) {
      return false;
    }
    return array_keys($this->data) === range(0, count($this->data) - 1);
  }

  /**
   * Get count of $data, if it's an array.
   * 
   * @return Boolean
   */
  final public function count ()
  {
    if ($this->isCollection()) {
      return count($this->data);
    }
    return 0;
  }

  /**
   * Checks, if the given $key valid (string or integer).
   * 
   * @param Mixed $key
   * @return Boolean
   */
  private function isKey ($check) {
    return ((is_string($check) && strlen($check) > 0) || (is_int($check) && $check >= 0));
  }
}