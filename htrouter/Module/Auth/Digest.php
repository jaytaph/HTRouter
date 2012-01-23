<?php

/**
 * Digest authentication is not operational yet.
 */

namespace HTRouter\Module\Auth;

class Digest extends \HTRouter\AuthModule {

    /**
     * @param \HTRouter $router
     * @param \HTRouter\HTDIContainer $container
     */
    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_CHECK_USER_ID, array($this, "authenticateDigestUser"));
    }

    /**
     * @param \HTRouter\Request $request
     * @return int
     */
    public function authenticateDigestUser(\HTRouter\Request $request) {
        /**
         * @var $plugin \HTRouter\AuthModule
         */
        $plugin = $this->_container->getConfig()->get("AuthType");
        if (! $plugin || ! $plugin instanceof \HTRouter\AuthModule || $plugin->getName() != "Digest") {
            return \HTRouter::STATUS_DECLINED;
        }

        // Set our handler type
        $request->setAuthType($this->getName());

        // Not yet available
        return \HTRouter::STATUS_DECLINED;
    }

    /**
     * @return string
     */
    public function getName() {
        return "Digest";
    }

    /**
     * @return array
     */
    public function getAliases() {
        return array("mod_auth_digest.c", "auth_digest");
    }

}