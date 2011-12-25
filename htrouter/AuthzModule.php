<?php
/**
 * Authorization module interface.
 */

use HTRouter\Module;

abstract class AuthzModule extends Module {

    abstract public function checkUserAccess(\HTRouter\Request $request);
}