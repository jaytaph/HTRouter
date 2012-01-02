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
        $this->getConfig()->setDirectorySlash(true);
    }

    public function DirectoryIndexDirective(\HTRouter\Request $request, $line) {
        $localUrls = explode(" ", $line);
        foreach ($localUrls as $url) {
            $this->getConfig()->appendDirectoryIndex($url);
        }
    }

    public function DirectorySlashDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("on" => true, "off" => false));
        $this->getConfig()->setDirectorySlash($value);
    }

    public function FallbackResourceDirective(\HTRouter\Request $request, $line) {
        $this->getConfig()->setFallbackResource($line);
    }

    protected function _fixup_dflt(\HTRouter\Request $request) {
        // Do fallback
        $path = $this->getConfig()->getFallbackResource();
        if ($path == false) {
            return \HTRouter::STATUS_DECLINED;
        }

        $url = $this->_updateUrl($request->getUri(), $path);

        // In case a subrequest throws an error
        $error_notfound = false;

        $subContainer = $this->getRouter()->prepareContainerForSubRequest($url);
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

        $url = $request->getUri();

        // Check if it doesn't end on a slash?
        if (!empty($url) and ($url[strlen($url)-1] != '/')) {
            // We are fixing a directory and we aren't allowed to add a slash. No good.
            if ($this->getConfig()->getDirectorySlash() == false) {
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
        $names = $this->getConfig()->getDirectoryIndex();
        $names[] = self::DEFAULT_DIRECTORY_INDEX_FILE;        // @TODO: Seriously wrong. This needs to be placed in config?
        foreach ($names as $name) {
            $url = $this->_updateUrl($request->getUri(), $name);

            $subContainer = $this->getRouter()->prepareContainerForSubRequest($url);
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
        } elseif (! empty($filename) && ! file_exists($request->getDocumentRoot() . $filename)) { // @TODO: This must be different FILE_NOT_EXIST
            return $this->_fixup_dflt($request);
        }

        return \HTRouter::STATUS_DECLINED;
    }

    public function getAliases() {
        return array("mod_dir.c", "mod_dir");
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

}