<?php
/**
 * Core module. Most of these directives we probably won't need.
 */

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Core implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        $router->registerDirective($this, "require");
        $router->registerDirective($this, "satisfy");
        $router->registerDirective($this, "<ifmodule");

        // Default values
        $router->getRequest()->setSatisfy("all");

        // Set the (default) request URI. This might be changed or rewritten
        $router->getRequest()->setURI($_SERVER['REQUEST_URI']);
    }

    public function requireDirective(\HTRequest $request, $line) {
        $request->appendRequire($line);
    }

    public function satisfyDirective(\HTRequest $request, $line) {
        $utils = new \HTUtils();
        $value = $utils->fetchDirectiveFlags($line, array("all" => "all", "any" => "any"));
        $request->setSatisfy($value);
    }

    public function gt_ifmoduleDirective(\HTRequest $request, $line) {
        $line = trim($line);
        if ($line[strlen($line)-1] != '>') {
            throw new \UnexpectedValueException("No > found");
        }

        $module = str_replace(">", "", $line);

        // Check if module exists
        if (! $this->_router->findModule($module)) {
            // Module does not exist, so skip this configuration block
            $this->_router->skipConfig($request->getHTAccessFileResource(), "</IfModule>");
        } else {
            // Module does exist, read this configuration block
            $this->_router->parseConfig($request->getHTAccessFileResource(), "</IfModule>");
        }

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
    // <IfModule>
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