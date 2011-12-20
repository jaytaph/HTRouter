<?php

namespace HTRouter\Module\Authn;
use HTRouter\ModuleInterface;

class File implements ModuleInterface {

    protected $_htpasswd = array();

    public function init(\HTRouter $router)
    {
        $router->registerDirective($this, "AuthUserFile");

        $router->registerHook(\HTRouter::HOOK_PROVIDER_GROUP, array($this, "checkPassword"));
        $router->registerHook(\HTRouter::HOOK_PROVIDER_GROUP, array($this, "checkRealm"));
    }

    public function authUserFileDirective(\HTRequest $request, $line) {
        print "authUserFileDirective called ".$line."<br>\n";
        if (! is_readable($line)) {
            throw new \RuntimeException("Cannot read authfile: $line");
        }

        $request->setAuthUserFile($line);
    }


    function checkRealm (\HTRequest $request) {
        print "Checking realm";
    }

    function checkPassword (\HTRequest $request) {
        print "Checking password";
    }

    public function getName() {
        return "authn_file";
    }


}