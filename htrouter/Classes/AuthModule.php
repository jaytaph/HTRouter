<?php

namespace HTRouter;

use HTRouter\Module;

abstract class AuthModule extends Module {

    const AUTH_GRANTED      = 1;
    const AUTH_DENIED       = 2;
    const AUTH_NOT_FOUND    = 3;

    const AUTHZ_GRANTED      = 1;
    const AUTHZ_DENIED       = 2;
    const AUTHZ_NOT_FOUND    = 3;

    abstract public function getName();
}