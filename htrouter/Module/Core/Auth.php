<?php

namespace HTRouter\Module\Core;
use HTRouter\ModuleInterface;

class Auth implements ModuleInterface {
    protected $_router;

    public function init(\HTRouter $router)
    {
        $router->registerDirective($this, "AuthName");
        $router->registerDirective($this, "AuthType");

        $router->registerHook(\HTRouter::HOOK_CHECK_AUTH, array($this, "checkAuthentication"));

        $this->_router = $router;
    }

    public function checkAuthentication(\HTRequest $request) {
        $plugin = $request->getAuthType();

        print "<h3>Checking request: ".$plugin->getName()." with name: ".$request->getAuthName()."</h3><br>";
        return $plugin->authenticateUser($request);
    }

    public function authNameDirective(\HTRequest $request, $line) {
        print "authNameDirective called ".$line."<br>\n";

        $request->setAuthName($line);
    }

    public function authTypeDirective(\HTRequest $request, $line) {
        print "authTypeDirective called ".$line."<br>\n";

        $name = "auth_".strtolower(trim($line));

        // @TODO: We should check that we have the correct AUTH_* module loaded
        $plugin = $this->_router->findModule($name);
        if (! $plugin) {
            throw new \UnexpectedValueException("Cannot find $name");
        }

        $request->setAuthType($plugin);
    }

    public function getName() {
        return "auth";
    }
}