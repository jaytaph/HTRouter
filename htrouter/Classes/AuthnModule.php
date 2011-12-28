<?php
/**
 * Authentication module interface.
 */

namespace HTRouter;

use HTRouter\Module;

abstract class AuthnModule extends Module {

    abstract public function checkPassword(\HTRouter\Request $request, $user, $pass);

    abstract public function checkRealm(\HTRouter\Request $request, $user, $realm);
}