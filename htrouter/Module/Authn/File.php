<?php

/**
 * Authenticate against a file (mostly a htpasswd). There can be different ways of storing passwords (md5, sha1, crypt).
 * The HTUtils::validatePassword takes care of that.
 *
 * REALM information is not checked.
 */

namespace HTRouter\Module\Authn;

class File Extends \HTRouter\AuthnModule {

    public function init(\HTRouter $router)
    {
        parent::init($router);

        $router->registerDirective($this, "AuthUserFile");

        // This is a authorization module, so register it as a provider
        $router->registerProvider(\HTRouter::PROVIDER_AUTHN_GROUP, $this);
    }

    public function authUserFileDirective(\HTRouter\Request $request, $line) {
        if (! is_readable($line)) {
            throw new \RuntimeException("Cannot read authfile: $line");
        }

        $request->config->setAuthUserFile($line);
    }


    function checkRealm (\HTRouter\Request $request, $user, $realm) {
        // @TODO: unused
    }

    function checkPassword (\HTRouter\Request $request, $user, $pass) {
        $utils = new \HTRouter\Utils;

        // Read htpasswd file line by line
        $htpasswdFile = $request->config->getAuthUserFile();
        foreach (file($htpasswdFile) as $line) {

            // Trim line and parse user/pass
            $line = trim($line);
            if ($line[0] == "#") continue;
            list($chk_user, $chk_pass) = explode(":", $line);

            // Note: case SENSITIVE:  jay != JAY
            if ($chk_user == $user and $utils->validatePassword($pass, $chk_pass)) {
                return \HTRouter\AuthModule::AUTH_GRANTED;
            }
        }

        return \HTRouter\AuthModule::AUTH_DENIED;
    }

    public function getAliases() {
        return array("mod_authn_file.c", "authn_file");
    }

}