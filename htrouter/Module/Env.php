<?php
/**
 * Mod_dir.
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Env extends Module {

    public function init(\HTRouter $router)
    {
        parent::init($router);

        $router->registerDirective($this, "PassEnv");
        $router->registerDirective($this, "SetEnv");
        $router->registerDirective($this, "UnsetEnv");

        // Register hook
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "envFixup"));


        $router->getRequest()->setPassEnv(array());
        $router->getRequest()->setSetEnv(array());
        $router->getRequest()->setUnsetEnv(array());
    }

    public function PassEnvDirective(\HTRouter\Request $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $env = trim($env);
            $request->appendPassEnv($env);
        }
    }

    public function SetEnvDirective(\HTRouter\Request $request, $line) {
        $line = trim($line);
        list($key, $val) = explode(" ", $line, 2);
        $key = trim($key);
        $val = trim($val);
        $request->appendSetEnv(array($key, $val));
    }

    public function UnsetEnvDirective(\HTRouter\Request $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $request->appendUnsetEnv($env);
        }
    }

    public function envFixup(\HTRouter\Request $request) {
        // Passthrough
        foreach ($request->getPassEnv() as $env) {
            if (isset($_ENV[$env])) {
                $request->appendEnvironment($env, $_ENV[$env]);
            }
        }

        // Set Env
        foreach ($request->getSetEnv() as $env) {
            $request->appendEnvironment($env[0], $env[1]);
        }

        // Unset Env
        foreach ($request->getUnsetEnv() as $env) {
            $request->removeEnvironment($env);
        }

        // All done. Proceed to next module
        return \HTRouter::STATUS_DECLINED;
    }


    public function getAliases() {
        return array("mod_env.c", "mod_env");
    }

}