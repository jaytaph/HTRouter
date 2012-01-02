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
     * @return \VarContainer
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

    abstract public function getAliases();
}