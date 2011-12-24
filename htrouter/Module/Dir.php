<?php
/**
 * Mod_dir.
 */

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Dir implements ModuleInterface {
    const DEFAULT_DIRECTORY_INDEX_FILE = "index.html";

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        $router->registerDirective($this, "DirectoryIndex");
        $router->registerDirective($this, "DirectorySlash");
        $router->registerDirective($this, "FallbackResource");

        // Register hook
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "dirFixups"), 99);

        $router->getRequest()->setDirectorySlash(true);
    }

    public function DirectoryIndexDirective(\HTRequest $request, $line) {
        $localUrls = explode(" ", $line);
        foreach ($localUrls as $url) {
            $request->appendDirectoryIndex($url);
        }
    }

    public function DirectorySlashDirective(\HTRequest $request, $line) {
        $utils = new \HTUtils();
        $value = $utils->fetchDirectiveFlags($line, array("on" => true, "off" => false));
        $request->setDirectorySlash($value);
    }

    public function FallbackResourceDirective(\HTRequest $request, $line) {
    }

    /**
     * Honestly, I have no idea what DFLT stands for :(
     * @param \HTRequest $request
     */
    public function fixup_dflt(\HTRequest $request) {

    }

    public function fixup_dir(\HTRequest $request) {
        $utils = new \HTUtils();

        $url = $request->getUri();

        // Check if it doesn't end on a slash?
        if (!empty($url) and ($url[strlen($url)-1] != '/')) {
            // We are fixing a directory and we aren't allowed to add a slash. No good.
            if ($request->getDirectorySlash() == false) {
                return false;
            }

            // Add the extra slash to the URL
            $url = parse_url($url);
            $url['path'] .= "/";
            $url = $utils->unparse_url($url);

            // Redirect permanently new slashed url ( http://example.org/dir => http://example.org/dir/ )
            $this->_router->createRedirect(302, "Moved permanently", $url);
            exit;
        }

        // We can safely check and match against our directory index now
        $names = $request->getDirectoryIndex();
        $names[] = self::DEFAULT_DIRECTORY_INDEX_FILE;        // @TODO: Seriously wrong. This needs to be placed in config?
        foreach ($names as $name) {
            $url = parse_url($request->getUri());

            // Is it an absolute url?
            if ($name[0] == "/") {
                $url['path'] = $name;   // Replace
            } else {
                $url['path'] .= $name;  // Append
            }
            $url = $utils->unparse_url($url);

            if ($utils->findUriFileType($request, $url) != \HTUtils::URI_FILETYPE_MISSING) {
                $request->setUri($url);
                return true;
            }
        }

        // Nothing found
        return false;
    }

    public function dirFixups(\HTRequest $request) {
        $utils = new \HTUtils();

        $url = $request->getUri();
        $type = $utils->findUriFileType($request, $url);

        if ($type == \HTUtils::URI_FILETYPE_DIR) {
            return $this->fixup_dir($request);
        } elseif ($type == \HTUtils::URI_FILETYPE_MISSING) {        // We skip alternate handling (like /server-status etc)
            return $this->fixup_dflt($request);
        }

        // It's possibly an existing file. We don't need to do any translations to it
        return false;
    }

    public function getName() {
        return "dir";
    }

}