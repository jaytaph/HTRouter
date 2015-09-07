<?php

class MockModule extends \HTRouter\Module {
    public function getAliases()
    {
        array("mock.c", "mock_module");
    }
}
class MockModule2 extends \HTRouter\Module {
    public function getAliases()
    {
        array("mock2.c", "mock_module2");
    }
}

class MockHTRouter extends \HTRouter {
    private static $__instance = null;
    public static function getInstance() {
        if (! self::$__instance) {
            $class = __CLASS__;
            self::$__instance = new $class();
        }
        return self::$__instance;
    }

    public static function newInstance(){
        $class = __CLASS__;
        return new $class();
    }

    function getRequest() {
        return $this->_getRequest();
    }

    function getHooks() {
        return $this->_hooks;
    }

    function directiveExists($directive) {
        return $this->_directiveExists($directive);
    }
}

class htrouterTest extends PHPUnit_Framework_TestCase {
    protected $_router;

    function setUp() {
        $this->_router = MockHTRouter::getInstance();
    }

    function testDoesProvidersFunction() {
        $router = $this->_router;

        $router->registerProvider("foo", new MockModule());
        $router->registerProvider("bar", new MockModule());
        $router->registerProvider("bar", new MockModule2());

        $a = $router->getProviders("foo");
        $this->assertCount(1, $a);
        $this->assertInstanceOf("MockModule", $a[0]);

        $a = $router->getProviders("bar");
        $this->assertCount(2, $a);
        $this->assertInstanceOf("MockModule", $a[0]);
        $this->assertInstanceOf("MockModule2", $a[1]);
    }


    function testDoesDirectivesFunction() {
        $router = $this->_router;
        $router->registerDirective(new MockModule(), "foo");
        $router->registerDirective(new MockModule(), "bar");

        // Check for 'foo'
        $a = $router->directiveExists("foo");
        $this->assertCount(2, $a);
        $this->assertEquals("foo", $a[1]);
        $this->assertInstanceof("MockModule", $a[0]);

        // Check for 'bar'
        $a = $router->directiveExists("bar");
        $this->assertCount(2, $a);
        $this->assertEquals("bar", $a[1]);
        $this->assertInstanceof("MockModule", $a[0]);

        // Check for non-existing directive
        $this->assertFalse($router->directiveExists("baz"));
    }


    /**
     * @expectedException \RuntimeException
     */
    function testDoesAddingTheSameDirectiveFunction() {
        $router = $this->_router;
        $router->registerDirective(new MockModule(), "foo");
        $router->registerDirective(new MockModule(), "foo");
    }


    function testDoesHooksFunction() {
        $router = $this->_router;

        // Register hook with default order
        $router->registerHook("foo", array("callback"));
        $a = $router->getHooks();
        $this->assertEquals("callback", $a['foo'][50][0][0]);

        // Register another hook on same level
        $router->registerHook("foo", array("callback2"));
        $a = $router->getHooks();
        $this->assertEquals("callback2", $a['foo'][50][1][0]);

        // Register hook with different order
        $router->registerHook("foo", array("callback3"), 99);
        $a = $router->getHooks();
        $this->assertEquals("callback3", $a['foo'][99][0][0]);

        // Register different hook
        $router->registerHook("baz", array("callback4"), 10);
        $a = $router->getHooks();
        $this->assertEquals("callback4", $a['baz'][10][0][0]);
    }

    function testDoesEnvironmentFunction() {
        $router = $this->_router;

        // No environment set by default
        $a = $router->getEnvironment();
        $this->assertFalse($a);

        // Add item
        $router->setEnvironment("foo", "bar");
        $a = $router->getEnvironment();

        $this->assertCount(1, $a);
        $this->assertArrayHasKey("foo", $a);
        $this->assertEquals("bar", $a['foo']);

        // Add more items
        $router->setEnvironment("foo2", "bar2");
        $router->setEnvironment("foo3", "bar3");

        $a = $router->getEnvironment();
        $this->assertCount(3, $a);
        $this->assertArrayHasKey("foo", $a);
        $this->assertArrayHasKey("foo2", $a);
        $this->assertArrayHasKey("foo3", $a);
        $this->assertEquals("bar3", $a['foo3']);

        // Unset item
        $router->unsetEnvironment("foo2");
        $a = $router->getEnvironment();
        $this->assertCount(2, $a);
        $this->assertArrayNotHasKey("foo2", $a);

        // Remove not existing item does noting
        $router->unsetEnvironment("foo5");
        $a = $router->getEnvironment();
        $this->assertCount(2, $a);
    }

}