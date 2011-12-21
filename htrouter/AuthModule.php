<?php

use HTRouter\ModuleInterface;

abstract class AuthModule implements ModuleInterface {

    const AUTH_GRANTED      = 1;
    const AUTH_DENIED       = 2;
    const AUTH_NOT_FOUND    = 3;

    const AUTHZ_GRANTED      = 1;
    const AUTHZ_DENIED       = 2;
    const AUTHZ_NOT_FOUND    = 3;

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        // No need to register anything
    }

    abstract public function getAuthType();

    abstract public function authenticateUser(\HTRequest $request);
}