<?php
/**
 * Mod_dir.
 */

namespace HTRouter\Module;
use HTRouter\Module;

class SetEnvIf extends Module {

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register directives
        $router->registerDirective($this, "BrowserMatch");
        $router->registerDirective($this, "BrowserMatchNoCase");
        $router->registerDirective($this, "SetEnvIf");
        $router->registerDirective($this, "SetEnvIfNoCase");

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_HEADER_PARSER, array($this, "matchHeaders"));
        $router->registerHook(\HTRouter::HOOK_POST_READ_REQUEST, array($this, "matchHeaders"));

//        // Set default values
//        $this->getConfig()->set("SetEnvIf", array());
    }

    public function BrowserMatchDirective(\HTRouter\Request $request, $line) {
        $this->SetEnvIfDirective($request, "User-Agent ".$line);
    }

    /**
     *
     * These are the same:
     *   BrowserMatchNoCase Robot is_a_robot
     *   SetEnvIfNoCase User-Agent Robot is_a_robot
     *
     * @param \HTRouter\Request $request
     * @param $line
     */
    public function BrowserMatchNoCaseDirective(\HTRouter\Request $request, $line) {
        $this->SetEnvIfNoCaseDirective($request, "User-Agent ".$line);
    }

    public function SetEnvIfDirective(\HTRouter\Request $request, $line) {
        $entry = $this->_parseLine($request, $line, false);
        $this->getConfig()->append("SetEnvIf", $entry);
    }

    public function SetEnvIfNoCaseDirective(\HTRouter\Request $request, $line) {
        $entry = $this->_parseLine($request, $line, true);
        $this->getConfig()->append("SetEnvIf", $entry);
    }

    public function mergeConfigs(\HTRouter\VarContainer $base, \HTRouter\VarContainer $add) {
        $merged = array_merge($add->get("SetEnvIf", array()), $base->get("SetEnvIf", array()));
        if (count($merged)) $base->set("SetEnvIf", $merged);
    }


    protected function _parseLine(\HTRouter\Request $request, $line, $nocase) {
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


    function matchHeaders(\HTRouter\Request $request) {
        foreach ($this->getConfig()->get("SetEnvIf", array()) as $entry) {
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
                    $tmp = array_merge($request->getInHeaders(), $this->getRouter()->getEnvironment());
                    foreach ($tmp as $header => $value) {
                        if ($entry->attribute_is_regex) {
                            // Match against regex

                            $regex = "/".$entry->attribute."/";
                            if (preg_match($regex, $header)) {
                                // Match!
                                $val = $value;
                                break;
                            }
                        } else {
                            // Match direct
                            if (strcmp($entry->attribute, $header) == 0) {
                                // Match!
                                $val = $value;
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

        // All done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }

    /**
     * Returns true if this LOOKS like a regex (anything with 'strange' characters, basically)
     * @param $regex
     * @return int
     */
    protected function _isRegex($regex) {
        return (! preg_match("/^[A-Za-z0-9_]*$/", $regex));
    }

    protected function _addMatch(\HTRouter\Request $request, \stdClass $entry) {
        foreach ($entry->envs as $env) {
            if ($env[0] == "!") {
                // Unset !ENV
                $env = substr($env, 1);
                $this->getRouter()->unsetEnvironment($env);
                continue;
            } elseif (strstr($env,"=")) {
                // Set ENV=TEST  to ENV=>TEST
                list($key,$val) = explode("=", $env);
                $this->getRouter()->setEnvironment($key, $val);
            } else {
                // Set ENV  to ENV=>1
                $this->getRouter()->setEnvironment($env, 1);
            }
        }
    }


    public function getAliases() {
        return array("mod_setenvif.c", "mod_setenvif");
    }

}