<?php
/**
 * Core module. Most of these directives we probably won't need.
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Core extends Module {
    // Cached htaccess files. In case we need to read/parse htaccess multiple times
    protected $_cachedHTAccess = array();

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register directives
        $router->registerDirective($this, "require");
        $router->registerDirective($this, "satisfy");
        $router->registerDirective($this, "<ifmodule");
        $router->registerDirective($this, "AuthName");
        $router->registerDirective($this, "AuthType");

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_MAP_TO_STORAGE, array($this, "coreMapToStorage"), 100);  // Really last!
        $router->registerHook(\HTRouter::HOOK_TRANSLATE_NAME, array($this, "coreTranslateName"), 100); // Really last!
        $router->registerHook(\HTRouter::HOOK_HANDLER, array($this, "coreHandler"), 100);              // Really last!


        // Set default values
        $this->getConfig()->set("Satisfy", "all");
    }

    public function requireDirective(\HTRouter\Request $request, $line) {
        $this->getConfig()->append("Require", $line);
    }

    public function satisfyDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("all" => "all", "any" => "any"));
        $this->getConfig()->set("Satisfy", $value);
    }

    public function gt_ifmoduleDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        if ($line[strlen($line)-1] != '>') {
            throw new \InvalidArgumentException("No > found");
        }

        $module = str_replace(">", "", $line);

        // Check if module exists
        $router = \HTRouter::getInstance();
        if (! $router->findModule($module)) {
            // Module does not exist, so skip this configuration block
            $router->skipConfig($this->getConfig()->get("HTAccessFileResource"), "</IfModule>");
        } else {
            // Module does exist, read this configuration block
            $router->parseConfig($this->getConfig()->get("HTAccessFileResource"), "</IfModule>");
        }
    }

    public function authNameDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        $line = trim($line, "\"\'");
        $this->getConfig()->set("AuthName", $line);
    }

    public function authTypeDirective(\HTRouter\Request $request, $line) {
        $name = "auth_".strtolower(trim($line));

        // @TODO: We should check that we have the correct AUTH_* module loaded
        $router = \HTRouter::getInstance();
        $plugin = $router->findModule($name);
        if (! $plugin) {
            throw new \InvalidArgumentException("Cannot find $name");
        }

        $this->getConfig()->set("AuthType", $plugin);
    }


    function coreHandler(\HTRouter\Request $request) {
        // Since we don't actually use handlers, this just checks to see if filename is an
        // actual file. If not, we create a 404.

        // Check if the status has been set correctly. If so, don't modify our status
        $status = $request->getStatus();
        if ($status != \HTrouter::STATUS_HTTP_OK) {
            return \HTRouter::STATUS_DECLINED;
        }

        // From this point, we know that the filename is the one we need. So we can check
        // for existence.
        $path = $request->getDocumentRoot() . $request->getFilename();

        if (is_dir($path)) {
            // Is it a directory. We are not allowed to view it!
            $request->setStatus(\HTROuter::STATUS_HTTP_FORBIDDEN);
            return \HTROuter::STATUS_OK;
        }

        if (! is_readable($path)) {
            // Is the file not viewable or existing? Not found!
            $request->setStatus(\HTROuter::STATUS_HTTP_NOT_FOUND);
            return \HTROuter::STATUS_OK;
        }

        // Next handler
        return \HTROuter::STATUS_DECLINED;
    }

    function coreTranslateName(\HTRouter\Request $request) {
        $uri = $request->getUri();
        if (empty($uri) || $uri[0] != '/' || $uri == "*") {
            $this->getLogger()->log(\HTRouter\Logger::ERRORLEVEL_ERROR, "Invalid uri in request: ".$uri);
            return \HTRouter::STATUS_HTTP_BAD_REQUEST;
        }

        $filename = $request->getUri();
        $request->setFilename($filename);   // Remember, filename must be relative from documentroot!

        return \HTRouter::STATUS_OK;
    }

    /**
     * map to storage: walk over the directory and merge htaccess components.
     *
     * @param \HTRouter\Request $request
     * @return mixed
     */
    function coreMapToStorage(\HTRouter\Request $request) {
        $status = $this->_directoryWalk($request);
        if ($status != \HTRouter::STATUS_OK) {
            return $status;
        }
        $status = $this->_fileWalk($request);
        if ($status != \HTRouter::STATUS_OK) {
            return $status;
        }
        return \HTRouter::STATUS_OK;
    }

    /**
     * ap_directory_walk is really difficult since it needs to be highly optimized. Since we don't need that
     * optimization and we don't need much of the functionality, I've used some artistic freedom in creating
     * this method.
     *
     * @param \HTRouter\Request $request
     * @return int
     */
    protected function _directoryWalk(\HTRouter\Request $request) {
        // No filename found to start from?
        $fn = $request->getFilename();
        if (empty($fn)) {
            $utils = new \HTrouter\Utils();
            $path = $utils->findUriOnDisk($request, $request->getUri());
            $request->setFilename($path);
            return \HTRouter::STATUS_OK;
        }

        // get htaccess name from config or constant
        $config = $this->getRouterConfig("global");
        if (isset ($config['htaccessfilename'])) {
            $htaccessFilename = $config['htaccessfilename'];
        } else {
            $htaccessFilename = \HTRouter::HTACCESS_FILE;
        }

        /* Create an array with the following paths:
         *
         *   /
         *   /wwwroot
         *   /wwwroot/router
         *   /wwwroot/router/public
         *
         * So we can do a simple iteration without worrying about stuff. Easier to reverse the process if needed.
         */
        if ($fn[strlen($fn)-1] == '/') {
            // THis is a "directory" (ie: /dir/, so filename is the dirname)
            $dirname = $fn;
        } else {
            // This might be a directory, we should check to see if the file is a directory, if so, it's a dir
            if (is_dir($request->getDocumentRoot() . $fn)) {
                $dirname = $fn;
            } else {
                // A file, so get the dirname
                $dirname = dirname($fn);
            }
        }
        $path = explode("/", $dirname);
        if (empty($path[count($path)-1])) array_pop($path);
        $dirs = array();
        while (count($path) > 0) {
            $dirs[] = $request->getDocumentRoot() . join("/", $path);
            array_pop($path);
        }
        $dirs = array_unique($dirs);    // Just in case...

        // Iterate directories and find htaccess files
        foreach ($dirs as $dir) {
            $htaccessPath = $dir ."/".$htaccessFilename;

            if (is_readable($htaccessPath)) {
                // Read HTACCESS and merge information
                $newConfig = $this->_readHTAccess($request, $htaccessPath);

                // Merge together with current request
                foreach ($this->getRouter()->getModules() as $module) {
                    /**
                     * @var $module \HTRouter\Module
                     */
                    $module->mergeConfigs($this->getConfig(), $newConfig);
                }
            }
        }

        return \HTRouter::STATUS_OK;
    }

    protected function _fileWalk(\HTRouter\Request $request) {
        // @TODO: Create me
        return \HTRouter::STATUS_OK;
    }


    protected function _readHTAccess(\HTRouter\Request $request, $htaccessPath) {
        // Check if the htaccess exists inside the cache, if so, return that one.
        if (isset($this->_cachedHTAccess[$htaccessPath])) {
            return $this->_cachedHTAccess[$htaccessPath];
        }

        // Save current configuration
        $old_config = $this->getConfig();
        $this->_container->setConfig(new \HTRouter\VarContainer());

        // Read HTACCESS
        $f = fopen($htaccessPath, "r");
        $this->getConfig()->set("HTAccessFileResource", $f);  // temporary saving of the filehandle resource

        // Parse config
        $router = \HTRouter::getInstance();
        $router->parseConfig($f);

        // Remove from config and close file
        $this->getConfig()->clear("HTAccessFileResource");
        fclose($f);

        // Save new config and restore current configuration
        $new_config = $this->getConfig();
        $this->_container->setConfig($old_config);

        // Store this htaccess configuration inside our cache
        $this->_cachedHTAccess[$htaccessPath] = $new_config;

        // Return new configuration
        return $new_config;
    }


    public function getAliases() {
        return array("core.c", "core");
    }

}