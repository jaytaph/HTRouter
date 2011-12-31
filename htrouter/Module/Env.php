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
        $this->getConfig()->setPassEnv(array());
        $this->getConfig()->setSetEnv(array());
        $this->getConfig()->setUnsetEnv(array());
    }

    public function PassEnvDirective(\HTRouter\Request $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $env = trim($env);
            $this->getConfig()->appendPassEnv($env);
        }
    }

    public function SetEnvDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        list($key, $val) = explode(" ", $line, 2);
        $key = trim($key);
        $val = trim($val);
        $this->getConfig()->appendSetEnv(array($key, $val));
    }

    public function UnsetEnvDirective(\HTRouter\Request $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $this->getConfig()->appendUnsetEnv($env);
        }
    }

    public function envFixup(\HTRouter\Request $request) {
        // Passthrough
        foreach ($this->getConfig()->getPassEnv() as $env) {
            if (isset($_ENV[$env])) {
                $this->getConfig()->appendEnvironment($env, $_ENV[$env]);
            }
        }

        // Set Env
        foreach ($this->getConfig()->getSetEnv() as $env) {
            $this->getConfig()->appendEnvironment($env[0], $env[1]);
        }

        // Unset Env
        foreach ($this->getConfig()->getUnsetEnv() as $env) {
            $this->getConfig()->removeEnvironment($env);
        }

        // All done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }


    public function getAliases() {
        return array("mod_env.c", "mod_env");
    }

}