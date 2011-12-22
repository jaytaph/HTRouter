<?php

namespace HTRouter\Module\Core;
use HTRouter\ModuleInterface;

class Auth implements ModuleInterface {
    protected $_router;

    public function init(\HTRouter $router)
    {
        $router->registerDirective($this, "AuthName");
        $router->registerDirective($this, "AuthType");

        $router->registerHook(\HTRouter::HOOK_CHECK_AUTHN, array($this, "checkAuthentication"));
        $router->registerHook(\HTRouter::HOOK_CHECK_AUTHZ, array($this, "checkAuthorization"));

        // Add extra info, like authentication and stuff
        $httpHeaders = apache_request_headers();
        if (isset($httpHeaders['Authorization'])) {
            $router->getRequest()->setAuthentication($httpHeaders['Authorization']);
        } else {
            $router->getRequest()->setAuthentication(false);
        }

        $this->_router = $router;
    }

    public function checkAuthorization(\HTRequest $request) {
        // Iterator through all the registered providers
        $providers = $this->_router->getProviders(\HTRouter::PROVIDER_AUTHZ_GROUP);
        foreach ($providers as $provider) {
            $result = $provider->checkUserAccess($request);

            // Denied or granted means we should stop processing.
            if ($result == \AuthModule::AUTHZ_DENIED ||
                $result == \AuthModule::AUTHZ_GRANTED) {
                break;
            }
        }

        if ($result != \AuthModule::AUTHZ_GRANTED) {
            // Let user authenticate
            $this->_router->createAuthenticateResponse();
            exit;
        }

        return true;
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
            // Let user authenticate
            $this->_router->createAuthenticateResponse();
            exit;
        }

        return true;
    }

    public function authNameDirective(\HTRequest $request, $line) {
        $line = trim($line);
        $line = trim($line, "\"\'");
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