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
     * @var HTRouter\Request
     */
    protected $_request;

    function setUp() {
        $this->_request = new \HTRouter\Request();
        $this->_request->setFilename("foo");
        $this->_request->setDocumentRoot("/www");
        $this->_request->setHostname("php.unittest.org");
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
        $rule = new MockRule($pattern, $sub, $flags);
        $this->assertEquals($output, (string)$rule);
    }


    function testDoesAddingConditionsFunction() {
        $rule = new MockRule("(.*)$", "test.php", "[L]");

        $this->assertCount(0, $rule->getConditions());

        $condition1 = new Condition('%{REQUEST_FILENAME}', '!-f', '[NV]');
        $rule->addCondition($condition1);
        $this->assertCount(1, $rule->getConditions());
        $a = $rule->getConditions();
        $this->assertEquals(spl_object_hash($condition1), spl_object_hash($a[0]));

        $condition2 = new Condition('%{REQUEST_FILENAME}', '!-d', '');
        $rule->addCondition($condition2);
        $this->assertCount(2, $rule->getConditions());
        $a = $rule->getConditions();
        $this->assertEquals(spl_object_hash($condition2), spl_object_hash($a[1]));
    }


    function testCanPatternBeNegative() {
        $rule = new MockRule('(.*)$', "test.php", "[L]");
        $this->assertFalse($rule->getProtectedProperty("_patternNegate"));
        $this->assertEquals('(.*)$', $rule->getProtectedProperty("_pattern"));

        $rule = new MockRule('!(.*)$', "test.php", "[L]");
        $this->assertTrue($rule->getProtectedProperty("_patternNegate"));
        $this->assertEquals('(.*)$', $rule->getProtectedProperty("_pattern"));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testDoesParseFlagsExceptionsWhenNotBracketedFunction() {
        $condition = new Rule('(.*)$', "test.php", "L");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testDoesParseFlagsExceptionsWhenNotValidFunction() {
        new Rule('(.*)$', "test.php", "[FOOBAR]");
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
        new Rule('(.*)$', "test.php", "[".$flag."]");
    }

    function testDoesParseFlagsFunction() {
        $rule = new Rule('(.*)$', "test.php", "[ENV=foo:bar,last,noescape,CO,gone]");
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
        $rule = new Rule(".+", "test.php", "");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("/test.php", $this->_request->getFilename());
    }


    function testMatchNoCase() {
        $rule = new Rule("FOO", "test.php", "[NC]");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("/test.php", $this->_request->getFilename());
    }

    function testRewrite001() {
        $rule = new Rule("FOO", "test.php", "");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("foo", $this->_request->getFilename());
    }

    function testMatchNegate() {
        $rule = new Rule("!FOO", "test.php", "");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("/test.php", $this->_request->getFilename());
    }

    function testMatchRedirectOtherDomain() {
        $rule = new Rule(".+", "http://otherdomain.com/test.php", "");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(302, $result->rc);
        $this->assertEquals("http://otherdomain.com/test.php", $this->_request->getOutHeaders("Location"));
    }

    function testMatchQSA() {
        $this->_request->setArgs(array("foo" => "1"));
        $rule = new Rule(".+", "test.php?bar=baz", "[QSA]");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("/test.php", $this->_request->getFilename());
        $this->assertCount(2, $this->_request->getArgs());
        $this->assertArrayHasKey("foo", $this->_request->getArgs());
        $this->assertArrayHasKey("bar", $this->_request->getArgs());
    }

    function testMatchQSA2() {
        $this->_request->setArgs(array("foo" => "1"));
        $rule = new Rule(".+", "test.php?bar=baz", "");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("/test.php", $this->_request->getFilename());
        $this->assertCount(1, $this->_request->getArgs());
        $this->assertArrayHasKey("bar", $this->_request->getArgs());
    }




    function testMatchSubtypeNone() {
        $rule = new Rule(".+", "-", "");
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("foo", $this->_request->getFilename());
    }

    function testDoesMatchingWithConditionsFunction() {
        // Rule does not match, since port != 1337
        $rule = new Rule(".+", "test.php", "");
        $rule->addCondition(new Condition('%{SERVER_ADMIN}', '=info@example.org', ''));
        $rule->addCondition(new Condition('%{SERVER_PORT}', '=1337', ''));
        $result = $rule->rewrite($this->_request);
        $this->assertEquals(0, $result->rc);
        $this->assertEquals("/test.php", $this->_request->getFilename());

        // Rule matches, since info@example.org is correct, and we need OR
        $rule = new Rule(".+", "test.php", "");
        $rule->addCondition(new Condition('%{SERVER_ADMIN}', '=info@example.org', '[OR]'));
        $rule->addCondition(new Condition('%{SERVER_PORT}', '=1337', ''));
        $result = $rule->rewrite($this->_request);
        //$this->assertTrue($rule->matches());

        // Rule matches, since both conditions are true
        $rule = new Rule(".+", "test.php", "");
        $rule->addCondition(new Condition('%{SERVER_ADMIN}', '=info@example.org', ''));
        $rule->addCondition(new Condition('%{SERVER_PORT}', '=80', ''));
        $result = $rule->rewrite($this->_request);
        //$this->assertTrue($rule->matches());
    }



    function testDoesRewriteFunction_001() {
        // /router.php
        $rule = new Rule("\.php$", "index.php", "[NC]");
//        $this->assertEquals("index.php", $rule->rewrite("/test.php"));
//        $this->assertEquals("index.php", $rule->rewrite("/TEST.PHP"));
    }

    function testDoesRewriteFunction_002() {
        $rule = new Rule("\.php$", "index.php", "");
//        $this->assertEquals("index.php", $rule->rewrite("/test.php"));
//        $this->assertEquals("/TEST.PHP", $rule->rewrite("/TEST.PHP"));
    }

    function testDoesRewriteFunction_003() {
        $rule = new Rule("\.php$", "-", "[R=301]");
//        $this->assertEquals("/test.php", $rule->rewrite("/test.php"));
//        $this->assertEquals("/TEST.PHP", $rule->rewrite("/TEST.PHP"));
    }

    function testDoesRewriteFunction_004() {
        $rule = new Rule("\.asp$", "index.php", "");
//        $this->assertEquals("/test.php", $rule->rewrite("/test.php"));
    }

    function testDoesRewriteFunction_005() {
        $rule = new Rule("!\.php$", "index.php", "");
//        $this->assertEquals("/test.php", $rule->rewrite("/test.php"));
//        $this->assertEquals("index.php", $rule->rewrite("/TEST.PHP"));
    }

    function testDoesRewriteFunction_006() {
        // @TODO: We need to check if redirection works
        //$rule = new Rule("\.php$", "http://www.google.com", "[R=301]");
        //$this->assertEquals("/test.php", $rule->rewrite("/test.php"));    // Redirects!
    }

}