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

        // Set default values
        $this->getConfig()->set("PassEnv", array());
        $this->getConfig()->set("SetEnv", array());
        $this->getConfig()->set("UnsetEnv", array());
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
        $this->getConfig()->append("SetEnv", array($key, $val));
    }

    public function UnsetEnvDirective(\HTRouter\Request $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $this->getConfig()->append("UnsetEnv", $env);
        }
    }

    public function envFixup(\HTRouter\Request $request) {
        // Passthrough
        foreach ($this->getConfig()->get("PassEnv") as $env) {
            if (isset($_ENV[$env])) {
                $this->getConfig()->append("Environment", array($env => $_ENV[$env]));
            }
        }

        // Set Env
        foreach ($this->getConfig()->get("SetEnv") as $env) {
            $this->getConfig()->append("Environment", array($env[0] => $env[1]));
        }

        // Unset Env
        foreach ($this->getConfig()->get("UnsetEnv") as $env) {
            $this->getConfig()->clear("Environment", $env);
        }

        // All done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }


    public function getAliases() {
        return array("mod_env.c", "mod_env");
    }

}