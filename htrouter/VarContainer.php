<?php

namespace HTRouter;

class VarContainer implements \IteratorAggregate {
    protected $_vars = array();

    /**
     * Return array iterator of all variables inside this container
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->_vars);
    }

    // This will append to a new or existing array.
    public function append($name, $value, $value2 = null) {
        $name = strtolower($name);
        if (! isset($this->_vars[$name]) || ! is_array($this->_vars[$name])) {
            $this->_vars[$name] = array();
        }

        if ($value2 != null) {
            $this->_vars[$name][$value] = $value2;
        } else {
            $this->_vars[$name][] = $value;
        }
    }

    // Set variable
    public function set($name, $value) {
        $name = strtolower($name);
        $this->_vars[$name] = $value;
    }

    /**
     * @param $name
     * @param mixed $default
     * @return mixed
     */
    public function get($name, $default = false) {
        $name = strtolower($name);
        if (! isset($this->_vars[$name])) {
            return $default;
        }
        return $this->_vars[$name];
    }

    // Unset variable
    public function clear($name) {
        $name = strtolower($name);
        if (isset($this->_vars[$name])) {
            unset($this->_vars[$name]);
        }
    }

}
