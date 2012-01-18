<?php
/**
 * Mod_dir.
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Dir extends Module {
    const DEFAULT_DIRECTORY_INDEX_FILE = "index.html";

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register directives
        $router->registerDirective($this, "DirectoryIndex");
        $router->registerDirective($this, "DirectorySlash");
        $router->registerDirective($this, "FallbackResource");

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "dirFixups"), 99);

        // Set default values
        $this->getConfig()->set("DirectorySlash", true);
    }

    public function DirectoryIndexDirective(\HTRouter\Request $request, $line) {
        $localUrls = explode(" ", $line);
        foreach ($localUrls as $url) {
            $this->getConfig()->append("DirectoryIndex", $url);
        }
    }

    public function DirectorySlashDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("on" => true, "off" => false));
        $this->getConfig()->set("DirectorySlash", $value);
    }

    public function FallbackResourceDirective(\HTRouter\Request $request, $line) {
        $this->getConfig()->set("FallbackResource", $line);
    }

    public function mergeConfigs(\HTRouter\VarContainer $base, \HTRouter\VarContainer $add) {
        $base->set("DirectoryIndex", $add->get("DirectoryIndex") ? $add->get("DirectoryIndex") : $base->get("DirectoryIndex"));
        $base->set("DirectorySlash", $add->get("DirectorySlash") == false ? $base->get("DirectorySlash") : $add->get("DirectorySlash"));
        $base->set("FallbackResource", $add->get("FallbackResource") ? $add->get("FallbackResource") : $base->get("FallbackResource"));
    }

    protected function _fixup_dflt(\HTRouter\Request $request) {
        // Do fallback
        $path = $this->getConfig()->get("FallbackResource");
        if ($path == false) {
            return \HTRouter::STATUS_DECLINED;
        }

        $url = $this->_updateUrl($request->getUri(), $path);

        // In case a subrequest throws an error
        $error_notfound = false;

        // @TODO: Sub requests are done more often, we should move this to doSubRequest() in \HTRouter
        $subContainer = $this->_prepareContainerForSubRequest($url);
        $processor = new \HTRouter\Processor($subContainer);
        $status = $processor->processRequest();
        $subrequest = $subContainer->getRequest();
        $subrequest->setStatus($status);

        if (is_file($subrequest->getDocumentRoot() . $subrequest->getFilename())) {
            $this->_container->setRequest($subrequest);
            return \HTRouter::STATUS_OK;
        }

        if ($subrequest->getStatus() >= 300 && $subrequest->getStatus() < 400) {
            $this->_container->setRequest($subrequest);
            return $subrequest->getStatus();
        }

        if ($subrequest->getStatus() != \HTRouter::STATUS_HTTP_NOT_FOUND &&
            $subrequest->getStatus() != \HTRouter::STATUS_HTTP_OK) {
            $error_notfound = $subrequest->getStatus();
        }

        // "error_notfound" is set? return error_notfound
        if ($error_notfound) {
            return $error_notfound;
        }

        // Nothing to be done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }



    protected function _fixup_dir(\HTRouter\Request $request) {
        $utils = new \HTRouter\Utils;

        // Check if it doesn't end on a slash?
        $url = $request->getUri();
        if (!empty($url) and ($url[strlen($url)-1] != '/')) {
            // We are fixing a directory and we aren't allowed to add a slash. No good.
            if ($this->getConfig()->get("DirectorySlash") == false) {
                return \HTRouter::STATUS_DECLINED;
            }

            // Add the extra slash to the URL
            $url = parse_url($url);
            $url['path'] .= "/";
            $url = $utils->unparse_url($url);

            // Redirect permanently new slashed url ( http://example.org/dir => http://example.org/dir/ )
            $request->appendOutHeaders("Location", $url);
            return \HTRouter::STATUS_HTTP_MOVED_PERMANENTLY;
        }

        // In case a subrequest throws an error
        $error_notfound = false;

        // We can safely check and match against our directory index now
        $names = $this->getConfig()->get("DirectoryIndex");
        $names[] = self::DEFAULT_DIRECTORY_INDEX_FILE;        // @TODO: Seriously wrong. This needs to be placed in config?
        foreach ($names as $name) {
            $url = $this->_updateUrl($request->getUri(), $name);

            $subContainer = $this->_prepareContainerForSubRequest($url);
            $processor = new \HTRouter\Processor($subContainer);
            $status = $processor->processRequest();
            $subrequest = $subContainer->getRequest();
            $subrequest->setStatus($status);

            if (is_file($subrequest->getDocumentRoot() . $subrequest->getFilename())) {
                $this->_container->setRequest($subrequest);
                return \HTRouter::STATUS_OK;
            }

            if ($subrequest->getStatus() >= 300 && $subrequest->getStatus() < 400) {
                $this->_container->setRequest($subrequest);
                return $subrequest->getStatus();
            }

            if ($subrequest->getStatus() != \HTRouter::STATUS_HTTP_NOT_FOUND &&
                $subrequest->getStatus() != \HTRouter::STATUS_HTTP_OK) {
                $error_notfound = $subrequest->getStatus();
            }
        }

        // "error_notfound" is set? return error_notfound
        if ($error_notfound) {
            return $error_notfound;
        }

        // Nothing to be done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }

    public function dirFixups(\HTRouter\Request $request) {
        $filename = $request->getFilename();

        if (empty($filename) || is_dir($request->getDocumentRoot() . $filename)) {
            return $this->_fixup_dir($request);
        } elseif (! empty($filename) && ! file_exists($request->getDocumentRoot() . $filename) &&
            @filetype($request->getDocumentRoot() . $filename) == "unknown"
        ) { // @TODO: This must be different FILE_NOT_EXIST
            return $this->_fixup_dflt($request);
        }

        return \HTRouter::STATUS_DECLINED;
    }

    protected function _updateUrl($url, $path) {
        $utils = new \HTRouter\Utils;
        $url = parse_url($url);

        // Is it an absolute url?
        if ($path[0] == "/") {
            $url['path'] = $path;   // Replace
        } else {
            $url['path'] .= $path;  // Append
        }
        $url = $utils->unparse_url($url);
        return $url;
    }


    protected function _prepareContainerForSubRequest($url) {
        $subrequest = clone ($this->_container->getRequest());
        $subrequest->setMainRequest(false);
        $subrequest->setUri($url);
        $subrequest->setFilename(null);

        $subContainer = clone ($this->_container);
        //$subContainer->name = $this->_container->name . " (SubRequest)";
        //$subContainer->setConfig($this->_container->getRouter()->getDefaultConfig());
        $subContainer->setRequest($subrequest);

        return $subContainer;
    }


    public function getAliases() {
        return array("mod_dir.c", "mod_dir", "dir");
    }

}