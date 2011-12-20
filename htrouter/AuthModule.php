<?php

use HTRouter\ModuleInterface;

abstract class AuthModule implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        // No need to register anything
    }

    abstract public function authenticateUser(\HTRequest $request);
}