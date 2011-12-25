<?php

namespace HTRouter;

abstract class Module  {
    protected $_router;

    /**
     * @param \HTRouter $router
     */
    public function init(\HTRouter $router)
    {
        $this->_router = $router;
    }

    /**
     * @return mixed
     */
    public function getRouter() {
        return $this->_router;
    }

    /**
     * @param \HTRouter $router
     */
    public function setRouter(\HTRouter $router) {
        $this->_router = $router;
    }

    abstract public function getAliases();
}