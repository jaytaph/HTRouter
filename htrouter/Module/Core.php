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

        $router->registerDirective($this, "require");
        $router->registerDirective($this, "satisfy");
        $router->registerDirective($this, "<ifmodule");

        // Default values
        $router->getRequest()->setSatisfy("all");

        // Set the (default) request URI. This might be changed or rewritten
        $router->getRequest()->setURI($_SERVER['REQUEST_URI']);
    }

    public function requireDirective(\HTRouter\Request $request, $line) {
        $request->appendRequire($line);
    }

    public function satisfyDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("all" => "all", "any" => "any"));
        $request->setSatisfy($value);
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
            $this->getRouter()->skipConfig($request->getHTAccessFileResource(), "</IfModule>");
        } else {
            // Module does exist, read this configuration block
            $this->getRouter()->parseConfig($request->getHTAccessFileResource(), "</IfModule>");
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