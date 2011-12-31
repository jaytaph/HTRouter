<?php

namespace HTRouter;

/**
 * This is a sortakinda simulation of Apache's request_req structure
 */
class Request {
    /**
     * @var null|HTRouter\Request The parent request if this request is a subRequest
     */
    protected $_parentRequest = null;

    /**
     * @var \HTRouter\VarContainer Configuration storage for directive and modules to set|get CONFIG items only!
     */
    public $config;


    /**
     * Create new request, with a link to the router, and if needed, as a subRequest for another request.
     *
     * @param \HTRouter $router
     * @param null $parentRequest The request for which this request is a subRequest for, or null when it's the main request.
     */
    function __construct($parentRequest = null) {
        // Set parent request, if this request is a sub-request
        $this->_parentRequest = $parentRequest;

        $this->config = new \HTRouter\VarContainer();
    }


    /**
     * Returns true when this request is the main request (first request, not a subRequest)
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
     * Returns the main request, independent if this request is a subRequest or not.
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


    /**********
     * THE METHODS BELOW ARE TAKEN FROM REQUEST_REC
     **********/

    protected $_protocol;
    protected $_hostname;
    protected $_method;
    protected $_status;
    protected $_inHeaders = array();
    protected $_outHeaders = array();
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
    protected $_authDigest = "";
    protected $_authUser = "";
    protected $_authPass = "";
    protected $_authType = null;
    protected $_server;
    protected $_queryString;
    protected $_notes;

    // additional items
    protected $_documentRoot;
    protected $_mainConfig;


    public function setArgs($args)
    {
        // Query string
        $this->_args = $args;
    }

    public function getArgs()
    {
        return $this->_args;
    }

//    public function setAuthType(\HTRouter\AuthModule $authType = null)
//    {
//        $this->_authType = $authType;
//    }

    // Module that authenticates: Basic | Digest
    public function getAuthType()
    {
        return $this->config->getAuthType();
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

    public function appendInHeaders($key, $value)
    {
        $this->_inHeaders[$key] = $value;
    }

    // Returns INPUT headers
    public function getInHeaders($header = null)
    {
        if ($header) {
            return isset($this->_inHeaders[$header]) ? $this->_inHeaders[$header] : null;
        }
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

    public function appendOutHeaders($key, $value)
    {
        $this->_outHeaders[$key] = $value;
    }

    // Returns OUTPUT headers
    public function getOutHeaders($header = null)
    {
        if ($header) {
            return isset($this->_outHeaders[$header]) ? $this->_outHeaders[$header] : null;
        }
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

    /**********
     * OTHER USEFUL METHODS
     **********/

    // Returns status line (200 => OK, 404 => Not Found etc)
    function getStatusLine() {
        $utils = new \HTRouter\Utils();
        return $utils->getStatusLine($this->getStatus());
    }

    public function setDocumentRoot($documentRoot)
    {
        $this->_documentRoot = $documentRoot;
    }

    public function getDocumentRoot()
    {
        return $this->_documentRoot;
    }

    public function setAuthDigest($authDigest)
    {
        $this->_authDigest = $authDigest;
    }

    public function getAuthDigest()
    {
        return $this->_authDigest;
    }

    public function setAuthPass($authPass)
    {
        $this->_authPass = $authPass;
    }

    public function getAuthPass()
    {
        return $this->_authPass;
    }

    public function setAuthUser($authUser)
    {
        $this->_authUser = $authUser;
    }

    public function getAuthUser()
    {
        return $this->_authUser;
    }

    public function setQueryString($queryString)
    {
        $this->_queryString = $queryString;
    }

    public function getQueryString()
    {
        return $this->_queryString;
    }

//    public function setMainConfig($mainConfig)
//    {
//        $this->_mainConfig = $mainConfig;
//    }
//
//    public function getMainConfig()
//    {
//        return $this->_mainConfig;
//    }

//    public function mergeNotes(\HTRouter\Request $subrequest) {
//        $this->_notes = array_merge($this->_notes, $subrequest->getNotes());
//    }
//    public function mergeHeadersOut(\HTRouter\Request $subrequest) {
//        $this->_outHeaders = array_merge($this->_outHeaders, $subrequest->getOutHeaders());
//    }
//    public function mergeErrHeadersOut(\HTRouter\Request $subrequest) {
//        // @TODO FILL THIS
//    }
//
//    public function setNotes($notes)
//    {
//        $this->_notes = $notes;
//    }
//
//    public function getNotes()
//    {
//        return $this->_notes;
//    }

    public function merge(\HTRouter\Request $subRequest) {
        $tmp = $subRequest->getFilename();
        $this->setFilename($tmp);
    }

}
