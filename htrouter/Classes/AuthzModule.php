<?php
/**
 * Authorization module interface.
 */

namespace HTRouter;

use HTRouter\Module;

abstract class AuthzModule extends Module {

    abstract public function checkUserAccess(\HTRouter\Request $request);
}