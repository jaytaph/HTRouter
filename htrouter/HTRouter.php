<?php

class HTRouter {
    const HTACCESS_FILE = ".htaccess";

    // All registered directives
    protected $_directives = array();

    // All registered hooks
    protected $_hooks = array();

    // All registered providers
    protected $_providers = array();

    // The HTTP request
    protected $_request;


    // Provider constants. Order / number is irrelevant.
    const PROVIDER_AUTHN_GROUP = 10;
    const PROVIDER_AUTHZ_GROUP = 15;

    // Hook constants (they are in order of running and as defined by Apache)
    const HOOK_PRE_CONFIG           =  5;
    const HOOK_POST_CONFIG          = 10;
    const HOOK_OPEN_LOGS            = 15;
    const HOOK_CHILD_INIT           = 20;
    const HOOK_HANDLER              = 25;
    const HOOK_QUICK_HANDLER        = 30;
    const HOOK_PRE_CONNECTION       = 35;
    const HOOK_PROCESS_CONNECTION   = 40;
    const HOOK_POST_READ_REQUEST    = 45;
    const HOOK_LOG_TRANSACTION      = 50;
    const HOOK_TRANSLATE_NAME       = 55;
    const HOOK_MAP_TO_STORAGE       = 60;
    const HOOK_HEADER_PARSER        = 65;
    const HOOK_CHECK_USER_ID        = 70;
    const HOOK_FIXUPS               = 75;
    const HOOK_CHECK_TYPE           = 80;
    const HOOK_CHECK_ACCESS         = 85;
    const HOOK_CHECK_AUTHN          = 90;
    const HOOK_CHECK_AUTHZ          = 95;
    const HOOK_INSERT_FILTER       = 100;


    /**
     * Constructs a new router. There should only be one (but i'm not putting this inside a singleton yet)
     */
    function __construct() {
        // Initialize request
        $this->_request = new \HTRequest();
    }

    /**
     * Returns the current request of the router.
     *
     * @return HTRequest
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
        $this->_init();

        // Parse htaccess
        $this->_run();

        // Cleanup
        $this->_fini();
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
    protected function _init() {
        $htaccessPath = $this->_request->getDocumentRoot() . "/" . self::HTACCESS_FILE;

        // Check existence of HTACCESS
        if (! file_exists ($htaccessPath)) {
            return;
        }

        // Read & parse HTACCESS
        $htaccessFile = file($htaccessPath);
        foreach ($htaccessFile as $line) {
            print "LINE: <font color=blue>".htmlentities($line)."</font><br>";
            $this->_parseLine($line);
        }
    }

    /**
     * Run the actual hooked plugins. It should be just as simple as stated here..
     */
    protected function _run() {
        // @TODO: This must be mapped onto the same logic more or less as found in request.c:ap_process_request_internal()

        // Run each hook in order
        foreach ($this->_hooks as $hook) {
            // Every hook as 0 or more "modules" hooked
            foreach ($hook as $modules) {
                // Every module has 0 or more callbacks
                foreach ($modules as $callback) {
                    $class = $callback[0];
                    $method = $callback[1];
                    $class->$method($this->_request);
                }
            }
        }
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
    public function registerDirective(HTRouter\ModuleInterface $module, $directive) {
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
     * @param HTRouter\ModuleInterface $module
     */
    public function registerProvider($provider, HTRouter\ModuleInterface $module) {
        $this->_providers[$provider][] = $module;
    }

    /**
     * Check if directive exists, and return the module entry which holds this directive
     *
     * @param $directive
     * @return bool
     */
    protected function _directiveExists($directive) {
        foreach ($this->_directives as $v) {
            if ($directive == $v[1]) return $v;
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


    /**
     * Parse a line from the htaccess file
     *
     * @param $line
     * @return null
     */
    function _parseLine($line) {
        // trim line
        $line = trim($line);
        if (empty($line)) return;

        // Check if it's a comment line
        if ($line[0] == "#") return;

        // @TODO: Must we strip comments at the end of the file

        // First word is the directive
        if (! preg_match("/^(\w+) (.+)/", $line, $match)) {
            // Cannot find any directive
            return null;
        }

        // Find registered directive
        $tmp = $this->_directiveExists($match[1]);
        if (!$tmp) {
            // Unknown directive found
            return null;
        }

        // Call the <keyword>Directive() function inside the corresponding module
        $module = $tmp[0];      // Object
        $method = $tmp[1]."Directive";   // Method
        $module->$method($this->_request, $match[2]);
    }



    /**
     * Returns a 401 response to the client, and exists
     */
    function createAuthenticateResponse() {
        // We are not authorized. Return a 401
        $plugin = $this->_request->getAuthType();

        // Return a 401
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: '.$plugin->getAuthType().' realm="'.$this->_request->getAuthName().'"');
        exit;
    }

    /**
     * Returns a 401 response to the client, and exists
     */
    function createForbiddenResponse() {
        // Return a 403
        header('HTTP/1.1 403 Forbidden');
        exit;
    }

    function createRedirect($code, $status, $url = "") {
        header("HTTP/1.1 $code $status");
        if ($url != "") header("Location: ".$url);  // No URL when "GONE"
        exit;
    }

}