<?php


class moduleTest extends PHPUnit_Framework_TestCase {
    protected $_router;
    protected $_module;

    function setUp() {
        $this->_router = new \HTRouter();
        // MockModule defined in HTRouterTest.php
        $this->_module = new MockModule($this->_router);
    }

    function testDoesInitFunction() {
        $router = new \HTRouter();

        // Make sure $router and $this->_router are not the same (no reference or such)
        $this->assertNotEquals(spl_object_hash($this->_router), spl_object_hash($router));

        // Set our router, and check if it's the correct one
        $this->_module->init($router);
        $this->assertEquals(spl_object_hash($router), spl_object_hash($this->_module->getRouter()));
    }

    function testDoGetSetRouterFunction() {
        $router = new \HTRouter();

        $this->_module->setRouter($router);
        $this->assertEquals($router, $this->_module->getRouter());
    }

}