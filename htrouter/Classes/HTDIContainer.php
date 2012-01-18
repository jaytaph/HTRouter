<?php

namespace HTRouter;

class HTDIContainer extends \DIContainer {
    protected $router;
    protected $request;
    protected $logger;
    protected $config;
    protected $routerConfig;

    function setRouter(\HTRouter $router) {
        $this->router = $router;
    }

    /**
     * @return \HTRouter
     */
    function getRouter() {
        return $this->router;
    }

    function setRequest(\HTRouter\Request $request) {
        $this->request = $request;
    }
    /**
     * @return \HTRouter\Request
     */
    function getRequest() {
        return $this->request;
    }

    function setLogger(\HTRouter\Logger $logger) {
        $this->logger = $logger;
    }
    /**
     * @return \HTRouter\Logger
     */
    function getLogger() {
        return $this->logger;
    }

    function setRouterConfig(array $config) {
        $this->routerConfig = $config;
    }
    function getRouterConfig($section = null) {
        if (! $section or ! isset($this->routerConfig[$section])) {
            return $this->routerConfig;
        } else {
            return $this->routerConfig[$section];
        }
    }

    function setConfig(\HTRouter\VarContainer $config) {
        $this->config = $config;
    }

    /**
     * @return \HTRouter\VarContainer
     */
    function getConfig() {
        return $this->config;
    }

}
