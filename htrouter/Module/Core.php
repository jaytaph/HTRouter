<?php
/**
 * Core module. Most of these directives we probably won't need.
 */

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Core implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        $router->registerDirective($this, "require");
        $router->registerDirective($this, "satisfy");

        // Default values
        $router->getRequest()->setSatisfy("all");
    }

    public function requireDirective(\HTRequest $request, $line) {
        $request->appendRequire($line);
    }

    public function satisfyDirective(\HTRequest $request, $line) {
        if (strtolower($line) == "all") {
            $request->setSatisfy("all");
        } elseif (strtolower($line) == "any") {
            $request->setSatisfy("any");
        } else {
            throw new \UnexpectedValueException("Satisfy must be either 'all' or 'any'");
        }
    }


    public function getName() {
        return "core";
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
    // Require
    // RLimitCPU
    // RLimitMEM
    // RLimitNPROC
    // Satisfy
    // ScriptInterpreterSource
    // ServerSignature
    // SetHandler
    // SetInputFilter
    // SetOutputFilter

}