<?php
/**
 * Core module. Most of these directives we probably won't need.
 */

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Core implements ModuleInterface {

    public function init(\HTRouter $router)
    {
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