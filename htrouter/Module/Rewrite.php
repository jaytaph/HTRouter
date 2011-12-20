<?php

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
            print "engine on";
            $request->setRewriteEngine(true);
        }
        if (strtolower($line) == "off") {
            print "engine off";
            $request->setRewriteEngine(false);
        }
    }

    public function rewriteLogLevelDirective(\HTRequest $request, $line) {
        print "RewriteLogLevel called ".$line."<br>\n";
        if (! is_numeric($line) or $line < 0 or $line > 9) {
            throw new \OutOfRangeException("RewriteLogLevel must be between 0 and 9");
        }
        $request->setRewriteLogLevel($line);
    }

    public function rewriteLogDirective(\HTRequest $request, $line) {
        print "RewriteLog called ".$line."<br>\n";
        $request->setRewriteLog($line);
    }


    public function rewriteCondDirective(\HTRequest$request, $line) {
        print "rewriteCondDirective called ".$line."<br>\n";
    }

    public function getName() {
        return "rewrite";
    }

}