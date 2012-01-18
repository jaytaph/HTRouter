<?php

/**
 * Basic authentication. Will hook into a provider (as registered through the PROVIDER_AUTHN_GROUP). This mostly
 * will be authn_file, but can be anything else (like ldap for instance).
 */

namespace HTRouter\Module\Auth;

class Basic extends \HTRouter\AuthModule {

    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register hooks
        $router->registerHook(\HTRouter::HOOK_CHECK_USER_ID, array($this, "authenticateBasicUser"));
    }

    public function authenticateBasicUser(\HTRouter\Request $request) {
        /**
         * @var $plugin \HTRouter\AuthModule
         */
        $plugin = $this->_container->getConfig()->get("AuthType");
        if (! $plugin || ! $plugin instanceof \HTRouter\AuthModule || $plugin->getName() != "Basic") {
            return \HTRouter::STATUS_DECLINED;
        }

        // Set our handler type
        $request->setAuthType($this->getName());

        // Check realm
        if (! $this->getConfig()->get("AuthName")) {
            $this->getLogger()->log(\HTRouter\Logger::ERRORLEVEL_ERROR, "need authname: ".$request->getUri());
            return \HTRouter::STATUS_HTTP_INTERNAL_SERVER_ERROR;
        }

        $ret = $this->_getBasicAuth($request);
        if (! is_array($ret)) {
            $request->appendOutHeaders("WWW-Authenticate", "Basic realm=\"".$this->getConfig()->get("AuthName")."\"");
            return $ret;
        }
        list($user, $pass) = $ret;

        // By default, we are not found
        $result = \HTRouter\AuthModule::AUTH_NOT_FOUND;

        // Iterator through all the registered providers to
        $providers = $this->getRouter()->getProviders(\HTRouter::PROVIDER_AUTHN_GROUP);
        foreach ($providers as $provider) {
            /**
             * @var $provider \HTRouter\AuthnModule
             */
            $result = $provider->checkPassword($request, $user, $pass);
            if ($result != \HTRouter\AuthModule::AUTH_NOT_FOUND) {
                // Found (either denied or granted), we don't need to check any more providers
                break;
            }
        }

        // Set the authenticated user inside the request
        if ($result != \HTRouter\AuthModule::AUTH_GRANTED) {

            if ($this->getConfig()->get("AuthzUserAuthoritative") && $result != \HTRouter\AuthModule::AUTH_DENIED) {
                // Not authoritative so we decline and goto the next checker
                return \HTRouter::STATUS_DECLINED;
            }

            switch ($result) {
                case \HTRouter\AuthModule::AUTH_DENIED :
                    $retval = \HTRouter::STATUS_HTTP_UNAUTHORIZED;
                    break;
                case \HTRouter\AuthModule::AUTH_NOT_FOUND :
                    $retval = \HTRouter::STATUS_HTTP_UNAUTHORIZED;
                    break;
                default:
                    $retval = \HTRouter::STATUS_HTTP_INTERNAL_SERVER_ERROR;
                    break;
            }

            // If we need to send a 403, do it
            if ($retval == \HTRouter::STATUS_HTTP_UNAUTHORIZED) {
                $request->appendOutHeaders("WWW-Authenticate", "Basic realm=\"".$this->getConfig()->get("AuthName")."\"");
            }

            return $result;
        }

        return \HTRouter::STATUS_OK;
    }

    /**
     * Returns either int or array[2] with user/pass
     *
     * @param $request
     * @return array|int
     */
    function _getBasicAuth(\HTRouter\Request $request) {
        // Parse authentication request
        $auth = $request->getInHeaders("Authorization");
        if (! $auth) {
            return \HTRouter::STATUS_HTTP_UNAUTHORIZED;
        }
        list ($auth_scheme, $auth_params)  = explode(" ", $auth, 2);
        if (strtolower($auth_scheme) != "basic") {
            return \HTRouter::STATUS_HTTP_UNAUTHORIZED;
        }

        // Split user/pass
        $auth_params = base64_decode($auth_params);
        return explode(":", $auth_params, 2);
    }

    public function getName() {
        return "Basic";
    }

    public function getAliases() {
        return array("mod_auth_basic.c", "auth_basic");
    }
}