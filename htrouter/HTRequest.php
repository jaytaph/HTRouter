<?php

class HTRequest {
    protected $_vars = array();


    // @TODO: It's a kind of magic...
    function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == "set") {
            $name = strtolower(substr($name, 3));
            $this->_vars[$name] = $arguments[0];
            return;
        }

        if (substr($name, 0, 3) == "get") {
            $name = strtolower(substr($name, 3));
            return $this->_vars[$name];
        }
    }

}
