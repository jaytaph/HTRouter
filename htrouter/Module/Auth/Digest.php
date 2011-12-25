<?php

/**
 * Digest authentication is not operational yet.
 */

namespace HTRouter\Module\Auth;

class Digest extends \AuthModule {

    public function authenticateUser(\HTRouter\Request $request) {
        throw new \LogicException("Digest authentication is not yet available.");
    }

    public function getAuthType() {
        return "Digest";
    }

    public function getAliases() {
        return array("mod_auth_digest.c", "auth_digest");
    }

}