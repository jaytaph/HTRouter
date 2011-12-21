<?php
/**
 * Authentication module interface.
 */

use HTRouter\ModuleInterface;

abstract class AuthnModule implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        // No need to register anything
    }

    abstract public function checkPassword(\HTRequest $request, $user, $pass);
    abstract public function checkRealm(\HTRequest $request, $user, $realm);
}