<?php

use HTRouter\Module\Rewrite\Rule;
use HTRouter\Module\Rewrite\Condition;
use HTRouter\Module\Rewrite\Flag;

class MockRule extends Rule {

    function getProtectedProperty($item) {
        if (property_exists($this, $item)) {
            return $this->$item;
        }

        throw new Exception ("Property does not exist!");
    }
}

class module_rewrite_ruleTest extends PHPUnit_Framework_TestCase {

    /**
     * @var HTRouter
     */
    protected $_router;

    /**
     * @var HTRouter\Request
     */
    protected $_request;

    /**
     * @var HTRouter\Module\Rewrite\Rule
     */
    protected $_rule;

    function setUp() {
        $this->_router = new \HTRouter();
        $this->_request = new \HTrouter\Request($this->_router);

        $this->_rule = new rule($this->_request, "(.*)$", "test.php", "[L]");
    }


    function conditionProvider() {
        return array(
            array('.*\.(gif|jpg|png)$', '-', '[F]', '.*\.(gif|jpg|png)$ - [F]'),
            array('(.*)$', 'test.php', '', '(.*)$ test.php'),
            array('!(.*)$', 'test.php', '', '(.*)$ test.php'),
        );
    }

    /**
     * @dataProvider conditionProvider
     *
     * @param $test
     * @param $cond
     * @param $flags
     * @param $output
     */
    function testDoesToStringFunction($pattern, $sub, $flags, $output) {
        $rule = new rule($this->_request, $pattern, $sub, $flags);
        $this->assertEquals($output, (string)$rule);
    }


    function testDoesAddingConditionsFunction() {
        $rule = $this->_rule;

        $this->assertCount(0, $rule->getCondititions());

        $condition1 = new Condition('%{REQUEST_FILENAME}', '!-f', '[NV]');
        $rule->addCondition($condition1);
        $this->assertCount(1, $rule->getCondititions());
        $a = $rule->getCondititions();
        $this->assertEquals(spl_object_hash($condition1), spl_object_hash($a[0]));

        $condition2 = new Condition('%{REQUEST_FILENAME}', '!-d', '');
        $rule->addCondition($condition2);
        $this->assertCount(2, $rule->getCondititions());
        $a = $rule->getCondititions();
        $this->assertEquals(spl_object_hash($condition2), spl_object_hash($a[1]));
    }


    function testCanPatternBeNegative() {
        $rule = new MockRule($this->_request, '(.*)$', "test.php", "[L]");
        $this->assertFalse($rule->getProtectedProperty("_patternNegate"));
        $this->assertEquals('(.*)$', $rule->getProtectedProperty("_pattern"));

        $rule = new MockRule($this->_request, '!(.*)$', "test.php", "[L]");
        $this->assertTrue($rule->getProtectedProperty("_patternNegate"));
        $this->assertEquals('(.*)$', $rule->getProtectedProperty("_pattern"));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testDoesParseFlagsExceptionsWhenNotBracketedFunction() {
        $condition = new Rule($this->_request, '(.*)$', "test.php", "L");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testDoesParseFlagsExceptionsWhenNotValidFunction() {
        new Rule($this->_request, '(.*)$', "test.php", "[FOOBAR]");
    }

    function flagProvider() {
        return array(
            array("B"),
            array("C"),
            array("CO"),
            array("DPI"),
            array("E"),
            array("F"),
            array("G"),
            array("H"),
            array("L"),
            array("N"),
            array("NC"),
            array("NE"),
            array("NS"),
            array("P"),
            array("PT"),
            array("QSA"),
            array("R"),
            array("S"),
            array("T"),
            array("ENV=foo:bar"),
            array("ENV=foo:bar,last,noescape,CO,gone"),
        );
    }

    /**
     * @dataProvider flagProvider
     * @param $flag
     */
    function testDoesParseFlagsGetAllFlags($flag) {
        // It's ok as long as we don't throw exception
        new Rule($this->_request, '(.*)$', "test.php", "[".$flag."]");
    }

    function testDoesParseFlagsFunction() {
        $rule = new Rule($this->_request, '(.*)$', "test.php", "[ENV=foo:bar,last,noescape,CO,gone]");
        $this->assertCount(5, $rule->getFlags());

        $this->assertTrue($rule->hasFlag(Flag::TYPE_ENV));
        $this->assertTrue($rule->hasFlag(Flag::TYPE_COOKIE));
        $this->assertFalse($rule->hasFlag(Flag::TYPE_ORNEXT));
        $this->assertTrue($rule->hasFlag(Flag::TYPE_GONE));

        $flag = $rule->getFlag(Flag::TYPE_ENV);
        $this->assertEquals("foo", $flag->getKey());
        $this->assertEquals("bar", $flag->getValue());
    }

    function testDoesMatchFunction() {

        // Rule does not match, since port != 1337
        $rule = new Rule($this->_request, ".+", "test.php", "");
        $rule->addCondition(new Condition('%{SERVER_ADMIN}', '=info@example.org', ''));
        $rule->addCondition(new Condition('%{SERVER_PORT}', '=1337', ''));
        $this->assertFalse($rule->matches());

        // Rule matches, since info@example.org is correct, and we need OR
        $rule = new Rule($this->_request, ".+", "test.php", "");
        $rule->addCondition(new Condition('%{SERVER_ADMIN}', '=info@example.org', '[OR]'));
        $rule->addCondition(new Condition('%{SERVER_PORT}', '=1337', ''));
        $this->assertTrue($rule->matches());

        // Rule matches, since both conditions are true
        $rule = new Rule($this->_request, ".+", "test.php", "");
        $rule->addCondition(new Condition('%{SERVER_ADMIN}', '=info@example.org', ''));
        $rule->addCondition(new Condition('%{SERVER_PORT}', '=80', ''));
        $this->assertTrue($rule->matches());
    }



    function testDoesRewriteFunction() {
        // /router.php
        $rule = new Rule($this->_request, "\.php$", "index.php", "[NC]");
        $this->assertEquals("index.php", $rule->rewrite("/test.php"));
        $this->assertEquals("index.php", $rule->rewrite("/TEST.PHP"));

        $rule = new Rule($this->_request, "\.php$", "index.php", "");
        $this->assertEquals("index.php", $rule->rewrite("/test.php"));
        $this->assertEquals("/TEST.PHP", $rule->rewrite("/TEST.PHP"));

        $rule = new Rule($this->_request, "\.php$", "-", "[R=301]");
        $this->assertEquals("/test.php", $rule->rewrite("/test.php"));
        $this->assertEquals("/TEST.PHP", $rule->rewrite("/TEST.PHP"));

        $rule = new Rule($this->_request, "\.asp$", "index.php", "");
        $this->assertEquals("/test.php", $rule->rewrite("/test.php"));

        $rule = new Rule($this->_request, "!\.php$", "index.php", "");
        $this->assertEquals("/test.php", $rule->rewrite("/test.php"));
        $this->assertEquals("index.php", $rule->rewrite("/TEST.PHP"));

        // @TODO: We need to check if redirection works
        //$rule = new Rule($this->_request, "\.php$", "http://www.google.com", "[R=301]");
        //$this->assertEquals("/test.php", $rule->rewrite("/test.php"));    // Redirects!
    }



}