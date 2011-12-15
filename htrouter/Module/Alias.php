<?php

namespace HTRouter\Module;
use HTRouter\ModuleInterface;

class Alias implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        $router->registerDirective($this, "Alias");
        $router->registerDirective($this, "AliasMatch");
        $router->registerDirective($this, "Redirect");
        $router->registerDirective($this, "RedirectMatch");
        $router->registerDirective($this, "RedirectPermanent");
        $router->registerDirective($this, "RedirectTemp");
        $router->registerDirective($this, "ScriptAlias");
        $router->registerDirective($this, "ScriptAliasMatch");
    }

    public function aliasDirective($request) {
        print "aliasDirective called ".$request."<br>\n";
    }

    public function aliasMatchDirective($request) {
        print "AliasMatch called ".$request."<br>\n";
    }

}