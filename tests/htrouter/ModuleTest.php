<?php


class moduleTest extends PHPUnit_Framework_TestCase {
    protected $_router;
    protected $_module;

    function setUp() {
        $this->_router = MockHTRouter::newInstance();
        // MockModule defined in HTRouterTest.php
        $this->_module = new MockModule($this->_router);
    }

    function testSomething(){
        $this->assertEquals(true, true);
    }

}