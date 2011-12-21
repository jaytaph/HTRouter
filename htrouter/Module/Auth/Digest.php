<?php

/**
 * Digest authentication is not operational yet.
 */

namespace HTRouter\Module\Auth;
use HTRouter\ModuleInterface;

class Digest extends \AuthModule {

    public function authenticateUser(\HTRequest $request) {
        throw new \LogicException("Digest authentication is not yet available.");
    }

    public function getAuthType() {
        return "Digest";
    }

    public function getName() {
        return "auth_digest";
    }

}