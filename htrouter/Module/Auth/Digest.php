<?php

/**
 * Digest authentication is not operational yet.
 */

namespace HTRouter\Module\Auth;

class Digest extends \HTRouter\AuthModule {

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_CHECK_USER_ID, array($this, "authenticateDigestUser"));
    }

    public function authenticateDigestUser(\HTRouter\Request $request) {
        $plugin = $this->_container->getConfig()->getAuthType();
        if (! $plugin || ! $plugin instanceof \HTRouter\AuthModule || $plugin->getName() != "Digest") {
            return \HTRouter::STATUS_DECLINED;
        }

        // Set our handler type
        $request->setAuthType($this->getName());

        // Not yet available
        return \HTRouter::STATUS_DECLINED;
    }

    public function getName() {
        return "Digest";
    }

    public function getAliases() {
        return array("mod_auth_digest.c", "auth_digest");
    }

}