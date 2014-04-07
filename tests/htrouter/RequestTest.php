<?php

class requestTest extends PHPUnit_Framework_TestCase {
    protected $_router;
    protected $_request;

    function setUp() {
        $this->_router = \HTRouter::getInstance();
        $this->_request = new \HTRouter\Request($this->_router);
    }

//    function testDoesEnvironmentFunction() {
//        $request = $this->_request;
//
//        // No environment set by default
//        $a = $request->getEnvironment();
//        $this->assertFalse($a);
//
//        // Add item
//        $request->appendEnvironment("foo", "bar");
//        $a = $request->getEnvironment();
//
//        $this->assertCount(1, $a);
//        $this->assertArrayHasKey("foo", $a);
//        $this->assertEquals("bar", $a['foo']);
//
//        // Add more items
//        $request->appendEnvironment("foo2", "bar2");
//        $request->appendEnvironment("foo3", "bar3");
//
//        $a = $request->getEnvironment();
//        $this->assertCount(3, $a);
//        $this->assertArrayHasKey("foo", $a);
//        $this->assertArrayHasKey("foo2", $a);
//        $this->assertArrayHasKey("foo3", $a);
//        $this->assertEquals("bar3", $a['foo3']);
//
//        // Unset item
//        $request->removeEnvironment("foo2");
//        $a = $request->getEnvironment();
//        $this->assertCount(2, $a);
//        $this->assertArrayNotHasKey("foo2", $a);
//
//        // Remove not existing item does noting
//        $request->removeEnvironment("foo5");
//        $a = $request->getEnvironment();
//        $this->assertCount(2, $a);
//    }
//
//    function testDoesGetIpFunction() {
//        $request = $this->_request;
//
//        $this->assertEquals("192.168.56.1", $request->getIp());
//    }
//
//    function testDoesGetDocumentRootFunction() {
//        $request = $this->_request;
//
//        $this->assertEquals("/etc/apache2/htdocs", $request->getDocumentRoot());
//    }
//
//    function testDoesIsHttpsFunction() {
//        $request = $this->_request;
//
//        // Check if flipping works
//        $this->assertFalse($request->isHttps());
//        $request->setHttps(true);
//        $this->assertTrue($request->isHttps());
//        $request->setHttps(false);
//        $this->assertFalse($request->isHttps());
//
//        // When it's not a boolean, we always return false
//        $request->setHttps("foobar");
//        $this->assertFalse($request->isHttps());
//    }
//
//    function testDoesServerVarsFunction() {
//        $request = $this->_request;
//
//        // Find item
//        $this->assertEquals("htrouter.phpunit.example.org", $request->getServerVar("HTTP_HOST"));
//
//        // Make sure lowercase searching functions as well
//        $this->assertEquals("htrouter.phpunit.example.org", $request->getServerVar("http_host"));
//
//        // Check empty items
//        $this->assertEmpty($request->getServerVar("foobar"));
//    }
//
//
//    function testDoesMagicAppendFunction() {
//        $request = $this->_request;
//
//        // Default is ""
//        $a = $request->vars->getFoobar();
//        $this->assertEmpty($a);
//
//        // Append item
//        $request->vars->appendFoobar("baz");
//        $a = $request->vars->getFoobar();
//        $this->assertCount(1, $a);
//        $this->assertEquals("baz", $a[0]);
//    }
//
//    // @TODO: MagicGet and MagicSet are tested throughout other methods. But we should test it separately anyway
//
//
//    function testDoesMagicUnsetFunction() {
//        $request = $this->_request;
//
//        $request->vars->setFoo("bar");
//
//        $this->assertEquals("bar", $request->vars->getFoo());
//
//        $request->vars->unsetFoo();
//        $this->assertEmpty("", $request->vars->getFoo());
//    }
//
//    function testDoesMagicGetFunction() {
//        $request = $this->_request;
//
//        $this->assertEmpty("", $request->vars->getFoo());
//        $this->assertCount(0, $request->vars->getFoo(array()));
//        $this->assertFalse($request->vars->getFoo(false));
//    }
//
//    function testDoesMagicFunction() {
//        $request = $this->_request;
//
//        $a = $request->vars->foobar();
//        $this->assertNull($a);
//    }

}
