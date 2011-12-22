<?php
/**
 * Mod_rewrite. This is probably the most important module (and the whole point of the router to begin with)
 */

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Rewrite implements ModuleInterface {

    public function init(\HTRouter $router)
    {
//        $router->registerDirective($this, "RewriteBase");
//        $router->registerDirective($this, "RewriteCond");
        $router->registerDirective($this, "RewriteEngine");
//        $router->registerDirective($this, "RewriteLock");
        $router->registerDirective($this, "RewriteLog");
        $router->registerDirective($this, "RewriteLogLevel");
//        $router->registerDirective($this, "RewriteMap");
//        $router->registerDirective($this, "RewriteOptions");
//        $router->registerDirective($this, "RewriteRule");
    }

    // Everything with *Directive can be called
    public function rewriteEngineDirective(\HTRequest $request, $line) {
        if (strtolower($line) == "on") {
            $request->setRewriteEngine(true);
        } else if (strtolower($line) == "off") {
            $request->setRewriteEngine(false);
        } else {
            throw new \UnexpectedValueException("Must be on or off");
        }
    }

    public function rewriteLogLevelDirective(\HTRequest $request, $line) {
        if (! is_numeric($line) or $line < 0 or $line > 9) {
            throw new \OutOfRangeException("RewriteLogLevel must be between 0 and 9");
        }
        $request->setRewriteLogLevel($line);
    }

    public function rewriteLogDirective(\HTRequest $request, $line) {
        $request->setRewriteLog($line);
    }


    public function rewriteCondDirective(\HTRequest$request, $line) {
    }

    public function getName() {
        return "rewrite";
    }

}