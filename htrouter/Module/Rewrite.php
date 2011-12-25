<?php
/**
 * Mod_rewrite. This is probably the most important module (and the whole point of the router to begin with)
 */

namespace HTRouter\Module;
use HTRouter\Module;

class Rewrite extends Module {

    public function init(\HTRouter $router)
    {
        parent::init($router);

        $router->registerDirective($this, "RewriteEngine");
        $router->registerDirective($this, "RewriteLog");
        $router->registerDirective($this, "RewriteLogLevel");
    }

    // Everything with *Directive can be called
    public function rewriteEngineDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("on" => true, "off" => false));
        $request->setRewriteEngine($value);
    }

    public function rewriteLogLevelDirective(\HTRouter\Request $request, $line) {
        if (! is_numeric($line) or $line < 0 or $line > 9) {
            throw new \OutOfRangeException("RewriteLogLevel must be between 0 and 9");
        }
        $request->setRewriteLogLevel($line);
    }

    public function rewriteLogDirective(\HTRouter\Request $request, $line) {
        $request->setRewriteLog($line);
    }


    public function rewriteCondDirective(\HTRouter\Request$request, $line) {
    }

    public function getName() {
        return "rewrite";
    }

    public function getAliases()
    {
        return array("mod_rewrite.c", "module_rewrite", "rewriteModule");
    }

}