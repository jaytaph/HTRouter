<?php

namespace HTRouter;

/**
 * This is a sortakinda simulation of Apache's request_req structure
 */
class Request {

    /**
     * @var array All errors that resulted in this request
     */
    protected $_errors = array();

    /**
     * @var null|HTRouter\Request The parent request if this request is a subrequest
     */
    protected $_parentRequest = null;

    /**
     * @var \HTRouter\VarContainer Configuration storage for directive and modules to set|get CONFIG items only!
     */
    public $config;



    /**
     * Create new request, with a link to the router, and if needed, as a subrequest for another request.
     *
     * @param \HTRouter $router
     * @param null $parentRequest The request for which this request is a subrequest for, or null when it's the main request.
     */
    function __construct(\HTRouter $router, $parentRequest = null) {
        //$this->_router = $router;

        // Set parent request, if this request is a subrequest
        $this->_parentRequest = $parentRequest;

        $this->config = new \HTRouter\VarContainer();
    }

//    // @TODO: If we need this, we are in trouble. Everything should be taken from the request, not from the router!
//    /**
//     * Returns the main router (application) attached to this request
//     *
//     * @return \HTRouter
//     */
//    function getRouter() {
//        return $this->_router;
//    }

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

    /**
     * Returns the parent request, or NULL when it's the main request
     * @return null
     */
    function getParentRequest() {
        if ($this->_parentRequest) {
            return $this->_parentRequest;
        }
        return null;
    }



//
//
//    function setApiVersion($version) {
//        $this->config->setApiVersion($version);
//    }
//    function getAuthenticatedUser() {
//        return $this->config->getAuthenticatedUser();
//    }
//    function getPathInfo() {
//        return $this->config->getPathInfo();
//    }
//    function getAuthType() {
//        return $this->config->getAuthType();
//    }
//    function getApiVersion() {
//        return $this->config->getApiVersion();
//    }
//    function getTheRequest() {
//        return $this->config->getTheRequest();
//    }
//    function getHttps() {
//        return $this->config->getHttps();
//    }
//    function setHttps($arg) {
//        return $this->config->setHttps($arg);
//    }
//
//
//    function getEnvironment() {
//        if (! isset ($this->_vars['environment'])) {
//            $this->_vars['environment'] = false;
//        }
//        return $this->_vars['environment'];
//    }
//
//
//
//
//
//
//    /**
//     * @return array
//     */
//    function getHeaders() {
//        return apache_request_headers();
//    }
//
//    /**
//     * @param $key
//     * @param $val
//     */
//    function appendEnvironment($key, $val) {
//        if (! isset($this->_vars['environment'])) {
//            $this->_vars['environment'] = array();
//        }
//        $this->_vars['environment'][$key] = $val;
//    }
//
//    /**
//     * @param $key
//     */
//    function removeEnvironment($key) {
//        if (isset($this->_vars['environment'])) {
//            unset($this->_vars['environment'][$key]);
//        }
//    }
//
//
//    /**
//     * @return mixed
//     */
//    function getIp() {
//        return $_SERVER['REMOTE_ADDR'];
//    }
//
//    /**
//     * @return mixed
//     */
//    function getDocumentRoot() {
//        return $_SERVER['DOCUMENT_ROOT'];
//    }
//
//
//    function getServerVar($item) {
//        $item = strtoupper($item);
//
//        if (isset ($_SERVER[$item])) {
//            return $_SERVER[$item];
//        }
//        return "";
//    }
//
//
//    function isHttps() {
//        return ($this->getHttps() === true);
//    }


    /**
     * Get or set errors that might have occurred.
     *
     * @param $error
     */
    function logError($error) {
        $this->_errors[] = $error;
    }
    function getErrors() {
        return $this->_errors;
    }



    /**********
     * THE METHODS BELOW ARE TAKEN FROM REQUEST_REC
     **********/

    protected $_protocol;
    protected $_hostname;
    protected $_method;
    protected $_status;
    protected $_inHeaders;
    protected $_outHeaders;
    protected $_contentType;
    protected $_contentEncoding;
    protected $_contentLanguage;
    protected $_unparsedUri;
    protected $_uri;
    protected $_filename;
    protected $_pathInfo;
    protected $_args;
    protected $_perDirConfig;
    protected $_requestConfig;
    protected $_user;
    protected $_authType;
    protected $_server;


    public function setArgs($args)
    {
        $this->_args = $args;
    }

    public function getArgs()
    {
        return $this->_args;
    }

    public function setAuthType($authType)
    {
        $this->_authType = $authType;
    }

    // Module that authenticates Basic | Digest (or the actual Module? makes more sense!)
    public function getAuthType()
    {
        return $this->_authType;
    }

    public function setContentEncoding($contentEncoding)
    {
        $this->_contentEncoding = $contentEncoding;
    }

    public function getContentEncoding()
    {
        return $this->_contentEncoding;
    }

    public function setContentLanguage($contentLanguage)
    {
        $this->_contentLanguage = $contentLanguage;
    }

    public function getContentLanguage()
    {
        return $this->_contentLanguage;
    }

    public function setContentType($contentType)
    {
        $this->_contentType = $contentType;
    }

    public function getContentType()
    {
        return $this->_contentType;
    }

    public function setFilename($filename)
    {
        $this->_filename = $filename;
    }

    public function getFilename()
    {
        return $this->_filename;
    }

    public function setHostname($hostname)
    {
        $this->_hostname = $hostname;
    }

    // Hostname as found on HTTP/1.1 / \n Host: <virtualhost>
    public function getHostname()
    {
        return $this->_hostname;
    }

    public function appendInHeaders($inHeaders)
    {
        $this->_inHeaders = $inHeaders;
    }

    // Returns INPUT headers
    public function getInHeaders()
    {
        return $this->_inHeaders;
    }

    public function setMethod($method)
    {
        $this->_method = $method;
    }

    // PUT, POST, HEAD etc...
    public function getMethod()
    {
        return $this->_method;
    }

    public function appendOutHeaders($outHeaders)
    {
        $this->_outHeaders = $outHeaders;
    }

    // Returns OUTPUT headers
    public function getOutHeaders()
    {
        return $this->_outHeaders;
    }

    public function setPathInfo($pathInfo)
    {
        $this->_pathInfo = $pathInfo;
    }

    public function getPathInfo()
    {
        return $this->_pathInfo;
    }

    public function setPerDirConfig($perDirConfig)
    {
        $this->_perDirConfig = $perDirConfig;
    }

    public function getPerDirConfig()
    {
        return $this->_perDirConfig;
    }

    public function setProtocol($protocol)
    {
        $this->_protocol = $protocol;
    }

    // Returns HTTP/0.9 HTTP/1.0 HTTP/1.1 etc
    public function getProtocol()
    {
        return $this->_protocol;
    }

    public function setRequestConfig($requestConfig)
    {
        $this->_requestConfig = $requestConfig;
    }

    public function getRequestConfig()
    {
        return $this->_requestConfig;
    }

    public function setServer($server)
    {
        $this->_server = $server;
    }

    public function getServer()
    {
        return $this->_server;
    }

    public function setStatus($status)
    {
        $this->_status = $status;
    }

    public function getStatus()
    {
        return $this->_status;
    }

    public function setUnparsedUri($unparsedUri)
    {
        $this->_unparsedUri = $unparsedUri;
    }

    public function getUnparsedUri()
    {
        return $this->_unparsedUri;
    }

    public function setUri($uri)
    {
        $this->_uri = $uri;
    }

    public function getUri()
    {
        return $this->_uri;
    }

    public function setUser($user)
    {
        $this->_user = $user;
    }

    public function getUser()
    {
        // Get the authenticated user name, or NULL when not authenticated
        return $this->_user;
    }

    // Returns status line (200 => OK, 404 => Not Found etc)
    function getStatusLine() {
        $utils = new \HTRouter\Utils();
        return $utils->getStatusLine($this->getStatus());
    }

}
