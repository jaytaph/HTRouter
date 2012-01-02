<?php
/**
 * Alias module
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Alias extends Module {

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register directives
        $router->registerDirective($this, "Redirect");
        $router->registerDirective($this, "RedirectMatch");
        $router->registerDirective($this, "RedirectPermanent");
        $router->registerDirective($this, "RedirectTemp");

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_TRANSLATE_NAME, array($this, "translateName"));
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "fixups"));

        // Set default values
        $container->getConfig()->setRedirects(array());
    }

    public function redirectDirective(\HTRouter\Request $request, $line) {
        $redirect = new \StdClass();

        // We should check if "status" is given. If not, it defaults to 302
        $redirect->http_status = 302;   // temporary status by default

        // parse argument list
        // @TODO better argument splitting!
        $args = preg_split("/\s+/", $line);
        if (count($args) == 3) {
            // We have 3 arguments, which means the first argument is the 'status'
            $redirect->http_status = 0;
            if (strtolower($args[0]) == "permanent") {
                $redirect->http_status = 301;
            } elseif (strtolower($args[0]) == "temp") {
                $redirect->http_status = 302;
            } elseif (strtolower($args[0]) == "seeother") {
                $redirect->http_status = 303;
            } elseif (strtolower($args[0]) == "gone" && count(args) == 2) {
                // Gone does not have 3 arguments, but 2!
                $redirect->http_status = 410;
            } elseif (is_numeric($args[0]) && $args[0] >= 300 && $args[0] <= 399) {
                $redirect->http_status = $args[0];
            }

            if ($redirect->http_status == 0) {
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
        $this->getConfig()->appendRedirects($redirect);
    }

    public function redirectMatchDirective(\HTRouter\Request $request, $line) {
        // @TODO: Fill this
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
        // Need an (absolute) url
        $uri = $request->getUri();
        if (empty($uri) || $uri[0] != '/') {
            return \HTRouter::STATUS_DECLINED;
        }

        // check if name matches one of the redirects
        foreach ($this->getConfig()->getRedirects() as $redirect) {
            // @TODO: Check if this is OK?
            $pos = strpos($request->getUri(), $redirect->urlpath);
            if ($pos === 0) {
                $url = $redirect->url . substr($request->getUri(), strlen($redirect->urlpath));
                $qs = $request->getQueryString();
                if (! empty($qs)) {
                    $url .= '?' . $qs;
                }
                $request->appendOutHeaders("Location", $url);
                return $redirect->http_status;
            }
        }

        return \HTRouter::STATUS_DECLINED;
    }

    public function fixups(\HTRouter\Request $request) {
        // @TODO: We need to fix the fixups
        return \HTRouter::STATUS_DECLINED;
    }



    public function getAliases() {
        return array("mod_alias.c", "alias_module");
    }

}