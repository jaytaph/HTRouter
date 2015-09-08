<?php

class HTRouter {
    const HTACCESS_FILE = ".htaccess";          // Default .htaccess filename
    const HTCONFIG_ENV = "HTROUTER_CONFIG";     // Ini configuration

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

    // Global environment
    protected $_env = false;

    const API_VERSION       = "123.45";                       // Useless API version
    const SERVER_SOFTWARE   = "Apache/2.2.0 (HTRouter)";      // Useless server string

    // Number of "subrequests" we can have maximum (prevents endless rewrites)
    const MAX_RECURSION = 15;                   // @TODO: Must be set inside the configuration?

    // These are the status codes that needs to be returned by the hooks (for now). Boolean true|false is wrong
    const STATUS_DECLINED                   =  -1;
    const STATUS_OK                         =   0;
    const STATUS_NO_MATCH                 =   1;
    const STATUS_HTTP_OK                    = 200;      // Everything above or equal to 100 is considered a HTTP status code
    const STATUS_HTTP_MOVED_TEMPORARILY     = 301;
    const STATUS_HTTP_MOVED_PERMANENTLY     = 302;
    const STATUS_HTTP_BAD_REQUEST           = 400;
    const STATUS_HTTP_UNAUTHORIZED          = 401;
    const STATUS_HTTP_FORBIDDEN             = 403;
    const STATUS_HTTP_NOT_FOUND             = 404;
    const STATUS_HTTP_INTERNAL_SERVER_ERROR = 500;

    // Provider constants
    const PROVIDER_AUTHN_GROUP      = 10;
    const PROVIDER_AUTHZ_GROUP      = 15;

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

    protected function __construct() {
        // Create DI container
        $this->_container = new \HTRouter\HTDIContainer();

        // Set router
        $this->_container->setRouter($this);

        // Read router configuration (if any)
        if (getenv(self::HTCONFIG_ENV)) {
            $this->_container->setRouterConfig($this->_readConfig(getenv(self::HTCONFIG_ENV)));
        }

        // The logger class
        $this->_container->setLogger(new \HTRouter\Logger($this->_container));

        // Variables that are initialized or read by the modules (from .htaccess)
        $this->_container->setConfig(new \HTRouter\VarContainer());

        // The actual (main) request
        $request = new \HTRouter\Request(true);
        $this->_container->setRequest($request);

        // Populate the request
        $this->_populateInitialRequest($request);

        // Initialize all modules
        $this->_initModules();
    }

    /**
     * This is the main entry point that routes everything. The module hooks will take care of finding
     * and parsing .htaccess files correctly (i hope).
     */
    public function route() {
        $request = $this->_getRequest();

        // Do actual running
        $processor = new \HTRouter\Processor($this->_container);
        $status = $processor->processRequest();
        $request->setStatus($status);

        // All done. But we need to do a final check to see which (output)handler we need to fetch (not really used)
        $this->runHook(self::HOOK_HANDLER, self::RUNHOOK_ALL, $this->_container);

        // Set our $_SERVER variables correctly according to our new request information
        $this->_modifySuperGlobalServerVars();

        // Output
        header($request->getProtocol()." ".$request->getStatus()." ".$request->getStatusLine());
        foreach ($request->getOutHeaders() as $k => $v) {
            header("$k: $v");
        }

        // Include file
        if ($request->getStatus() == self::STATUS_HTTP_OK) {
            $path = $this->_getRequest()->getDocumentRoot().$this->_getRequest()->getFilename();
            if(substr($path, -4) == '.php'){
                $closure = function ($path) { require_once($path); };
                ob_start();
                $closure($path);
                $out = ob_get_clean();
                echo $out;
            } else {
                return false;
            }
        }

        if ($request->getStatus() >= 400) {
            $this->_print_error($request);
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
     * @param \HTRouter\HTDIContainer $container
     * @return int Status
     * @throws LogicException When something wrong happens
     */
    function runHook($hookNumber, $runtype = self::RUNHOOK_ALL, \HTRouter\HTDIContainer $container) {
        // Check if something is actually registered to this hook.
        if (!isset ($this->_hooks[$hookNumber])) {
            return \HTRouter::STATUS_OK;
        }

        foreach ($this->_hooks[$hookNumber] as $moduleGroupIndex => $hook) {
            // Every hook as 0 or more "modules" hooked
            foreach ($hook as $module) {
                // Run the callback
                $class = $module[0];
                $method = $module[1];
                $retval = $class->$method($container->getRequest());

                // @TODO: Old style return, can be removed when everything is refactored
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
        $path = dirname(__FILE__)."/Module" . DIRECTORY_SEPARATOR ;

        // Read module directory and initialize all modules
        $it = new RecursiveDirectoryIterator($path);
        $it = new RecursiveRegexIterator($it, '/^.+\.php$/i');
        $it = new RecursiveIteratorIterator($it);

        foreach ($it as $file) {
            /**
             * @var $file SplFileInfo
             */

            $p = $file->getPathName();
            $p = str_replace($path, "", $p);    // Remove base path
            $p = str_replace("/", "\\", $p);    // Change / into \
            $p = str_replace(".php", "", $p);   // Remove extension
            $class = "\\HTRouter\\Module\\".$p; // Now we have got our actual class

            /**
             * @var $module \HTRouter\Module
             */
            $module = new $class();


            // Check if the module matches an alias in our disabled_modules
            $disabled = false;
            $routerConfig = $this->_getRouterConfig();
            $disabled_modules = preg_split("/,\s*/", $routerConfig['global']['disabled_modules']);
            foreach ($disabled_modules as $dismod) {
                if (in_array($dismod, $module->getAliases())) {
                    $disabled = true;
                }
            }
            // Continue with next module if disabled
            if ($disabled) continue;

            $module->init($this, $this->_container);
            $this->_modules[] = $module;
        }

        // Order the hooks
        ksort($this->_hooks);


        // At this point, the getRequest()->config holds all default values. We store them separately
        $this->_defaultConfig = $this->_container->getconfig();
    }


    /**
     * Register a directive, a keyword that can be read from htaccess file
     *
     * @param HTRouter\Module $module The module to register
     * @param string $directive The directive to register the module on
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
     * @throws InvalidArgumentException When $f is not a file resoure
     */
    function skipConfig($f, $terminateLine) {
        if (! is_resource($f)) {
            throw new \InvalidArgumentException("Must be a config resource");
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
     * @param resource $f The file resource
     * @param string $terminateLine Additional line to end our reading (instead of EOF)
     * @internal param \HTRouter\Request $request Request to use during parsing
     * @throws InvalidArgumentException Incorrect resource given
     */
    function parseConfig($f, $terminateLine = "") {
        if (! is_resource($f)) {
            throw new \InvalidArgumentException("Must be a config resource");
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
        if(isset($_SERVER['QUERY_STRING'])){
            parse_str($_SERVER['QUERY_STRING'], $args);
        } else {
            $args = array();
        }

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
        $request->setIp($_SERVER['REMOTE_ADDR']);
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

        // Let SetEnvIf etc do their thing
        $this->runHook(self::HOOK_POST_READ_REQUEST, self::RUNHOOK_ALL, $this->_container);

        $this->_getLogger()->log(\HTRouter\Logger::ERRORLEVEL_DEBUG, "Populating new request done");
    }

    /**
     * Read INI configuration
     *
     * @param string $configPath The absolute path to the configuration file
     * @return array Complete array with configuration
     */
    protected function _readConfig($configPath) {
        if (! is_readable($configPath)) return array();
        return parse_ini_file($configPath, true);
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
     * @return \HTRouter\Logger
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

    /**
     * Return a list of (installed) modules
     *
     * @return array
     */
    function getModulesAsList() {
        $ret = array();
        foreach ($this->_modules as $module) {
            $ret[] = get_class($module);
        }
        return $ret;
    }

    function getModules() {
        return $this->_modules;
    }

    function getEnvironment($key = null) {
        if (! $key) return $this->_env;
        return isset($this->_env[$key]) ? $this->_env[$key] : false;
    }
    function setEnvironment($key, $value) {
        $this->_env[$key] = $value;
    }
    function unsetEnvironment($key) {
        unset($this->_env[$key]);
    }


    function getServerSoftware() {
        return self::SERVER_SOFTWARE;
    }

    function getServerApi() {
        return self::API_VERSION;
    }

    function getLogger() {
        return $this->_container->getLogger();
    }


    /**
     * Outputs an error message generated from the current request.
     *
     * @param HTRouter\Request $request
     */
    protected function _print_error(\HTRouter\Request $request) {
        echo <<< EOH
<html>
<head>
  <title>HTRouter error code: {$request->getStatus()} - {$request->getStatusLine()} </title>
</head>

<body>
  <h1>{$request->getStatus()} - {$request->getStatusLine()}</h1>

  <table>
    <tr><td>Uri</td><td>:</td><td>{$request->getUri()}<td></tr>
    <tr><td>DocRoot</td><td>:</td><td>{$request->getDocumentRoot()}<td></tr>
    <tr><td>Filename</td><td>:</td><td>{$request->getFilename()}<td></tr>
  </table>
</body>
</html>

EOH;

    }

}