<?php

class DIContainer {
    /**
     * @var array
     */
    protected $_values = array();

    /**
     * @param $key
     * @param $value
     */
    function __set($key, $value) {
        $this->_values[$key] = $value;
    }

    /**
     * @param $key
     * @return mixed
     */
    function __get($key) {
        if (! isset($this->_values[$key])) {
            throw new \InvalidArgumentException("Value $key is not defined.");
        }

        if (is_callable($this->_values[$key])) {
            $method = (string)$this->_values[$key];
            return $method($this);
        }

        return $this->_values[$key];
    }

    /**
     * @param $callable
     * @return closure
     */
    function asShared($callable) {
        return function($c) use ($callable) {
            static $object;

            if (is_null($object)) {
                $object = $callable($c);
            }
            return $object;
        };
    }
}
