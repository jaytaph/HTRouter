<?php

class HTRouter {
    // All registered directives
    protected $_directives = array();

    /**
     * Call this to route your stuff with .htaccess rules
     */
    public function route() {
        $this->_initModules();
        $this->_init();
    }

    /**
     * Initializes the modules so directives are known
     */
    protected function _initModules() {
        // Read module directory and initialize all modules
        $it = new \DirectoryIterator(dirname(__FILE__)."/Module");
        $it = new RegexIterator($it, "/\.php$/");
        foreach ($it as $file) {
            $class = "\\HTRouter\\Module\\".$file->getBaseName(".php");
            $module = new $class();
            // @TODO: Can be done through constructor?
            $module->init($this);
        }
    }

    /**
     * Init .htaccess routing
     * @return mixed
     */
    protected function _init() {
        print "<li>Looking for .htaccess";
        if (! file_exists (".htaccess")) {
            $this->_print_error(".htaccess Not found");
            return;
        }

        print "<li>Reading .htaccess";
        print "<br>";

        $htaccess = file(".htaccess");
        foreach ($htaccess as $line) {
            print "LINE: ".htmlentities($line)."<br>";

            $this->_parseLine($line);
        }

        print "<li>Parsing .htaccess";
        print "<hr>";
    }


    // @TODO Remove me
    protected function _print_error($str) {
        print "<li><font color=red>".$str."</font>";
    }


    /**
     * Register a directive
     * @param $directive
     */
    public function registerDirective($directive, HTRouter\ModuleInterface $module) {
        print "Registering: ".$directive."<br>";
        $this->_directives[] = array($module, $directive);
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
        $module->$method($match[2]);

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

}
