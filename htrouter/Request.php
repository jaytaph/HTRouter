<?php

namespace HTRouter;

class Request {
    protected $_vars = array();

    // @TODO: It's a kind of magic...


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

    /**
     * @return array
     */
    function getHeaders() {
        return apache_request_headers();
    }

    /**
     * @param $key
     * @param $val
     */
    function appendEnvironment($key, $val) {
        if (! isset($this->_vars['environment'])) {
            $this->_vars['environment'] = array();
        }
        $this->_vars['environment'][$key] = $val;
    }

    /**
     * @param $key
     */
    function removeEnvironment($key) {
        if (isset($this->_vars['environment'])) {
            unset($this->_vars['environment'][$key]);
        }
    }

    /**
     * @return mixed
     */
    function getIp() {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @return mixed
     */
    function getDocumentRoot() {
        return $_SERVER['DOCUMENT_ROOT'];
    }


    function getServerVar($item) {
        if (isset ($_SERVER[$item])) {
            return $_SERVER[$item];
        }
        return "";
//        print "<pre>: Looking for $item :";
//        print_r ($_SERVER);
//        print "</pre>";
    }

}
