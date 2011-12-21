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

        // Add extra info, like authentication and stuff
        $httpHeaders = apache_request_headers();
        if (isset($httpHeaders['Authorization'])) {
            $router->getRequest()->setAuthentication($httpHeaders['Authorization']);
        } else {
            $router->getRequest()->setAuthentication(false);
        }

        $this->_router = $router;
    }

    public function checkAuthentication(\HTRequest $request) {
        // This is our authentication scheme (even when no auth data is given)
        $plugin = $request->getAuthType();

        // Did we supply authentication?
        $auth = $request->getAuthentication();
        if (empty($auth)) {
            // No authentication found
            $result = \AuthModule::AUTH_NOT_FOUND;
        } else {
            // Auth info found. Let's authenticate!
            $result = $plugin->authenticateUser($request);
        }

        if ($result != \AuthModule::AUTH_GRANTED) {
            // Return a 401
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: '.$plugin->getAuthType().' realm="'.$request->getAuthName().'"');
            exit;
        }

        return true;
    }

    public function authNameDirective(\HTRequest $request, $line) {
        $request->setAuthName($line);
    }

    public function authTypeDirective(\HTRequest $request, $line) {
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