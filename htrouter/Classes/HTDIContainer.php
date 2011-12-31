<?php

namespace HTRouter;

class HTDIContainer extends \DIContainer {

    function setRequest(\HTRouter\Request $request) {
        $this->request = $request;
    }
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
    function getConfig() {
        return $this->config;
    }

}
