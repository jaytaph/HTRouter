<?php

/**
 * Check authorization
 */

namespace HTRouter\Module\Authz;

class User extends \HTRouter\AuthzModule {

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register directives
        $router->registerDirective($this, "AuthzUserAuthoritative");

        // This is a authorization module, so register it as a provider
        $router->registerProvider(\HTRouter::PROVIDER_AUTHZ_GROUP, $this);
    }

    public function AuthzUserAuthoritativeDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils;
        $value = $utils->fetchDirectiveFlags($line, array("on" => "on", "off" => "off"));
        $this->getConfig()->set("AuthzUserAuthoritative", $value);
    }

    public function checkUserAccess(\HTRouter\Request $request) {
        // Any will do, and we are already authenticated through the "allow/deny" rules. No Need to check this.
        // @TODO: This code must be moved to HTRouter::_run()
        if ($this->getConfig()->get("Satisfy") == "any" && $request->getAuthorized()) {
            return \HTRouter\AuthModule::AUTHZ_GRANTED;
        }

        $requires = $this->getConfig()->get("Require");
        foreach ($requires as $require) {
            if (strtolower($require) == "valid-user") {
                // Set the authorized user inside the request
                $user = $request->getAuthUser();
                $request->setAuthUser($user);
                $request->setAuthorized(true);
                return \HTRouter\AuthModule::AUTHZ_GRANTED;
            }

            // Check if it starts with 'user'
            $users = explode(" ", $require);
            $tmp = array_shift($users);
            if ($tmp != "user") {
                continue;
            }

            // Parse all users on this line to check if it matches against the currently authenticated user
            foreach ($users as $user) {
                if ($user == $request->getAuthUser()) {
                    // Set the authorized user inside the request
                    $request->setAuthUser($user);
                    $request->setAuthorized(true);
                    return \HTRouter\AuthModule::AUTHZ_GRANTED;
                }
            }
        }

        // If the module is authorative we should deny access. This will stop other modules from trying to match..
        if ($this->getConfig()->get("AuthzUserAuthoritative") == "on") {
            return \HTRouter\AuthModule::AUTHZ_DENIED;
        }

        // Nothing that matches found, and w
        return \HTRouter\AuthModule::AUTHZ_NOT_FOUND;
    }

    public function getAliases() {
        return array("mod_authz_user.c", "authz_user");
    }

}