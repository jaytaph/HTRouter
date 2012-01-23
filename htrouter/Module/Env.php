<?php
/**
 * Mod_dir.
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Env extends Module {

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register directives
        $router->registerDirective($this, "PassEnv");
        $router->registerDirective($this, "SetEnv");
        $router->registerDirective($this, "UnsetEnv");

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "envFixup"));
    }

    public function PassEnvDirective(\HTRouter\Request $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $env = trim($env);
            $this->getConfig()->append("PassEnv", $env);
        }
    }

    public function SetEnvDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        list($key, $val) = explode(" ", $line, 2);
        $key = trim($key);
        $val = trim($val);
        $this->getConfig()->append("SetEnv", $key, $val);
    }

    public function UnsetEnvDirective(\HTRouter\Request $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $this->getConfig()->append("UnsetEnv", $env);
        }
    }

    public function mergeConfigs(\HTRouter\VarContainer $base, \HTRouter\VarContainer $add) {
        $merged = array_merge($add->get("PassEnv", array()), $base->get("PassEnv", array()));
        if (count($merged)) $base->set("PassEnv", $merged);

        $merged = array_merge($add->get("SetEnv", array()), $base->get("SetEnv", array()));
        if (count($merged)) $base->set("SetEnv", $merged);

        $merged = array_merge($add->get("UnsetEnv", array()), $base->get("UnsetEnv", array()));
        if (count($merged)) $base->set("UnsetEnv", $merged);
    }

    public function envFixup(\HTRouter\Request $request) {
        // Passthrough
        foreach ($this->getConfig()->get("PassEnv", array()) as $env) {
            if (isset($_ENV[$env])) {
                $this->getRouter()->setEnvironment($env, $_ENV[$env]);
            }
        }

        // Set Env
        foreach ($this->getConfig()->get("SetEnv", array()) as $key => $val) {
            $this->getRouter()->setEnvironment($key, $val);
        }

        // Unset Env
        foreach ($this->getConfig()->get("UnsetEnv", array()) as $env) {
            $this->getRouter()->unsetEnvironment($env);
        }

        // All done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }


    public function getAliases() {
        return array("mod_env.c", "mod_env");
    }

}