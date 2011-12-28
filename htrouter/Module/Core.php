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

        // Core directives
        $router->registerDirective($this, "require");
        $router->registerDirective($this, "satisfy");
        $router->registerDirective($this, "<ifmodule");
        $router->registerDirective($this, "AuthName");
        $router->registerDirective($this, "AuthType");

        // Default values
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
            $this->getRouter()->parseConfig($request->config->getHTAccessFileResource(), "</IfModule>");
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

        // Attention: this is set inside the request, not the configuration!
        $request->setAuthType($plugin);
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