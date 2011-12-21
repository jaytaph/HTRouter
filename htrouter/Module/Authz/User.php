<?php

/**
 * Check authorization
 */

namespace HTRouter\Module\Authz;
use HTRouter\ModuleInterface;

class User Extends \AuthzModule {

    public function init(\HTRouter $router)
    {
        parent::init($router);

        $router->registerDirective($this, "AuthzUserAuthoritative");

        // This is a authorization module, so register it as a provider
        $router->registerProvider(\HTRouter::PROVIDER_AUTHZ_GROUP, $this);
    }

    public function AuthzUserAuthoritativeDirective(\HTRequest $request, $line) {
        $line = strtolower(trim($line));
        if ($line != "on" and $line != "off") {
            throw new \DomainException("AuthzUserAuthoritative must be on or off");
        }
        $request->setAuthzUserAuthoritative($line);
    }

    public function checkUserAccess(\HTRequest $request) {
        $requires = $request->getRequire();
        foreach ($requires as $require) {
            if (strtolower($require) == "valid-user") {
                // Set the authorized user inside the request
                $user = $request->getAuthenticatedUser();
                $request->setAuthorizedUser($user);
                return \AuthModule::AUTHZ_GRANTED;
            }

            // Check if it starts with 'user'
            $users = explode(" ", $require);
            $tmp = array_shift($users);
            if ($tmp != "user") {
                continue;
            }

            // Parse all users on this line to check if it matches against the currently authenticated user
            foreach ($users as $user) {
                if ($user == $request->getAuthenticatedUser()) {
                    // Set the authorized user inside the request
                    $request->setAuthorizedUser($user);
                    return \AuthModule::AUTHZ_GRANTED;
                }
            }
        }

        // If the module is authorative we should deny access. This will stop other modules from trying to match..
        if ($request->getAuthzUserAuthoritative() == "on") {
            return \AuthModule::AUTHZ_DENIED;
        }

        // Nothing that matches found, and w
        return \AuthModule::AUTHZ_NOT_FOUND;
    }

    public function getName() {
        return "authz_user";
    }

}