<?php

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Rewrite implements ModuleInterface {

    protected $_engine = false;
    protected $_loglevel = 0;
    protected $_log;

    public function init(\HTRouter $router)
    {
        $router->registerDirective($this, "RewriteBase");
        $router->registerDirective($this, "RewriteCond");
        $router->registerDirective($this, "RewriteEngine");
        $router->registerDirective($this, "RewriteLock");
        $router->registerDirective($this, "RewriteLog");
        $router->registerDirective($this, "RewriteLogLevel");
        $router->registerDirective($this, "RewriteMap");
        $router->registerDirective($this, "RewriteOptions");
        $router->registerDirective($this, "RewriteRule");
    }

    /**
     * We probably want this inside an abstract class
     */
    public function __call($name, $arguments) {
        if (! preg_match("/Directive$/", $name)) {
            throw new \BadMethodCallException("Directive registered, but not declared: ".$name);
        }
    }

    // Everything with *Directive can be called
    public function rewriteEngineDirective($request) {
        if (strtolower($request) == "on") {
            print "engine on";
            $this->_engine = true;
        }
        if (strtolower($request) == "off") {
            print "engine off";
            $this->_engine = false;
        }
    }

    public function rewriteLogLevel($request) {
        print "RewriteLogLevel called ".$request."<br>\n";
        if (! is_numeric($request) or $request < 0 or $request > 9) {
            throw new Exception("RewriteLogLevel must be between 0 and 9");
        }

        $this->_loglevel = $request;
    }

    public function rewriteLog($request) {
        print "RewriteLog called ".$request."<br>\n";
        $this->_log = $request;
    }


    public function rewriteCondDirective($request) {
        print "rewriteCondDirective called ".$request."<br>\n";
    }

}