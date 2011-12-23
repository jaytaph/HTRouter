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
    // RLimitCPU
    // RLimitMEM
    // RLimitNPROC
    // ScriptInterpreterSource
    // ServerSignature
    // SetHandler
    // SetInputFilter
    // SetOutputFilter

}