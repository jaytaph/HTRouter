<?php

namespace HTRouter;

class VarContainer implements \IteratorAggregate {
    protected $_vars = array();

    function __construct() {
    }

    /**
     * Return array iterator of all variables inside this container
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->_vars);
    }

    // This will append to a new or existing array.
    public function append($name, $value) {
        $name = strtolower($name);
        if (! isset($this->_vars[$name]) || ! is_array($this->_vars[$name])) {
            $this->_vars[$name] = array();
        }
        $this->_vars[$name][] = $value;
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


    /**
     * Merge another container with this container. We get precedence.
     *
     * @TODO: We should add parameter $mergevars to find out which items needs to be merged.
     *        For instance: $mergeVars = $module->getMergeDirectives()
     *
     * @param VarContainer $container
     */
    public function merge(\HTRouter\VarContainer $container) {
        foreach ($container as $k => $v) {
            if (is_array($v)) {
                if (! isset($this->_vars[$k])) $this->_vars[$k] = array();
                $this->_vars[$k] = array_merge($this->_vars[$k], $v);
            }
            $this->_vars[$k] = $v;
//            if (! isset($this->_vars[$k])) {
//                // Not yet set. Set it.
//                $this->_vars[$k] = $v;
//            }
        }
    }
}
