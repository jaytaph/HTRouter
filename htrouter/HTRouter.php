<?php

class HTRouter {
    const HTACCESS_FILE = "htaccess";
    const CONFIG_FILE = "../public/htrouter.ini";

    // All registered directives
    protected $_directives = array();

    // All registered hooks
    protected $_hooks = array();

    // All registered providers
    protected $_providers = array();

    // All found modules
    protected $_modules = array();

    // Array with default configuration after initialization of the modules (default .htaccess config)
    protected $_defaultConfig;

    const API_VERSION = "123.45";       // Useless API version


    const MAX_RECURSION = 15;           // @TODO: Must be set inside the configuration?

    // These are the status codes that needs to be returned by the hooks (for now). Boolean true|false is wrong
    const STATUS_DECLINED                   =  -1;
    const STATUS_OK                         =   0;
    const STATUS_HTTP_OK                    = 200;      // Everything above or equal to 100 is considered a HTTP status code
    const STATUS_HTTP_MOVED_PERMANENTLY     = 302;
    const STATUS_HTTP_BAD_REQUEST           = 400;
    const STATUS_HTTP_UNAUTHORIZED          = 401;
    const STATUS_HTTP_FORBIDDEN             = 403;
    const STATUS_HTTP_NOT_FOUND             = 404;
    const STATUS_HTTP_INTERNAL_SERVER_ERROR = 500;

    // Provider constants
    const PROVIDER_AUTHN_GROUP = 10;
    const PROVIDER_AUTHZ_GROUP = 15;

    // Hook constants (not all of them are provided since we don't need them)
    const HOOK_HANDLER              = 40;
    const HOOK_POST_READ_REQUEST    = 45;
    const HOOK_TRANSLATE_NAME       = 55;
    const HOOK_MAP_TO_STORAGE       = 60;
    const HOOK_HEADER_PARSER        = 65;
    const HOOK_CHECK_USER_ID        = 70;
    const HOOK_FIXUPS               = 75;
    const HOOK_CHECK_TYPE           = 80;
    const HOOK_CHECK_ACCESS         = 85;
    const HOOK_CHECK_AUTH           = 90;

    // Define the way we can execute the runHook() method. These are basically the mappings of
    // AP_IMPLEMENT_HOOK_RUN_[FIRST|ALL|VOID].
    const RUNHOOK_ALL   = 1;      // Run all hooks unless we get a status other than OK or DECLINED
    const RUNHOOK_FIRST = 2;      // Run all hooks until we get a status that is not DECLINED
    const RUNHOOK_VOID  = 3;      // Run all hooks. Don't care about the status code (there probably isn't any)

    // Singleton.
    private static $_instance = null;

    /**
     * @static
     * @return \HTRouter
     */
    public static function getInstance() {
        if (! self::$_instance) {
            $class = __CLASS__;
            self::$_instance = new $class();
        }
        return self::$_instance;
    }

    private function __clone() {
        // Cannot be used due to singleton
    }

    private function __construct() {
        // Create DI container
        $this->_container = new \HTRouter\HTDIContainer();
        $this->_container->name = "MainRequest";

        // Set router
        $this->_container->setRouter($this);

        // Main router htrouter.ini configuration
        $this->_container->setRouterConfig($this->_readConfig(__DIR__.'/'.self::CONFIG_FILE));

        // The logger class
        $this->_container->setLogger(new \HTRouter\Logger($this->_container));

        // Variables that are initialized or read by the modules (from .htaccess)
        $this->_container->setConfig(new \HTRouter\VarContainer());

        // The actual (main) request
        $request = new \HTRouter\Request(true);
        $this->_container->setRequest($request);

        // Populate the request
        $this->_populateInitialRequest($request);
    }


    /**
     * This is the main entrypoint that routes everything. The module hooks will take care of finding
     * and parsing .htaccess files correctly (i hope).
     */
    public function route() {
        // Initialize all modules
        $this->_initModules();

        // Do actual running
        $processor = new \HTRouter\Processor($this->_container);
        $status = $processor->processRequest();
        $this->_getRequest()->setStatus($status);

        // All done. But we need to do a final check to see which handler we need to fetch
        $status = $this->runHook(self::HOOK_HANDLER, self::RUNHOOK_ALL, $this->_container);


        // Set our $_SERVER variables correctly according to our new request information
        $this->_modifySuperGlobalServerVars();

        // Output data
        if (isset($_GET['debug'])) {
            // @TODO: remove this. There is something seriously wrong with me....
            print "<hr>";
            print "We are done. Status <b>".$this->_getRequest()->getStatus()."</b>. ";

            if ($this->_getRequest()->getStatus() == \HTRouter::STATUS_HTTP_OK) {
                // @TODO Closure?
                print "The file we need to include is : " . $this->_getRequest()->getDocumentRoot().$this->_getRequest()->getFilename()."<br>\n";
            } else {
                print "We do not need to include a file but do something else: ".$this->_getRequest()->getStatusLine()."<br>\n";
                print "Our outgoing headers: ";
                print "<pre>";
                print_r ($this->_getRequest()->getOutHeaders());
            }

            print "<h2>Server</h2><pre>";
            print_r ($_SERVER);

            print "<h2>Request</h2><pre>";
            print_r($this->_getRequest());
            exit;
        }

        // Output
        $r = $this->_getRequest();
        header($r->getProtocol()." ".$r->getStatus()." ".$r->getStatusLine());
        foreach ($r->getOutHeaders() as $k => $v) {
            header("$k: $v");
        }
        exit;
    }

    /**
     * Run all modules that are hooked onto this particular hook. This emulates Apache's
     * APR_IMPLEMENT_EXTERNAL_HOOK_RUN_* macro's.
     *
     * There are 3 different modes we can run:
     *   RUNHOOK_ALL: run until we hit something that is not DECLINED or OK
     *   RUNHOOK_FIRST: run until we hit the first non DECLINED
     *   RUNHOOK_VOID: run everything, always. We throw away any status we get from the modules.
     *
     * @param mixed $hook Hook to run
     * @param int $runtype The type of running
     * @return int Status
     * @throws LogicException When something wrong happens
     */
    function runHook($hook, $runtype = self::RUNHOOK_ALL, \HTRouter\HTDIContainer $container) {
        // Check if something is actually registered to this hook.
        if (!isset ($this->_hooks[$hook])) {
            return \HTRouter::STATUS_OK;
        }

        foreach ($this->_hooks[$hook] as $hook) {
            // Every hook as 0 or more "modules" hooked
            foreach ($hook as $module) {
                // Run the callback
                $class = $module[0];
                $method = $module[1];
                print "&bull; Running: ".get_class($class)." => $method <br>\n";
                $retval = $class->$method($container->getRequest());

                // Check if it's boolean (@TODO: Old style return, must be removed when all is refactored)
                if (! is_numeric($retval)) {
                    throw new \LogicException("Return value must be a STATUS_* constant: found in ".get_class($class)." ->$method!");
                }

                if ($runtype == self::RUNHOOK_VOID) {
                    // Don't care about result. Just continue with the next
                    continue;
                }

                if ($runtype == self::RUNHOOK_ALL && ($retval != \HTRouter::STATUS_OK && $retval != \HTRouter::STATUS_DECLINED)) {
                    // Only HTTP STATUS will return
                    return $retval;
                }

                if ($runtype == self::RUNHOOK_FIRST && $retval != \HTRouter::STATUS_DECLINED) {
                    // OK and HTTP STATUS will return
                    return $retval;
                }
            }
        }
        return \HTRouter::STATUS_OK;
    }

    /**
     * Returns the current request of the router.
     *
     * @return HTRouter\Request The request
     */
    protected function _getRequest() {
        return $this->_container->getRequest();
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
            /**
             * @var $file SplFileInfo
             */
            // @TODO: RegexIterator returns a file instead of a FileInfo Object :|
            // @TODO: RecursiveFilterIterator instead of this...
            if (! preg_match ('/^.+\.php$/i', $file->getBaseName())) continue;

            $p = $file->getPathName();
            $p = str_replace($path, "", $p);
            $p = str_replace("/", "\\", $p);
            $p = str_replace(".php", "", $p);
            $class = "\\HTRouter\\Module\\".$p;

            /**
             * @var $module \HTRouter\Module
             */
            $module = new $class();
            $module->init($this, $this->_container);

            $this->_modules[] = $module;
        }

        // Order the hooks
        ksort($this->_hooks);


        // At this point, the getRequest()->config holds all default values. We store them separately
        $this->_defaultConfig = $this->_container->getconfig();
    }

    /**
     * Cleanup stuff, if needed
     */
    protected function _fini() {
        // Cleanup
    }

    /**
     * Register a directive, a keyword that can be read from htaccess file
     *
     * @param HTRouter\Module $module The module to register
     * @param $directive The directive to register the module on
     * @throws RuntimeException Thrown when the directive is already registered
     */
    public function registerDirective(\HTRouter\Module $module, $directive) {
        if ($this->_directiveExists($directive)) {
            throw new \RuntimeException("Cannot register the same directive twice!");
        }
        $this->_directives[] = array($module, $directive);
    }

    /**
     * Register a hook. Those hooks can be called from different places when needed by the HTRouter
     *
     * @param string $hook The name of the hook
     * @param array $callback The callback to the module->method
     * @param int $order Order (0-100) of the modules that are added to the specified hook. Only use 0 and 100 when you really mean it (REALLY_FIRST, REALLY_LAST)
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
     * @param string $provider The provider to register the module for
     * @param \HTRouter\Module $module The module to register
     */
    public function registerProvider($provider, \HTRouter\Module $module) {
        $this->_providers[$provider][] = $module;
    }

    /**
     * Check if directive exists, and return the module entry which holds this directive
     *
     * @param string $directive The directive to look for
     * @return bool|mixed false when not found, otherwise return the directive's array(class, method)
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
     * @param string $provider The provider to return
     * @return array All providers or empty array when nothing is found
     */
    function getProviders($provider) {
        if (! isset($this->_providers[$provider])) return array();
        return $this->_providers[$provider];
    }

    /**
     * Finds and returns the specified module. Searching can be done on any name that is returned by the
     * Module's getAliases() methods.
     *
     * @param $name The module to find
     * @return null|\HTRouter\Module The found module, or null on nothing found
     */
    function findModule($name) {
        $name = strtolower($name);

        foreach ($this->_modules as $module) {
            /**
             * @var $module \HTRouter\Module
             */
            foreach ($module->getAliases() as $alias) {
                if (strtolower($alias) == $name) return $module;
            }
        }
        return null;
    }

    /**
     * Skips lines from the configuration until we find 'terminateLine' (most of the time, a </tag>)
     *
     * @param resource $f File resource
     * @param string $terminateLine The line on which it needs to terminate
     * @throws UnexpectedValueException When $f is not a file resoure
     */
    function skipConfig($f, $terminateLine) {
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
     * Parse a complete or partial .htaccess file. When terminateLine is given, it will stop parsing until it finds
     * that particular line. Used when parsing blocks like <ifModule> etc..
     *
     * @param HTRouter\Request $request Request to use during parsing
     * @param resource $f The file resource
     * @param string $terminateLine Additional line to end our reading (instead of EOF)
     * @throws UnexpectedValueException Incorrect resource given
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
            $module->$method($this->_container->getRequest(), $match[2]);
        }
    }

    /**
     * Initialize the request with standard values taken from the $_SERVER.
     *
     * @param HTRouter\Request $request The request to be filled
     */
    protected function _populateInitialRequest(\HTRouter\Request $request) {
        /**
         * A lot of stuff is already filtered by either apache or the built-in webserver. We just have to
         * populate our request so we have a generic state which we can work with. From this point on, it
         * should never matter on what kind of webserver we are actually working on (in fact: this can be
         * the base of writing your own webserver like nanoweb)
         */

        $routerConfig = $this->_getRouterConfig();

        // By default, we don't have any authentication
        //$request->setAuthType(null);
        $request->setUser("");

        // Query arguments
        parse_str($_SERVER['QUERY_STRING'], $args);
        $request->setArgs($args);

        $request->setContentEncoding("");
        $request->setContentLanguage("");
        $request->setContentType("text/plain");

        // @TODO: Find requesting file?
        // NOTE: Must be set before checking findUriOnDisk!
        if (isset($routerConfig['global']['documentroot'])) {
            $request->setDocumentRoot($routerConfig['global']['documentroot']);
        } else {
            $request->setDocumentRoot($_SERVER['DOCUMENT_ROOT']);
        }

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

        // We don't have the actual host-header, but we can use the http_host variable for this
        $tmp = parse_url($_SERVER['HTTP_HOST']);
        $request->setHostname(isset($tmp['host']) ? $tmp['host'] : $tmp['path']);

        $request->setMethod($_SERVER['REQUEST_METHOD']);
        $request->setProtocol($_SERVER['SERVER_PROTOCOL']);
        $request->setStatus(\HTRouter::STATUS_HTTP_OK);

        if (! isset($_SERVER['PATH_INFO'])) {
            $_SERVER['PATH_INFO'] = "";
        }

        // These are again, depending on the type of server. Strip the router.php if needed
        $request->setUnparsedUri($_SERVER['REQUEST_URI']);
            $request->setUri($_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO']);

        // Check if we need to remove our router info
        if (isset($routerConfig['global']['apacherouterprefix'])) {
            $routerName = $routerConfig['global']['apacherouterprefix'];
            if (strpos($_SERVER['REQUEST_URI'], $routerName) === 0) {
                $uri = substr($_SERVER['REQUEST_URI'], strlen($routerName));
                if ($uri === false) $uri = "/";
                $request->setUnparsedUri($uri);
            }

            if (strpos($_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'], $routerName) === 0) {
                $uri = substr($_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'], strlen($routerName));
                if ($uri === false) $uri = "/";
                $request->setUri($uri);
            }
        }

        $request->setQueryString($_SERVER['QUERY_STRING']);

        // Let SetEnvIf etc do their thing
        $this->runHook(self::HOOK_POST_READ_REQUEST, self::RUNHOOK_ALL, $this->_container);

        $this->_getLogger()->log(\HTRouter\Logger::ERRORLEVEL_DEBUG, "Populating new request done");
    }

    /**
     * Copies a request into a new (sub)request. Also takes care of additional handlers/hooks
     *
     * @param HTRouter\Request $request
     * @return HTRouter\Request The new copied request
     */
    function copyRequest(\HTRouter\Request $request) {
        $new = new \HTRouter\Request($request);
        $new->mergeVariables($request);

        // Let SetEnvIf etc do their thing, again
        $this->runHook(self::HOOK_POST_READ_REQUEST, self::RUNHOOK_ALL, $this->_container);

        return $new;
    }

    /**
     * Read INI configuration
     *
     * @param string $configPath The absolute path to the configuration file
     * @return array Complete array with configuration
     */
    protected function _readConfig($configPath) {
        if (! is_readable($configPath)) return;
        return  parse_ini_file($configPath, true);
    }

    /**
     * returns main htrouter configuration
     *
     * @return array The configuration
     */
    protected function _getRouterConfig() {
        return $this->_container->getRouterConfig();
    }

    /**
     * Returns the logger
     * @return mixed
     */
    function _getLogger() {
        return $this->_container->getLogger();
    }

    /**
     * Modifies the superglobal $_SERVER variables.
     */
    protected function _modifySuperGlobalServerVars() {
        $request = $this->_getRequest();

        $_SERVER['PHP_SELF'] = $request->getFilename() . $request->getPathInfo();
        $_SERVER['QUERY_STRING'] = $request->getQueryString();
        $_SERVER['SCRIPT_FILENAME'] = $request->getDocumentRoot() . $request->getFilename();
        $_SERVER['SCRIPT_NAME'] = $request->getFilename();
        $_SERVER['REQUEST_URI'] = $request->getUnparsedUri();
        $_SERVER['PATH_INFO'] = $request->getPathInfo();
        $tmp = $request->getAuthDigest();
        if (! empty($tmp)) $_SERVER['PHP_AUTH_DIGEST'] = $tmp;
        $tmp = $request->getAuthUser();
        if (! empty($tmp)) $_SERVER['PHP_AUTH_USER'] = $tmp;
        $tmp = $request->getAuthPass();
        if (! empty($tmp)) $_SERVER['PHP_AUTH_PW'] = $tmp;
        $tmp = $request->getAuthType();
        if (! empty($tmp)) $_SERVER['AUTH_TYPE'] = $tmp;
    }


    function getDefaultConfig() {
        return $this->_defaultConfig;
    }



    function prepareContainerForSubRequest($url) {
        $subrequest = clone ($this->_container->getRequest());
        $subrequest->setMainRequest(false);
        $subrequest->setUri($url);
        $subrequest->setFilename(null);

        $subContainer = clone ($this->_container);
        $subContainer->name = $this->_container->name . " (SubRequest)";
        //$subContainer->setConfig($this->_container->getRouter()->getDefaultConfig());
        $subContainer->setRequest($subrequest);


        return $subContainer;
    }

}