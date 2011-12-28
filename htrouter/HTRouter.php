<?php

class HTRouter {
    const HTACCESS_FILE = "htaccess";

    // All registered directives
    protected $_directives = array();

    // All registered hooks
    protected $_hooks = array();

    // All registered providers
    protected $_providers = array();

    // The main request
    protected $_request;

    const API_VERSION = "123.45";       // Useless API version

    // These are the status codes that needs to be returned by the hooks (for now). Boolean true|false is wrong
    const STATUS_DECLINED                   =  -1;
    const STATUS_OK                         =   0;
    const STATUS_HTTP_OK                    = 200;      // Everything above or equal to 100 is considered a HTTP status code
    const STATUS_HTTP_MOVED_PERMANENTLY     = 302;
    const STATUS_HTTP_UNAUTHORIZED          = 401;
    const STATUS_HTTP_FORBIDDEN             = 403;
    const STATUS_HTTP_INTERNAL_SERVER_ERROR = 500;

    // Provider constants
    const PROVIDER_AUTHN_GROUP = 10;
    const PROVIDER_AUTHZ_GROUP = 15;

    // Hook constants (not all of them are provided since we don't need them)
//    const HOOK_PRE_CONFIG           =  5;
//    const HOOK_POST_CONFIG          = 10;
//    const HOOK_OPEN_LOGS            = 15;
//    const HOOK_CHILD_INIT           = 20;
//    const HOOK_HANDLER              = 25;
//    const HOOK_QUICK_HANDLER        = 30;
//    const HOOK_PRE_CONNECTION       = 35;
//    const HOOK_PROCESS_CONNECTION   = 40;
    const HOOK_POST_READ_REQUEST    = 45;
//    const HOOK_LOG_TRANSACTION      = 50;
    const HOOK_TRANSLATE_NAME       = 55;
    const HOOK_MAP_TO_STORAGE       = 60;
    const HOOK_HEADER_PARSER        = 65;
    const HOOK_CHECK_USER_ID        = 70;
    const HOOK_FIXUPS               = 75;
    const HOOK_CHECK_TYPE           = 80;
    const HOOK_CHECK_ACCESS         = 85;
    const HOOK_CHECK_AUTH           = 90;
//    const HOOK_CHECK_AUTHZ          = 95;
//    const HOOK_CHECK_AUTHN          = 90;
//    const HOOK_INSERT_FILTER       = 100;


    // Define the way we can execute the _runHook method. These are basically the mappings of
    // AP_IMPLEMENT_HOOK_RUN_[FIRST|ALL|VOID].
    const RUNHOOK_ALL   = 1;      // Run all hooks unless we get a status other than OK or DECLINED
    const RUNHOOK_FIRST = 2;      // Run all hooks until we get a status that is not DECLINED
    const RUNHOOK_VOID  = 3;      // Run all hooks. Don't care about the status code (there probably isn't any)


    /**
     * Constructs a new router. There should only be one (but i'm not putting this inside a singleton yet)
     */
    function __construct($request = null, $populate = true) {
        // Initialize request
        if ($request == null) {
            $this->_request = new \HTRouter\Request($this);
        } else {
            $this->_request = $request;
        }

        // Populate the request if needed.
        if ($populate) {
            $this->_populateInitialRequest($this->getRequest());
        }
    }

    /**
     * Returns the current request of the router.
     *
     * @return HTRouter\Request
     */
    function getRequest() {
        return $this->_request;
    }

    /**
     * Call this to route your stuff with .htaccess rules
     */
    public function route() {
        // Initialize all modules
        $this->_initModules();

        // Read htaccess
        $this->_initHtaccess();

        // Do actual running
        $status = $this->_run();
        $this->getRequest()->setStatus($status);

        // Cleanup
        $this->_fini();

        // Output data
        if (isset($_GET['debug'])) {
            // There is something seriously wrong with me....
            print "<hr>";
            print "We are done. Status <b>".$this->getRequest()->getStatus()."</b> The file we need to include is : ".$this->getRequest()->getFilename()."<br>\n";

            print "<h2>Request</h2><pre>";
            print_r($this->getRequest());
            exit;
        }

        // Output
        $r = $this->getRequest();
        header($r->getProtocol()." ".$r->getStatus()." ".$r->getStatusLine());
        foreach ($r->getOutHeaders() as $k => $v) {
            header("$k: $v");
        }
        exit;
    }

    /**
     * Initializes the modules so directives are known
     */
    protected function _initModules() {
        $path = dirname(__FILE__)."/Module/";

        // Read module directory and initialize all modules
        $it = new RecursiveDirectoryIterator($path);
        $it = new RecursiveIteratorIterator($it);

        foreach ($it as $file) {
            // @TODO: RegexIterator returns a file instead of a FileInfo Object :|
            // @TODO: RecursiveFilterIterator instead of this...
            if (! preg_match ('/^.+\.php$/i', $file->getBaseName())) continue;

            $p = $file->getPathName();
            $p = str_replace($path, "", $p);
            $p = str_replace("/", "\\", $p);
            $p = str_replace(".php", "", $p);
            $class = "\\HTRouter\\Module\\".$p;

            $module = new $class();
            $module->init($this);

            $this->_modules[] = $module;
        }

        // Order the hooks
        ksort($this->_hooks);
    }

    /**
     * Init .htaccess routing by parsing all htaccess lines and check for validity (at least, check if the directives
     * are known) and set data inside the request.
     *
     * @return mixed
     */
    protected function _initHtaccess() {
        $htaccessPath = $this->getRequest()->getDocumentRoot() . "/" . self::HTACCESS_FILE;

        // Check existence of HTACCESS
        if (! file_exists ($htaccessPath)) {
            return;
        }

        // Read HTACCESS
        $f = fopen($htaccessPath, "r");
        $this->getRequest()->config->setHTAccessFileResource($f);

        // Parse config
        $this->parseConfig($this->getRequest()->config->getHTAccessFileResource());

        // Remove from config and close file
        $this->getRequest()->config->unsetHTAccessFileResource();
        fclose($f);
    }


    protected function _declDie($status, $str, \HTRouter\Request $request) {
        if ($status == self::STATUS_DECLINED) {
            $request->logError("configuration error: $str returns $status");
            return self::STATUS_HTTP_INTERNAL_SERVER_ERROR;
        } else {
            return $status;
        }
    }

    protected function _runHook($hook, $runtype = self::RUNHOOK_ALL) {
        // Check if something is actually registered to this hook.
        if (!isset ($this->_hooks[$hook])) {
            return self::STATUS_OK;
        }

        foreach ($this->_hooks[$hook] as $hook) {
            // Every hook as 0 or more "modules" hooked
            foreach ($hook as $module) {
                // Run the callback
                $class = $module[0];
                $method = $module[1];
                print "&bull; Running: ".get_class($class)." => $method <br>\n";
                $retval = $class->$method($this->getRequest());

                // Check if it's boolean (@TODO: Old style return, must be removed when all is refactored)
                if (! is_numeric($retval)) {
                    throw new \LogicException("Return value must be a STATUS_* constant: found in ".get_class($class)." ->$method!");
                }

                if ($runtype == self::RUNHOOK_VOID) {
                    // Don't care about result. Just continue with the next
                    continue;
                }

                if ($runtype == self::RUNHOOK_ALL && ($retval != self::STATUS_OK && $retval != self::STATUS_DECLINED)) {
                    // Only HTTP STATUS will return
                    return $retval;
                }

                if ($runtype == self::RUNHOOK_FIRST && $retval != self::STATUS_DECLINED) {
                    // OK and HTTP STATUS will return
                    return $retval;
                }
            }
        }
        return self::STATUS_OK;
    }

    /**
     * This should look somewhat similar to request.c:ap_process_request_internal()
     */
    protected function _run() {
        $utils = new \HTRouter\Utils();
        $r = $this->getRequest();       // just an alias

        // If you are looking for proxy stuff. It's not here to simplify things

        // Remove /. /.. and // from the URI
        $realUri = $utils->getParents($r->getUri());
        $r->setUri($realUri);


        if ($r->isMainRequest() && $r->getFileName()) {
            $status = $this->_locationWalk($r);
            if ($status != self::STATUS_OK) {
                return $status;
            }

            $status = $this->_runHook(self::HOOK_TRANSLATE_NAME, self::RUNHOOK_FIRST);
            if ($status != self::STATUS_OK) {
                return $this->_declDie($status, "translate", $r);
            }
        }


        // Set per_dir_config to defaults


        $status = $this->_runHook(self::HOOK_MAP_TO_STORAGE, self::RUNHOOK_FIRST);
        if ($status != self::STATUS_OK) {
            return $status;
        }

        // Rerun location walk (@TODO: Find out why)
        if ($status != self::STATUS_OK) {
            return $status;
        }

        if ($r->isMainRequest()) {
            $status = $this->_runHook(self::HOOK_HEADER_PARSER, self::RUNHOOK_FIRST);
            if ($status != self::STATUS_OK) {
                return $status;
            }
        }

        // We always re-authenticate. Something request.c doesn't do for optimizing. Easy enough to create though.
        $status = $this->_authenticate($r);
        if ($status != self::STATUS_OK) {
            return $status;
        }

        $status = $this->_runHook(self::HOOK_CHECK_TYPE, self::RUNHOOK_FIRST);
        if ($status != self::STATUS_OK) {
            return $this->_declDie($status, "find types", $r);
        }

        $status = $this->_runHook(self::HOOK_FIXUPS, self::RUNHOOK_ALL);
        if ($status != self::STATUS_OK) {
            return $status;
        }

        // If everything is ok. Note that we return 200 OK instead of "OK" since we need to return a code for the
        // router to work with...
        return self::STATUS_HTTP_OK;
    }

    /**
     * Do the authentication
     */
    protected function _authenticate(\HTRouter\Request $request) {
        switch ($request->config->getSatisfy("all")) {
            default :
            case "all" :
                $status = $this->_runHook(self::HOOK_CHECK_ACCESS, self::RUNHOOK_ALL);
                if ($status  != \HTRouter::STATUS_OK) {
                    return $this->_declDie($status, "check access", $request);
                }

                // We only do this if there are any "requires". Without requires, we do not need authentication
                if (count($request->config->getRequire(array())) > 0) {
                    $status = $this->_runHook(self::HOOK_CHECK_USER_ID, self::RUNHOOK_FIRST);
                    if ($request->getAuthType() == null) {
                        $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check user failure", $request);
                    }

                    $status = $this->_runHook(self::HOOK_CHECK_AUTH, self::RUNHOOK_FIRST);
                    if ($request->getAuthType() == null) {
                        return $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check access failure", $request);
                    }
                }
                break;
            case "any" :
                $status = $this->_runHook(self::HOOK_CHECK_ACCESS, self::RUNHOOK_ALL);
                if ($status != \HTRouter::STATUS_OK) {

                    // No requires needed
                    if (count($request->config->getRequire(array())) == 0) {
                        return $this->_declDie($status, "check access", $request);
                    }

                    $status = $this->_runHook(self::HOOK_CHECK_USER_ID, self::RUNHOOK_FIRST);
                    if ($request->getAuthType() == null) {
                        return $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check user failure", $request);
                    }

                    $status = $this->_runHook(self::HOOK_CHECK_AUTH, self::RUNHOOK_FIRST);
                    if ($request->getAuthType() == null) {
                        return $this->_declDie($status, "AuthType not set", $request);
                    } elseif ($status != \HTRouter::STATUS_OK) {
                        return $this->_declDie($status, "Check access failure", $request);
                    }
                }
                break;
        }

        return self::STATUS_OK;
    }


    /**
     * Do a location walk to check if we are still OK on the location (i guess)...
     *
     * @param HTRouter\Request $request
     */
    protected function _locationWalk(\HTRouter\Request $request) {
        // Since we don't have any locations, we just return
        return self::STATUS_OK;
    }

    /**
     * Cleanup stuff, if needed
     */
    protected function _fini() {
        // Cleanup
    }


    /**
     * Register a directive, a keyword that can be read from htaccess file
     * @param $directive
     */
    public function registerDirective(\HTRouter\Module $module, $directive) {
        if ($this->_directiveExists($directive)) {
            throw new \RuntimeException("Cannot register the same directive twice!");
        }
        $this->_directives[] = array($module, $directive);
    }

    /**
     * Register a hook
     *
     * @param $hook
     * @param array $callback The callback to the module->method
     * @param int $order Order (0-100) of the modules that are added to the specified hook
     */
    public function registerHook($hook, array $callback, $order = 50) {
        // We can register our hooks with the "order".
        // Apache defines APR_HOOK_[FIRST|MIDDLE|LAST]. We don't use that but use a simple ordering from 0-100.
        $this->_hooks[$hook][$order][] = $callback;
    }

    /**
     * Providers are not really hooks, but can be used for modules to add functionality. A good example
     * would be to register the authn_basic and authn_digest types.
     *
     * @param $provider
     * @param \HTRouter\Module $module
     */
    public function registerProvider($provider, \HTRouter\Module $module) {
        $this->_providers[$provider][] = $module;
    }

    /**
     * Check if directive exists, and return the module entry which holds this directive
     *
     * @param $directive
     * @return bool
     */
    protected function _directiveExists($directive) {
        $directive = strtolower($directive);
        foreach ($this->_directives as $v) {
            if ($directive == strtolower($v[1])) return $v;
        }
        return false;
    }

    /**
     * Get all data for specified provider. Normally this would be a list of objects conforming a specific interface.
     *
     * @param $provider
     * @return array
     */
    function getProviders($provider) {
        if (! isset($this->_providers[$provider])) return array();
        return $this->_providers[$provider];
    }


    /**
     * Find specified module
     */
    function findModule($name) {
        $name = strtolower($name);

        foreach ($this->_modules as $module) {
            foreach ($module->getAliases() as $alias) {
                if (strtolower($alias) == $name) return $module;
            }
        }
        return null;
    }


    // Skip a block from the configuration until we find 'terminateLine' (mostly a </tag>)
    function SkipConfig($f, $terminateLine) {
        if (! is_resource($f)) {
            throw new \UnexpectedValueException("Must be a config resource");
        }

        while (! feof($f)) {
            // Fetch next line
            $line = fgets($f);

            // trim line
            $line = trim($line);
            if (empty($line)) continue;

            // Check if it's a comment line
            if ($line[0] == "#") continue;

            // Found ending line?
            if (! empty($terminateLine) && strtolower($line) == strtolower($terminateLine)) {
                return;
            }
        }
    }


    /**
     * Parse a line from the htaccess file
     *
     * @param $line
     * @return null
     */
    function parseConfig($f, $terminateLine = "") {
        if (! is_resource($f)) {
            throw new \UnexpectedValueException("Must be a config resource");
        }

        while (! feof($f)) {
            // Fetch next line
            $line = fgets($f);

            // trim line
            $line = trim($line);
            if (empty($line)) continue;

            // Check if it's a comment line
            if ($line[0] == "#") continue;

            // Found ending line?
            if (! empty($terminateLine) && strtolower($line) == strtolower($terminateLine)) {
                break;
            }

            if (! empty($terminateLine)) {
                print "LINE: <font color=red>".htmlentities($terminateLine)." => ".htmlentities($line)."</font><br>";
            } else {
                print "LINE: <font color=green>".htmlentities($line)."</font><br>";
            }

            // @TODO: Must we strip comments at the end of the file

            // @TODO: TRY TO BE A BETTER PARSER

            // First word is the directive
            if (! preg_match("/^(\S+)\s+(.+)/", $line, $match)) {
                // Cannot find any directive
                continue;
            }

            // Find registered directive
            $tmp = $this->_directiveExists($match[1]);
            if (!$tmp) {
                // Unknown directive found
                continue;
            }

            // Replace <IfModule to gt_IfModule
            if ($tmp[1][0] == "<") {
                $tmp[1] = "gt_" . substr($tmp[1], 1);
            }

            // Call the <keyword>Directive() function inside the corresponding module
            $module = $tmp[0];               // Object
            $method = $tmp[1]."Directive";   // Method
            $module->$method($this->getRequest(), $match[2]);
        }
    }


    protected function _populateInitialRequest(\HTRouter\Request $request) {
        /**
         * A lot of stuff is already filtered by either apache or the built-in webserver. We just have to
         * populate our request so we have a generic state which we can work with. From this point on, it
         * should never matter on what kind of webserver we are actually working on (in fact: this can be
         * the base of writing your own webserver like nanoweb)
         */

        // By default, we don't have any authentication
        // @TODO: What about authentication? Where do we set this?
        $request->setAuthType(null);
        $request->setUser("");

        // Query arguments
        parse_str($_SERVER['QUERY_STRING'], $args);
        $request->setArgs($args);

        $request->setContentEncoding("");
        $request->setContentLanguage("");
        $request->setContentType("text/plain");

        // @TODO: Find requesting file?
        // NOTE: Must be set before checking findUriOnDisk!
        $request->setDocumentRoot($_SERVER['DOCUMENT_ROOT']);

        $utils = new \HTRouter\Utils();
        $filename = $utils->findUriOnDisk($request, $_SERVER['REQUEST_URI']);
        $request->setFilename($filename);

        if (isset($_SERVER['PATH_INFO']))
            $request->setPathInfo($_SERVER['PATH_INFO']);


        // Set INPUT headers
        foreach ($_SERVER as $key => $item) {
            if (! is_string($key)) continue;
            if (substr($key, 0, 5) != "HTTP_") continue;

            $key = substr($key, 5);
            $key = strtolower($key);
            $key = str_replace("_", "-", $key);

            $key = preg_replace_callback("/^(.)|-(.)/", function ($matches) { return strtoupper($matches[0]); }, $key);
            $request->appendInHeaders($key, $item);
        }

        /*
         * Apache does not send us the Authorization variable. So this piece of code checks if apache_request_headers
         * function is present (we are running the router from apache(compatible) browser), and add the authorization
         * header.
         */
        if (function_exists("apache_request_headers")) {
            $tmp = apache_request_headers();
            if (isset($tmp['Authorization'])) {
                $request->appendInHeaders('Authorization', $tmp['Authorization']);
            }
         }

        // We don't have the actual host-header, but we misuse the http_host for this
        $tmp = parse_url($_SERVER['HTTP_HOST']);
        $request->setHostname(isset($tmp['host'])?$tmp['host']:$tmp['path']);

        $request->setMethod($_SERVER['REQUEST_METHOD']);
        $request->setProtocol($_SERVER['SERVER_PROTOCOL']);
        $request->setStatus(\HTRouter::STATUS_HTTP_OK);
        $request->setUnparsedUri($_SERVER['REQUEST_URI']);
        $request->setUri($_SERVER['SCRIPT_NAME']);

        // Let SetEnvIf etc do their thing
        $this->_runHook(self::HOOK_POST_READ_REQUEST, self::RUNHOOK_ALL);
    }


    /**
     * Copies a request into a new (sub)request. Also takes care of additional handlers/hooks
     *
     * @param HTRouter\Request $request
     */
    function copyRequest(\HTRouter\Request $request) {
        $new = \HTRouter\Request($request->getRouter());

        // Let SetEnvIf etc do their thing, again
        $this->_runHook(self::HOOK_POST_READ_REQUEST, self::RUNHOOK_ALL);

        return $new;
    }
}