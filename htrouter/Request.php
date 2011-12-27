<?php

namespace HTRouter;

// This is a sortakinda simulation of apache's request_req.
class Request {
    protected $_vars = array();

    protected $_errors = array();

    protected $_parentRequest = null;

    /**
     * @var \HTRouter\VarContainer
     */
    public $vars;



    /**
     * Create new request, with a link to the router, and if needed, as a subrequest for another request.
     *
     * @param \HTRouter $router
     * @param null $parentRequest The request for which this request is a subrequest for, or null when it's the main request.
     */
    function __construct(\HTRouter $router, $parentRequest = null) {
        $this->_router = $router;

        // Set parent request, if this request is a subrequest
        $this->_parentRequest = $parentRequest;

        $this->vars = new \HTRouter\VarContainer();
    }

    function getRouter() {
        return $this->_router;
    }

    /**
     * Returns true when this request is the main request (first request, not a subrequest)
     * @return bool
     */
    function isMainRequest() {
        return ($this->_parentRequest == null);
    }

    /**
     * Returns true when this request is NOT the main request
     *
     * @return bool
     */
    function isSubRequest() {
        return (! $this->isMainRequest());
    }

    /**
     * Returns the main request, independent if this request is a subrequest or not.
     * @return Request
     */
    function getMainRequest() {
        $req = $this;

        // Goto start of the chain (ie the first request)
        while ($req->getParentRequest() != null) {
            $req = $req->getParentRequest();
        }

        return $req;
    }





    function setApiVersion($version) {
        $this->vars->setApiVersion($version);
    }
    function getAuthenticatedUser() {
        return $this->vars->getAuthenticatedUser();
    }
    function getPathInfo() {
        return $this->vars->getPathInfo();
    }
    function getAuthType() {
        return $this->vars->getAuthType();
    }
    function getApiVersion() {
        return $this->vars->getApiVersion();
    }
    function getTheRequest() {
        return $this->vars->getTheRequest();
    }
    function getHttps() {
        return $this->vars->getHttps();
    }
    function setHttps($arg) {
        return $this->vars->setHttps($arg);
    }


    function getEnvironment() {
        if (! isset ($this->_vars['environment'])) {
            $this->_vars['environment'] = false;
        }
        return $this->_vars['environment'];
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
        $item = strtoupper($item);

        if (isset ($_SERVER[$item])) {
            return $_SERVER[$item];
        }
        return "";
    }


    function isHttps() {
        return ($this->getHttps() === true);
    }


    function logError($error) {
        $this->_errors[] = $error;
    }
    function getErrors() {
        return $this->_errors;
    }

}
