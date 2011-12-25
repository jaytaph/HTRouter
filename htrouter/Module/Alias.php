<?php
/**
 * Alias module
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Alias extends Module {

    public function init(\HTRouter $router)
    {
        parent::init($router);

        $router->registerDirective($this, "Redirect");
        $router->registerDirective($this, "RedirectMatch");
        $router->registerDirective($this, "RedirectPermanent");
        $router->registerDirective($this, "RedirectTemp");

        $router->registerHook(\HTRouter::HOOK_TRANSLATE_NAME, array($this, "translateName"));
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "fixups"));
    }

    public function redirectDirective(\HTRouter\Request $request, $line) {
        $redirect = new \StdClass();

        // We should check if "status" is given. If not, it defaults to 302
        $redirect->http_status = 302;   // temporary status by default
        $redirect->http_status = "Found";

        // parse argument list
        $args = explode(" ", $line);
        if (count($args) == 3) {
            // We have 3 arguments, which means the first argument is the 'status'
            $redirect->http_status = 0;
            if (strtolower($args[0]) == "permanent") {
                $redirect->http_code = 301;
                $redirect->http_status = "Moved permanently";
            } elseif (strtolower($args[0]) == "temp") {
                $redirect->http_code = 302;
                $redirect->http_status = "Found";
            } elseif (strtolower($args[0]) == "seeother") {
                $redirect->http_code = 303;
                $redirect->http_status = "See Other";
            } elseif (strtolower($args[0]) == "gone" && count(args) == 2) {
                // Gone does not have 3 arguments, but 2!
                $redirect->http_status = 410;
                $redirect->http_status = "Gone";
            } elseif (is_numeric($args[0]) && $args[0] >= 300 && $args[0] <= 399) {
                $redirect->http_code = $args[0];
                $redirect->http_status = "Moved"; // @TODO: what is the default status we return?
            }

            if ($redirect->http_code == 0) {
                throw new \UnexpectedValueException("redirect does not have correct first argument (of three)");
            }

            // Remove "status" from the list. Now we only have 2 arguments!
            array_shift($args);
        }

        // Check the url path
        $redirect->urlpath = $args[0];
        if ($redirect->urlpath[0] != '/') {
            throw new \UnexpectedValueException("URL path needs to be an absolute path");
        }

        // Check the url (if available)
        if (isset($args[1])) {
            $redirect->url = $args[1];
            $utils = new \HTRouter\Utils;
            if (! $utils->isUrl($redirect->url)) {
                throw new \UnexpectedValueException("URL needs to be an actual URL (http://...)");
            }
        }

        // Add to the list
        $request->appendRedirects($redirect);
    }

    public function redirectMatchDirective(\HTRouter\Request $request, $line) {
    }

    public function redirectPermanentDirective(\HTRouter\Request $request, $line) {
        // It's the same as "redirect permanent ..."
        $this->redirectDirective($request, "permanent ".$line);
    }

    public function redirectTempDirective(\HTRouter\Request $request, $line) {
        // It's the same as "redirect temp ..."
        $this->redirectDirective($request, "temp ".$line);
    }

    public function translateName(\HTRouter\Request $request) {
        // check if name matches one of the redirects
        foreach ($request->getRedirects() as $redirect) {
            $pos = strpos($request->getURI(), $redirect->urlpath);
            if ($pos === 0) {
                $this->getRouter()->createRedirect($redirect->http_code, $redirect->http_status, $redirect->url);
                exit;
                //$request->setURI($redirect->url);
            }
        }
    }

    public function fixups(\HTRouter\Request $request) {
        // @TODO: We need to fix the fixups
    }



    public function getAliases() {
        return array("mod_alias.c", "alias_module");
    }

}