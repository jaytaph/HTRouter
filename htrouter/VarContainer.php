<?php

namespace HTRouter;

class VarContainer {
    protected $_vars = array();

    // @TODO: It's a kind of magic...

    function __construct() {
    }


    // the get* functions allows a parameter. This is the return value when the actual item is not found.
    function __call($name, $arguments)
    {
        if (substr($name, 0, 6) == "append") {
            // This will append to a new or existing array.
            $name = strtolower(substr($name, 6));
            if (! isset($this->_vars[$name]) || ! is_array($this->_vars[$name])) {
                $this->_vars[$name] = array();
            }
            $this->_vars[$name][] = $arguments[0];
            return null;
        }
        if (substr($name, 0, 3) == "set") {
            // Set variable
            $name = strtolower(substr($name, 3));
            $this->_vars[$name] = $arguments[0];
            return null;
        }

        if (substr($name, 0, 3) == "get") {
            // Get variable
            $name = strtolower(substr($name, 3));
            if (! isset($this->_vars[$name])) {
                if (! isset($arguments[0])) {
                    $arguments[0] = false;
                }
                return $arguments[0];
            }
            return $this->_vars[$name];
        }

        if (substr($name, 0, 5) == "unset") {
            // Unset variable
            $name = strtolower(substr($name, 5));
            if (isset($this->_vars[$name])) {
                unset($this->_vars[$name]);
            }
            return null;
        }
    }
}
