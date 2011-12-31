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


    public function fixup_dir(\HTRouter\Request $request) {
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
            $this->getRouter()->createRedirect(302, "Moved permanently", $url);
            exit;
        }

        // We can safely check and match against our directory index now
        $names = $this->getConfig()->getDirectoryIndex();
        $names[] = self::DEFAULT_DIRECTORY_INDEX_FILE;        // @TODO: Seriously wrong. This needs to be placed in config?
        foreach ($names as $name) {
            $url = $this->_updateUrl($request->getUri(), $name);
            if ($utils->findUriFileType($request, $url) != \HTRouter\Utils::URI_FILETYPE_MISSING) {
                $request->setUri($url);
                return \HTRouter::STATUS_DECLINED;
            }
        }

        // Nothing found

        // All done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }

    public function dirFixups(\HTRouter\Request $request) {
        $utils = new \HTRouter\Utils;

        $url = $request->getUri();
        $type = $utils->findUriFileType($request, $url);

        if ($type == \HTRouter\Utils::URI_FILETYPE_DIR) {
            return $this->fixup_dir($request);
        } elseif ($type == \HTRouter\Utils::URI_FILETYPE_MISSING) {
            // Do fallback
            $path = $this->getConfig()->getFallbackResource();
            if ($path == false) {
                return \HTRouter::STATUS_DECLINED;
            }
            $url = $this->_updateUrl($request->getUri(), $path);

            $type = $utils->findUriFileType($request, $url);
            if ($type == \HTRouter\Utils::URI_FILETYPE_MISSING) {
                $request->appendOutHeaders("Location", $url);
                return \HTRouter::STATUS_HTTP_MOVED_PERMANENTLY;
            }
        } else {
            // We skip alternate handling (like /server-status etc)
        }

        // It's possible an existing file. We don't need to do any translations to it
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