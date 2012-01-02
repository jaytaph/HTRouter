<?php

namespace HTRouter;

class Processor {
    protected $_container;



    function __construct(\HTRouter\HTDIContainer $container) {
        $this->_container = $container;
    }


    /**
     * @param string $uri
     * @param HTRouter\Request $request
     * @return HTRouter\Request SubRequest
     */
    function obsolete_subRequestLookupUri($uri, \HTRouter\Request $request) {
        $this->getLogger()->log(\HTRouter\Logger::ERRORLEVEL_DEBUG, "subRequestLookupUri($uri)");

        // Save old info
        $oldRequest = $this->_container->getRequest();
        $oldConfig = $this->_container->getConfig();

        // Create a new container
        $subRequest = $this->getRouter()->copyRequest($request);
        $this->_container->setRequest($subRequest);
        $this->_container->setConfig($this->getRouter()->getDefaultConfig());

        $subRequest->setMethod("GET");
        if ($uri[0] != '/') {
            // Relative URI
            $uri = $this->getUri() . $uri;
        }
        $subRequest->setUri($uri);

        if ($this->hasReachedRecursionLimits($subRequest)) {
            $subRequest->setStatus(\HTRouter::STATUS_HTTP_INTERNAL_SERVER_ERROR);
            return $subRequest;
        }

        $status = $this->processRequest($subRequest);
        $subRequest->setStatus($status);

        $this->_container->setRequest($oldRequest);
        $this->_container->setConfig($oldConfig);
        return $subRequest;
    }

    /**
     * Returns true when the number of parent sub requests found have reached a limit
     *
     * @param HTRouter\Request $request The last request in line
     * @return bool true when limit reached, false otherwise
     */
    function hasReachedRecursionLimits(\HTRouter\Request $request) {
        $level = 0;
        while ($request->getParentRequest()) {
            $level++;
            $request = $request->getParentRequest();
        }

        return ($level > \HTrouter::MAX_RECURSION);
    }


    /**
     * The actual processing of a request, this should look somewhat similar to request.c:ap_process_request_internal()
     *
     * @return int status
     */
    function processRequest() {
        $r = $this->_container->getRequest();
        $this->getLogger()->log(\HTRouter\Logger::ERRORLEVEL_DEBUG, "processRequest(".$r->getUri().")");

        $utils = new \HTRouter\Utils();

        // If you are looking for proxy stuff. It's not here to simplify things

        // Remove /. /.. and // from the URI
        $realUri = $utils->getParents($r->getUri());
        $r->setUri($realUri);

        // We don't have a filename yet, try to find the file that corresponds to the URI we need
//        if (! $r->isMainRequest() && ! $r->getFileName()) {
            $status = $this->_locationWalk($r);
            if ($status != \HTRouter::STATUS_OK) {
                return $status;
            }

            $status = $this->getRouter()->runHook(\HTRouter::HOOK_TRANSLATE_NAME, \HTRouter::RUNHOOK_FIRST, $this->_container);
            if ($status != \HTRouter::STATUS_OK) {
                return $this->_declDie($status, "translate", $r);
            }
//        }

        // @TODO: Set per_dir_config to defaults, is this still needed?

        $status = $this->getRouter()->runHook(\HTRouter::HOOK_MAP_TO_STORAGE, \HTRouter::RUNHOOK_FIRST, $this->_container);
        if ($status != \HTRouter::STATUS_OK) {
            return $status;
        }

        // Rerun location walk (@TODO: Find out why)
        if ($status != \HTRouter::STATUS_OK) {
            return $status;
        }

        if ($r->isMainRequest()) {
            $status = $this->getRouter()->runHook(\HTRouter::HOOK_HEADER_PARSER, \HTRouter::RUNHOOK_FIRST, $this->_container);
            if ($status != \HTRouter::STATUS_OK) {
                return $status;
            }
        }

        // We always re-authenticate. Something request.c doesn't do for optimizing. Easy enough to create though.
        $status = $this->_authenticate($r);
        if ($status != \HTRouter::STATUS_OK) {
            return $status;
        }

        $status = $this->getRouter()->runHook(\HTRouter::HOOK_CHECK_TYPE, \HTRouter::RUNHOOK_FIRST, $this->_container);
        if ($status != \HTRouter::STATUS_OK) {
            return $this->_declDie($status, "find types", $r);
        }

        $status = $this->getRouter()->runHook(\HTRouter::HOOK_FIXUPS, \HTRouter::RUNHOOK_ALL, $this->_container);
        if ($status != \HTRouter::STATUS_OK) {
            return $status;
        }

        // If everything is ok. Note that we return 200 OK instead of "OK" since we need to return a code for the
        // router to work with...
        return \HTRouter::STATUS_HTTP_OK;
    }

    /**
     * Do the authentication part of the request processing. It's a bit more complicated as the rest, so
     * we moved it into a separate method.
     *
     * @param HTRouter\Request $request
     * @return int Status
     */
    protected function _authenticate(\HTRouter\Request $request) {
        // Authentication depends on the satisfy flag (defaults to ALL)
        switch ($this->getConfig()->getSatisfy("all")) {
            default :
            case "all" :
                // Both access and authentication must be OK
                $status = $this->getRouter()->runHook(\HTRouter::HOOK_CHECK_ACCESS, \HTRouter::RUNHOOK_ALL, $this->_container);
                if ($status  != \HTRouter::STATUS_OK) {
                    return $this->_declDie($status, "check access", $request);
                }

                // We only do this if there are any "requires". Without requires, we do not need to
                // go into to the authentication process
                if (count($this->getConfig()->getRequire(array())) > 0) {

                    // Check authentication
                    $status = $this->getRouter()->runHook(\HTRouter::HOOK_CHECK_USER_ID, \HTRouter::RUNHOOK_FIRST, $this->_container);
                    if ($request->getAuthType() == null) {
                        $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check user failure", $request);
                    }

                    $status = $this->getRouter()->runHook(\HTRouter::HOOK_CHECK_AUTH, \HTRouter::RUNHOOK_FIRST, $this->_container);
                    if ($request->getAuthType() == null) {
                        return $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check access failure", $request);
                    }
                }
                break;
            case "any" :
                // Either access or authentication must be OK
                $status = $this->getRouter()->runHook(\HTRouter::HOOK_CHECK_ACCESS, \HTRouter::RUNHOOK_ALL, $this->_container);
                if ($status != \HTRouter::STATUS_OK) {

                    // No requires needed
                    if (count($this->getConfig()->getRequire(array())) == 0) {
                        return $this->_declDie($status, "check access", $request);
                    }

                    $status = $this->getRouter()->runHook(\HTRouter::HOOK_CHECK_USER_ID, \HTRouter::RUNHOOK_FIRST, $this->_container);
                    if ($request->getAuthType() == null) {
                        return $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check user failure", $request);
                    }

                    $status = $this->getRouter()->runHook(\HTRouter::HOOK_CHECK_AUTH, \HTRouter::RUNHOOK_FIRST, $this->_container);
                    if ($request->getAuthType() == null) {
                        return $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check access failure", $request);
                    }
                }
                break;
        }

        return \HTRouter::STATUS_OK;
    }

    /**
     * Do a location walk to check if we are still OK on the location (i guess)...
     *
     * @param HTRouter\Request $request
     * @return int Returns OK
     */
    protected function _locationWalk(\HTRouter\Request $request) {
        // Since we don't have any locations, we just return
        return \HTRouter::STATUS_OK;
    }


    /**
     * Logs error and returns 500 code when status has been declined. If the status is a normal HTTP response,
     * it may pass. A simple way to filter out status-codes that are !STATUS_OK.
     *
     * @param int $status The status code to check
     * @param string $str Logstring
     * @param HTRouter\Request $request The request (for logging)
     * @return int status code
     */
    protected function _declDie($status, $str, \HTRouter\Request $request) {
        if ($status == \HTRouter::STATUS_DECLINED) {
            $this->getLogger()->log(\HTRouter\Logger::ERRORLEVEL_ERROR, "configuration error: $str returns $status");
            return \HTRouter::STATUS_HTTP_INTERNAL_SERVER_ERROR;
        } else {
            return $status;
        }
    }


    function getRouter() {
        return $this->_container->getRouter();
    }
    function getLogger() {
        return $this->_container->getLogger();
    }
    function getConfig() {
        return $this->_container->getConfig();
    }

}