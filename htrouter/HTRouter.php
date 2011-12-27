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
    const STATUS_DECLINED                   =   1;
    const STATUS_OK                         =   2;
    const STATUS_HTTP_OK                    = 200;      // Everything above or equal to 100 is considered a HTTP status code
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
//    const HOOK_POST_READ_REQUEST    = 45;
//    const HOOK_LOG_TRANSACTION      = 50;
    const HOOK_TRANSLATE_NAME       = 55;
    const HOOK_MAP_TO_STORAGE       = 60;
    const HOOK_HEADER_PARSER        = 65;
    const HOOK_CHECK_USER_ID        = 70;
    const HOOK_FIXUPS               = 75;
    const HOOK_CHECK_TYPE           = 80;
    const HOOK_CHECK_ACCESS         = 85;
    const HOOK_CHECK_AUTHN          = 90;
    const HOOK_CHECK_AUTHZ          = 95;
//    const HOOK_INSERT_FILTER       = 100;


    // Define the way we can execute the _runHook method. These are basically the mappings of
    // AP_IMPLEMENT_HOOK_RUN_[FIRST|ALL|VOID].
    const RUNHOOK_ALL   = 1;      // Run all hooks unless we get a status other than OK or DECLINED
    const RUNHOOK_FIRST = 2;      // Run all hooks until we get a status that is not DECLINED
    const RUNHOOK_VOID  = 3;      // Run all hooks. Don't care about the status code (there probably isn't any)


    /**
     * Constructs a new router. There should only be one (but i'm not putting this inside a singleton yet)
     */
    function __construct($request = null) {
        // Initialize request
        if ($request == null) {
            $this->_request = new \HTRouter\Request($this);
        } else {
            $this->_request = $request;
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
        $this->_initServer();

        // Initialize all modules
        $this->_initModules();

        // Read htaccess
        $this->_initHtaccess();

        // Parse htaccess
        $this->_run();

        // Cleanup
        $this->_fini();

        print "<hr>";
        print "We are done. The file we need to include is : ".$this->getRequest()->getUri()."<br>\n";

        print "<h3>Environment</h3>";
        foreach ($this->getRequest()->getEnvironment() as $key => $val) {
            print "K: $key   V: $val <br>\n";
        }
    }

    /**
     * Initializes default server settings so we can emulate Apache
     */
    protected function _initServer() {

        // Set default values for our request
        $this->getRequest()->setApiVersion(self::API_VERSION);
        $this->getRequest()->setHttps(false);
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
        $this->getRequest()->setHTAccessFileResource($f);

        // Parse config
        $this->parseConfig($this->getRequest()->getHTAccessFileResource());

        // Remove from config and close file
        $this->getRequest()->unsetHTAccessFileResource();
        fclose($f);
    }


    protected function _declDie($status, $str, \HTRouter\Request $r) {
        if ($status == self::STATUS_DECLINED) {
            $r->logError("configuration error: $str returns $status");
            return self::STATUS_HTTP_INTERNAL_SERVER_ERROR;
        } else {
            return $status;
        }
    }

    protected function _runHook($hook, $runtype = self::RUNHOOK_ALL) {
        foreach ($this->_hooks[$hook] as $hook) {
            // Every hook as 0 or more "modules" hooked
            foreach ($hook as $modules) {
                // Every module has 0 or more callbacks
                foreach ($modules as $callback) {
                    // Run the callback
                    $class = $callback[0];
                    $method = $callback[1];
                    $retval = $class->$method($this->getRequest());

                    // Check if it's boolean (@TODO: Old style return, must be removed when all is refactored)
                    if (! is_numeric($retval)) {
                        throw new \LogicException("Return value must be a STATUS_* constant: found in ".get_class($class)." ->$method!");
                    }


                    if ($runtype == self::RUNHOOK_VOID) {
                        // Don't care about result. Just continue with the next
                        continue;
                    }

                    if ($runtype == self::RUNHOOK_ALL && ($retval != self::STATUS_OK || $retval != self::STATUS_DECLINED)) {
                        // Only HTTP STATUS will return
                        return $retval;
                    }

                    if ($runtype == self::RUNHOOK_FIRST && $retval != self::STATUS_DECLINED) {
                        // OK and HTTP STATUS will return
                        return $retval;
                    }

                }
            }
        }
        return self::STATUS_OK;
    }

    /**
     * Run the actual hooked plugins. It should be just as simple as stated here..
     */
    protected function _run() {
        $utils = new \HTRouter\Utils();
        $r = $this->getRequest();

        // This should look somewhat similar to request.c:ap_process_request_internal()

        // Skip proxy stuff. We don't do proxy


        // Remove /. /.. and // from the URI
        $realUri = $utils->getParents($r->getUri());
        $r->setUri($realUri);


        if ($r->isMainRequest() && $r->getFileName()) {
            $status = $this->_locationWalk($r);
            if ($status) {
                return $status;
            }

            $status = $this->_runHook(self::HOOK_TRANSLATE_NAME, self::RUNHOOK_FIRST);
            if ($status) {
                return $this->_declDie($status, "translate", $r);
            }
        }


        // Set per_dir_config to defaults


        $status = $this->_runHook(self::HOOK_MAP_TO_STORAGE, self::RUNHOOK_FIRST);
        if ($status) {
            return $status;
        }

        // Rerun location walk (@TODO: Find out why)
        if ($status) {
            return $status;
        }

        if ($r->isMainRequest()) {
            $status = $this->_runHook(self::HOOK_HEADER_PARSER, self::RUNHOOK_FIRST);
            if ($status) {
                return $status;
            }
        }

        // We always reauthenticate, something request.c doesn't do for optimizing. Easy enough to create
        $this->_authenticate($r);


        $status = $this->_runHook(self::HOOK_CHECK_TYPE, self::RUNHOOK_FIRST);
        if ($status != 0) {
            return $this->_declDie($status, "find types", $r);
        }

        $status = $this->_runHook(self::HOOK_FIXUPS, self::RUNHOOK_ALL);
        if ($status) {
            return $status;
        }

        return OK;
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



//    /**
//     * Returns a 401 response to the client, and exists
//     *
//     * @codeCoverageIgnore
//     */
//    function createAuthenticateResponse() {
//        // We are not authorized. Return a 401
//        $plugin = $this->getRequest()->getAuthType();
//
//        // Return a 401
//        header('HTTP/1.1 401 Unauthorized');
//        header('WWW-Authenticate: '.$plugin->getAuthType().' realm="'.$this->getRequest()->getAuthName().'"');
//        exit;
//    }
//
//    /**
//     * Returns a 401 response to the client, and exists
//     *
//     * @codeCoverageIgnore
//     */
//    function createForbiddenResponse() {
//        // Return a 403
//        header('HTTP/1.1 403 Forbidden');
//        exit;
//    }
//
//    /**
//     * Create a redirection with HTTP_CODE HTTP_STATUS and an optional location header
//     *
//     * @codeCoverageIgnore
//     *
//     * @param $code
//     * @param $status
//     * @param string $url
//     */
//    function createRedirect($code, $status, $url = "") {
//        header("HTTP/1.1 $code $status");
//        if ($url != "") header("Location: ".$url);  // No URL when "GONE"
//        exit;
//    }

}