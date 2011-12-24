<?php
/**
 * Mod_dir.
 */

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class SetEnvIf implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        $router->registerDirective($this, "BrowserMatch");
        $router->registerDirective($this, "BrowserMatchNoCase");
        $router->registerDirective($this, "SetEnvIf");
        $router->registerDirective($this, "SetEnvIfNoCase");

        // Register hook
        $router->registerHook(\HTRouter::HOOK_HEADER_PARSER, array($this, "matchHeaders"));
        $router->registerHook(\HTRouter::HOOK_POST_READ_REQUEST, array($this, "matchHeaders"));
    }

    public function BrowserMatchDirective(\HTRequest $request, $line) {
        $this->SetEnvIfDirective($request, "User-Agent ".$line);
    }

    /**
     *
     * These are the same:
     *   BrowserMatchNoCase Robot is_a_robot
     *   SetEnvIfNoCase User-Agent Robot is_a_robot
     *
     * @param \HTRequest $request
     * @param $line
     */
    public function BrowserMatchNoCaseDirective(\HTRequest $request, $line) {
        $this->SetEnvIfNoCaseDirective($request, "User-Agent ".$line);
    }

    public function SetEnvIfDirective(\HTRequest $request, $line) {
        $entry = $this->_parseLine($request, $line, false);
        $request->appendSetEnvIf($entry);
    }

    public function SetEnvIfNoCaseDirective(\HTRequest $request, $line) {
        $this->_parseLine($request, $line, true);
        $request->appendSetEnvIf($entry);
    }

    protected function _parseLine(\HTRequest $request, $line, $nocase) {
        $args = explode(" ", $line);

        $entry = new \StdClass();
        $entry->nocase = $nocase;

        $entry->attribute = array_shift($args);
        $entry->attribute = trim($entry->attribute, "\"");  // Remove quotes (@TODO: Have a better parser that does this!)
        $entry->attribute_is_regex = $this->_isRegex($entry->attribute);
        $entry->regex = array_shift($args);
        $entry->regex = trim($entry->regex, "\"");  // Remove quotes (@TODO: Have a better parser that does this!)
        $entry->envs = $args;

        return $entry;
    }


    function matchHeaders(\HTRequest $request) {
        foreach ($request->getSetEnvIf() as $entry) {
            $val = "";
            switch (strtolower($entry->attribute)) {
                case "remote_host" :
                    $val = $_SERVER['REMOTE_HOST'];
                    break;
                case "remote_addr" :
                    $val = $_SERVER['REMOTE_ADDR'];
                    break;
                case "server_addr" :
                    $val = $_SERVER['SERVER_ADDR'];
                    break;
                case "request_method" :
                    $val = $_SERVER['REQUEST_METHOD'];
                    break;
                case "request_protocol" :
                    $val = $_SERVER['REQUEST_PROTOCOL'];
                    break;
                case "request_uri" :
                    $val = $_SERVER['REQUEST_URI'];
                    break;
                default :
                    // Match all headers until we find a match
                    $tmp = array_merge($request->getHeaders(), $request->getEnvironment());
                    foreach ($tmp as $header => $value) {
                        if ($entry->attribute_is_regex) {
                            // Match against regex

                            $regex = "/".$entry->attribute."/";
                            if (preg_match($regex, $header)) {
                                // Match!
                                $val = $header;
                                break;
                            }
                        } else {
                            // Match direct
                            if (strcmp($entry->attribute, $header) == 0) {
                                // Match!
                                $val = $header;
                                break;
                            }
                        }

                    }
                    break;
            }

            // Found a correct value, not check against the actual regex
            $regex = "/".$entry->regex."/";
            if ($entry->nocase) $regex .= "i";

            if (preg_match($regex, $val)) {
                $this->_addMatch($request, $entry);
            }

        }
    }

    /**
     * Returns true if this LOOKS like a regex (anything with 'strange' characters, basically)
     * @param $regex
     * @return int
     */
    protected function _isRegex($regex) {
        return (! preg_match("/^[A-Za-z0-9_]*$/", $regex));
    }

    protected function _addMatch(\HTRequest $request, \stdClass $entry) {
        foreach ($entry->envs as $env) {
            if ($env[0] == "!") {
                // Unset !ENV
                $env = substr($env, 1);
                $request->removeEnvironment($env);
                continue;
            } elseif (strstr($env,"=")) {
                // Set ENV=TEST  to ENV=>TEST
                list($key,$val) = explode("=", $env);
                $request->appendEnvironment($key, $val);
            } else {
                // Set ENV  to ENV=>1
                $request->appendEnvironment($env, 1);
            }
        }
    }


    public function getName() {
        return "setenvif";
    }

}