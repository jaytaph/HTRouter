<?php
/**
 * Mod_dir.
 */

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Env implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        $router->registerDirective($this, "PassEnv");
        $router->registerDirective($this, "SetEnv");
        $router->registerDirective($this, "UnsetEnv");

        // Register hook
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "envFixup"));
    }

    public function PassEnvDirective(\HTRequest $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $env = trim($env);
            $request->appendPassEnv($env);
        }
    }

    public function SetEnvDirective(\HTRequest $request, $line) {
        $line = trim($line);
        list($key, $val) = explode(" ", $line, 2);
        $key = trim($key);
        $val = trim($val);
        $request->appendSetEnv(array($key, $val));
    }

    public function UnsetEnvDirective(\HTRequest $request, $line) {
        $envs = explode(" ", $line);
        foreach ($envs as $env) {
            $request->appendUnsetEnv($env);
        }
    }

    public function envFixup(\HTRequest $request) {
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
    }


    public function getName() {
        return "env";
    }

}