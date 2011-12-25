<?php

/**
 * Basic authentication. Will hook into a provider (as registered through the PROVIDER_AUTHN_GROUP). This mostly
 * will be authn_file, but can be anything else (like ldap for instance).
 */

namespace HTRouter\Module\Auth;
use HTRouter\ModuleInterface;

class Basic extends \AuthModule {

    public function authenticateUser(\HTRequest $request) {

        // Parse authentication request
        $auth = $request->getAuthentication();
        list ($auth_scheme, $auth_params)  = explode(" ", $auth, 2);
        if (strtolower($auth_scheme) != "basic") {
            return \AuthModule::AUTH_NOT_FOUND;     // We need BASIC authentication form the client
        }

        // Split user/pass
        $auth_params = base64_decode($auth_params);
        list ($user, $pass) = explode(":", $auth_params);


        // By default, we are not found
        $result = \AuthModule::AUTH_NOT_FOUND;

        // Iterator through all the registered providers to
        $providers = $this->_router->getProviders(\HTRouter::PROVIDER_AUTHN_GROUP);
        foreach ($providers as $provider) {
            $result = $provider->checkPassword($request, $user, $pass);

            if ($result != \AuthModule::AUTH_NOT_FOUND) {
                // Found (either denied or granted), we don't need to check any more providers
                break;
            }
        }

        // Set the authenticated user inside the request
        if ($result == \AuthModule::AUTH_GRANTED) {
            $request->setAuthenticatedUser($user);
            $request->setIsAuthenticated(true);
        }
        return $result;
    }

    public function getAuthType() {
        return "Basic";
    }

    public function getAliases() {
        return array("mod_auth_basic.c", "auth_basic");
    }

}