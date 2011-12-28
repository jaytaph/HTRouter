<?php
/**
 * Core module. Most of these directives we probably won't need.
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Core extends Module {

    public function init(\HTRouter $router)
    {
        parent::init($router);

        // Register core directives
        $router->registerDirective($this, "require");
        $router->registerDirective($this, "satisfy");
        $router->registerDirective($this, "<ifmodule");
        $router->registerDirective($this, "AuthName");
        $router->registerDirective($this, "AuthType");

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_MAP_TO_STORAGE, array($this, "coreMapToStorage"), 100); // Really last!
//        $router->registerHook(\HTRouter::HOOK_MAP_TO_STORAGE, array($this, "translateName"), 100); // Really last!


        // Set default values
        $router->getRequest()->config->setSatisfy("all");
    }

    public function requireDirective(\HTRouter\Request $request, $line) {
        $request->config->appendRequire($line);
    }

    public function satisfyDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("all" => "all", "any" => "any"));
        $request->config->setSatisfy($value);
    }

    public function gt_ifmoduleDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        if ($line[strlen($line)-1] != '>') {
            throw new \UnexpectedValueException("No > found");
        }

        $module = str_replace(">", "", $line);

        // Check if module exists
        if (! $this->getRouter()->findModule($module)) {
            // Module does not exist, so skip this configuration block
            $this->getRouter()->skipConfig($request->config->getHTAccessFileResource(), "</IfModule>");
        } else {
            // Module does exist, read this configuration block
            $this->getRouter()->parseConfig($request, $request->config->getHTAccessFileResource(), "</IfModule>");
        }
    }

    public function authNameDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        $line = trim($line, "\"\'");
        $request->config->setAuthName($line);
    }

    public function authTypeDirective(\HTRouter\Request $request, $line) {
        $name = "auth_".strtolower(trim($line));

        // @TODO: We should check that we have the correct AUTH_* module loaded
        $plugin = $this->getRouter()->findModule($name);
        if (! $plugin) {
            throw new \UnexpectedValueException("Cannot find $name");
        }

        $request->config->setAuthType($plugin);
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
        // No file found!?
        $fn = $request->getFilename();
        if (empty($fn)) {
            return \HTRouter::STATUS_OK;
        }

//        // Path is absolute. Strip docroot
//        if (strpos($fn, $request->getDocumentRoot()) === 0) {
//            $fn = substr($fn, strlen($request->getDocumentRoot()));
//        }

        // @TODO: We should stop until we have reached the documentroot!?

        $config = $request->getMainConfig();
        if (isset ($config['global']['htaccessfilename'])) {
            $htaccessFilename = $config['global']['htaccessfilename'];
        } else {
            $htaccessFilename = \HTRouter::HTACCESS_FILE;
        }

        /* Create an array with the following paths:
         *   /
         *   /wwwroot
         *   /wwwroot/router
         *   /wwwroot/router/public
         * So we can do a simple iteration without worrying about stuff
         */
        $dirs = array();
        $path = explode("/", dirname($fn));
        while (count($path) > 0) {
            $dirs[] = $request->getDocumentRoot() . join("/", $path);
            array_pop($path);
        }
        //$dirs = array_reverse($dirs);

        // Iterate directories and find htaccess files
        foreach ($dirs as $dir) {
            $htaccessPath = $dir ."/".$htaccessFilename;
            print "HTACCESS found at $htaccessPath ? : ".(is_readable($htaccessPath) ? "yes" : "no")."<br>\n";

            if (is_readable($htaccessPath)) {
                // Read HTACCESS and merge information
                $new_config = $this->_readHTAccess($request, $htaccessPath);

                // Merge together with current request
                $request->config->merge($new_config);
            }
        }

        return \HTRouter::STATUS_OK;
    }

    protected function _fileWalk(\HTRouter\Request $request) {
        // @TODO: Create me
        return \HTRouter::STATUS_OK;
    }


    protected function _readHTAccess(\HTRouter\Request $request, $htaccessPath) {
        // Note the cloning!
        $new_request = clone $request;
        $new_request->config = new \HTRouter\VarContainer();

        // Read HTACCESS
        $f = fopen($htaccessPath, "r");
        $new_request->config->setHTAccessFileResource($f);  // temporary saving of the filehandle resource

        // Parse config
        $this->getRouter()->parseConfig($new_request, $f);

        // Remove from config and close file
        $new_request->config->unsetHTAccessFileResource();
        fclose($f);

        return $new_request->config;
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