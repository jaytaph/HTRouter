<?php
/**
 * Core module. Most of these directives we probably won't need.
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Core extends Module {

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
        $router->registerHook(\HTRouter::HOOK_MAP_TO_STORAGE, array($this, "coreMapToStorage"), 100); // Really last!
        $router->registerHook(\HTRouter::HOOK_TRANSLATE_NAME, array($this, "coreTranslateName"), 100); // Really last!


        // Set default values
        $this->getConfig()->setSatisfy("all");
    }

    public function requireDirective(\HTRouter\Request $request, $line) {
        $this->getConfig()->appendRequire($line);
    }

    public function satisfyDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("all" => "all", "any" => "any"));
        $this->getConfig()->setSatisfy($value);
    }

    public function gt_ifmoduleDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        if ($line[strlen($line)-1] != '>') {
            throw new \UnexpectedValueException("No > found");
        }

        $module = str_replace(">", "", $line);

        // Check if module exists
        $router = \HTRouter::getInstance();
        if (! $router->findModule($module)) {
            // Module does not exist, so skip this configuration block
            $router->skipConfig($this->getConfig()->getHTAccessFileResource(), "</IfModule>");
        } else {
            // Module does exist, read this configuration block
            $router->parseConfig($this->getConfig()->getHTAccessFileResource(), "</IfModule>");
        }
    }

    public function authNameDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        $line = trim($line, "\"\'");
        $this->getConfig()->setAuthName($line);
    }

    public function authTypeDirective(\HTRouter\Request $request, $line) {
        $name = "auth_".strtolower(trim($line));

        // @TODO: We should check that we have the correct AUTH_* module loaded
        $router = \HTRouter::getInstance();
        $plugin = $router->findModule($name);
        if (! $plugin) {
            throw new \UnexpectedValueException("Cannot find $name");
        }

        $this->getConfig()->setAuthType($plugin);
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
        $dirs = array();
        $path = explode("/", dirname($fn));
        if (empty($path[count($path)-1])) array_pop($path);
        while (count($path) > 0) {
            $dirs[] = $request->getDocumentRoot() . join("/", $path);
            array_pop($path);
        }
        $dirs = array_unique($dirs);    // Just in case...

        // Iterate directories and find htaccess files
        foreach ($dirs as $dir) {
            $htaccessPath = $dir ."/".$htaccessFilename;
            print "\n\n<b>HTACCESS found at $htaccessPath ? : ".(is_readable($htaccessPath) ? "yes" : "no")."</b><br>\n";

            if (is_readable($htaccessPath)) {
                // Read HTACCESS and merge information
                $new_config = $this->_readHTAccess($request, $htaccessPath);

                // Merge together with current request
                $this->getConfig()->merge($new_config);
            }
        }

        return \HTRouter::STATUS_OK;
    }

    protected function _fileWalk(\HTRouter\Request $request) {
        // @TODO: Create me
        return \HTRouter::STATUS_OK;
    }


    protected function _readHTAccess(\HTRouter\Request $request, $htaccessPath) {
        // Save current configuration
        $old_config = $this->getConfig();
        $this->_container->setConfig(new \HTRouter\VarContainer());

        // Read HTACCESS
        $f = fopen($htaccessPath, "r");
        $this->getConfig()->setHTAccessFileResource($f);  // temporary saving of the filehandle resource

        // Parse config
        $router = \HTRouter::getInstance();
        $router->parseConfig($f);

        // Remove from config and close file
        $this->getConfig()->unsetHTAccessFileResource();
        fclose($f);

        // Save new config and restore current configuration
        $new_config = $this->getConfig();
        $this->_container->setConfig($old_config);

        // Return new configuration
        return $new_config;
    }






    public function getAliases() {
        return array("core.c", "core");
    }

    // Core Directives (Apache 2.2.x)
    // AcceptPathInfo
    // AccessFileName
    // AddDefaultCharset
    // AddOutputFilterByType
    // AllowOverride
    // CGIMapExtension
    // ContentDigest
    // DefaultType
    // EnableMMAP
    // EnableSendfile
    // ErrorDocument
    // FileETag
    // <Files>
    // <FilesMatch>
    // ForceType
    // <IfDefine>
    // <Limit>
    // <LimitExcept>
    // LimitRequestBody
    // LimitXMLRequestBody
    // Options
    // RLimitCPU
    // RLimitMEM
    // RLimitNPROC
    // ScriptInterpreterSource
    // ServerSignature
    // SetHandler
    // SetInputFilter
    // SetOutputFilter

}