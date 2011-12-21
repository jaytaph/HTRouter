<?php

class HTRequest {
    protected $_vars = array();

    // @TODO: It's a kind of magic...

    function __call($name, $arguments)
    {
        if (substr($name, 0, 6) == "append") {
            // This will append to a new or existing array.
            $name = strtolower(substr($name, 6));
            if (! isset($this->_vars[$name]) || ! is_array($this->_vars[$name])) {
                $this->_vars[$name] = array();
            }
            $this->_vars[$name][] = $arguments[0];
            return;
        }
        if (substr($name, 0, 3) == "set") {
            $name = strtolower(substr($name, 3));
            $this->_vars[$name] = $arguments[0];
            return;
        }

        if (substr($name, 0, 3) == "get") {
            $name = strtolower(substr($name, 3));
            if (! isset($this->_vars[$name])) $this->_vars[$name] = array();
            return $this->_vars[$name];
        }
    }


    function appendEnvironment($key, $val) {
        if (! isset($this->_vars['environment'])) {
            $this->_vars['environment'] = array();
        }
        $this->_vars['environment'][$key] = $val;
    }

}
