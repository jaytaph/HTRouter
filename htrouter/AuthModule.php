<?php

use HTRouter\ModuleInterface;

abstract class AuthModule implements ModuleInterface {
    const AUTH_NOT_FOUND    = 1;
    const AUTH_GRANTED      = 2;
    const AUTH_DENIED       = 3;

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        // No need to register anything
    }

    abstract public function getAuthType();

    abstract public function authenticateUser(\HTRequest $request);
}