<?php

namespace HTRouter;

abstract class Module  {
    /**
     * @var \HTRouter\HTDIContainer
     */
    protected $_container;

    /**
     */
    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container) {
        $this->_container = $container;
    }

    public function getRequest() {
        return $this->_container->getRequest();
    }

    public function getRouterConfig() {
        return $this->_container->getRouterConfig();
    }

    /**
     * @return \HTRouter\VarContainer
     */
    public function getConfig() {
        return $this->_container->getConfig();
    }


    public function getLogger() {
        return $this->_container->getLogger();
    }

    public function getRouter() {
        return $this->_container->getRouter();
    }


    /**
     * Called when need to merge variables from one container to another (for instance, when reading
     * multiple .htaccess files)
     */
    public function mergeConfigs(\HTRouter\VarContainer $base, \HTRouter\VarContainer $add) {
        return $base;
    }

    /**
     * @abstract
     * Returns a list of aliases for this module (mod_rewrite, rewrite_module, mod_rewrite.c, rewrite etc)
     */
    abstract public function getAliases();
}