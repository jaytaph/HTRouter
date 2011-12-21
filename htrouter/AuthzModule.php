<?php
/**
 * Authorization module interface.
 */

use HTRouter\ModuleInterface;

abstract class AuthzModule implements ModuleInterface {

    public function init(\HTRouter $router)
    {
        $this->_router = $router;

        // No need to register anything
    }

    abstract public function checkUserAccess(\HTRequest $request);
}