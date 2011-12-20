<?php

class HTRouter {
    const HTACCESS_FILE = "/wwwroot/router/public/htaccess";

    // All registered directives
    protected $_directives = array();

    // All registered hooks
    protected $_hooks = array();

    protected $_request;


    // Hook constants (they are in order of running)
    const HOOK_PROVIDER_GROUP = 25;
    const HOOK_CHECK_AUTH = 50;


    /**
     * Call this to route your stuff with .htaccess rules
     */
    public function route() {
        // Initialize request
        $this->_request = new \HTRequest();

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
        //$it = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

        foreach ($it as $file) {
            // @TODO: RegexIterator returns a file instead of a FileInfo Object :|
            if (! preg_match ('/^.+\.php$/i', $file->getBaseName())) continue;

            $p = $file->getPathName();
            $p = str_replace($path, "", $p);
            $p = str_replace("/", "\\", $p);
            $p = str_replace(".php", "", $p);
            $class = "\\HTRouter\\Module\\".$p;

            print "CLASS: $class <br>\n";
            $module = new $class();
            $module->init($this);

            $this->_modules[] = $module;
        }

        // Order the hooks
        ksort($this->_hooks);
    }

    /**
     * Init .htaccess routing
     * @return mixed
     */
    protected function _init() {
        // Check HTACCESS
        if (! file_exists (self::HTACCESS_FILE)) {
            print "No HTACCESS found";
            return;
        }

        // Read & parse HTACCESS
        $htaccessfile = file(self::HTACCESS_FILE);
        foreach ($htaccessfile as $line) {
            print "LINE: <font color=blue>".htmlentities($line)."</font><br>";
            $this->_parseLine($line);
        }
    }

    protected function _run() {
        foreach ($this->_hooks as $key => $hook) {
            print "<h2>Running hook :".$key."</h2>";
            foreach ($hook as $item) {
                foreach ($item as $callback) {
                    print "<pre>";
                    print_r($callback);
                    print "</pre>";
                    $class = $callback[0];
                    $method = $callback[1];
                    $class->$method($this->_request);
                }
            }
        }
    }

    protected function _fini() {
        // Cleanup
    }


    /**
     * Register a directive
     * @param $directive
     */
    public function registerDirective(HTRouter\ModuleInterface $module, $directive) {
        print "Registering: ".$directive."<br>";
        $this->_directives[] = array($module, $directive);
    }

    public function registerHook($hook, array $callback, $order = 50) {
        print "Hooking: ".$hook." at order ".$order." <br>";
        $this->_hooks[$hook][$order][] = $callback;
    }


    // Parse a line
    function _parseLine($line) {
        $line = trim($line);

        // First word is the directive
        if (! preg_match("/^(\w+) (.+)/", $line, $match)) {
            // Cannot find any directive
            return null;
        }

        // Find registered directive entry
        $tmp = $this->_directiveExists($match[1]);
        if (!$tmp) {
            // Unknown directive found
            return null;
        }

        // Find module + directive
        $module = $tmp[0];
        $directive = $tmp[1];

        // Call it
        $method = $directive."Directive";
        $module->$method($this->_request, $match[2]);
    }

    /**
     * Check if directive exists, and return the module entry which holds this directive
     * @param $directive
     * @return bool
     */
    function _directiveExists($directive) {
        foreach ($this->_directives as $v) {
            if ($directive == $v[1]) return $v;
        }
        return false;
    }


    function findModule($name) {
        $name = strtolower($name);

        foreach ($this->_modules as $module) {
            if (strtolower($module->getName()) == $name) return $module;
        }
        return null;
    }

}