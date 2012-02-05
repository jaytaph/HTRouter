<?php

use HTRouter\Module\Rewrite\Rule;
use HTRouter\Module\Rewrite\Condition;
use HTRouter\Module\Rewrite\Flag;

class MockCondition extends Condition {

    function getProtectedProperty($item) {
        if (property_exists($this, $item)) {
            return $this->$item;
        }

        throw new Exception ("Property does not exist!");
    }
}

class module_rewrite_conditionTest extends PHPUnit_Framework_TestCase {
    /**
     * @var HTRouter\Module\Rewrite\Condition
     */
    protected $_condition;

    /**
     * @var HTRouter\Request
     */
    protected $_request;

    /**
     * @var HTRouter\Module\Rewrite\Rule
     */
    protected $_rule;

    function setUp() {
        $this->_condition = new Condition('%{REQUEST_FILENAME}', '!-f', '[NV]');

        $this->_request = new \HTRouter\Request();
        $this->_request->setFilename("foo");
        $this->_request->setDocumentRoot("/www");
        $this->_request->setHostname("php.unittest.org");
    }


    function parseCondPatternProvider() {
        return array(
            array('<TEST', MockCondition::COND_LEXICAL_PRE),
            array('>TEST', MockCondition::COND_LEXICAL_POST),
            array('=TEST', MockCondition::COND_LEXICAL_EQ),
            array('-d', MockCondition::COND_TEST_DIR),
            array('-f', MockCondition::COND_TEST_FILE),
            array('-s', MockCondition::COND_TEST_SIZE),
            array('-l', MockCondition::COND_TEST_SYMLINK),
            array('-x', MockCondition::COND_TEST_EXECUTE),
            array('-F', MockCondition::COND_TEST_FILE_SUBREQ),
            array('-U', MockCondition::COND_TEST_URL_SUBREQ),
            array('regex', MockCondition::COND_REGEX),
            array('somethingelse', MockCondition::COND_REGEX),
            array('>', MockCondition::COND_REGEX),
            array('<', MockCondition::COND_REGEX),
            array('-', MockCondition::COND_REGEX),
            array('-X', MockCondition::COND_REGEX),
            array('=', MockCondition::COND_REGEX),
        );
    }

    /**
     * @dataProvider parseCondPatternProvider
     *
     * @param $cond
     * @param $type
     */
    function testDoesParseCondPatternFunction($cond, $type) {
        $condition = new MockCondition('%{HTTP_HOST}', $cond, "");
        $this->assertEquals($type, $condition->getProtectedProperty("_condPatternType"));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testDoesParseCondPatternThrowsExceptionWhenEmptyFunction() {
        new MockCondition('%{HTTP_HOST}', '', "");
    }


    function testDoesNegateCondPatternFunction() {
        $condition = new MockCondition("%{HTTP_HOST}", "-d", "");
        $this->assertFalse($condition->getProtectedProperty("_condPatternNegate"));

        $condition = new MockCondition("%{HTTP_HOST}", "!-d", "");
        $this->assertTrue($condition->getProtectedProperty("_condPatternNegate"));
    }


    function parseFlagsProvider() {
        return array(
            array('[NC]'),
            array('[NC,OR]'),
            array('[NC,OR,NV]'),
            array('[nocase,ornext]'),
            array('[ornext,NC]'),
            array('[ornext,NC, novary]'),
        );
    }

    function parseFlagsProviderExceptions() {
        return array(
            array('[L]'),
            array('[last]'),
            array('[L,OR]'),
            array('[nocase,last]'),
            array('[]'),
        );
    }


    /**
     * @expectedException InvalidArgumentException
     */
    function testDoesParseFlagsExceptionsWhenNotBracketedFunction() {
        $condition = new MockCondition('%{HTTP_HOST}', '-d', "FOO");
    }

    /**
     * @dataProvider parseFlagsProvider
     */
    function testDoesParseFlagsFunction($flag) {
        $condition = new MockCondition('%{HTTP_HOST}', '-d', $flag);
    }

    /**
     * @dataProvider parseFlagsProviderExceptions
     * @expectedException InvalidArgumentException
     */
    function testDoesParseFlagsExceptionsFunction($flag) {
        $condition = new MockCondition('%{HTTP_HOST}', '-d', $flag);
    }

    function testDoesHasFlagFunction() {
        $condition = new MockCondition('%{HTTP_HOST}', '-d', "");
        $this->assertFalse($condition->hasFlag(Flag::TYPE_ORNEXT));
        $this->assertFalse($condition->hasFlag(Flag::TYPE_NOVARY));
        $this->assertFalse($condition->hasFlag(Flag::TYPE_NOCASE));

        $condition = new MockCondition('%{HTTP_HOST}', '-d', "[OR]");
        $this->assertTrue($condition->hasFlag(Flag::TYPE_ORNEXT));
        $this->assertFalse($condition->hasFlag(Flag::TYPE_NOVARY));
        $this->assertFalse($condition->hasFlag(Flag::TYPE_NOCASE));

        $condition = new MockCondition('%{HTTP_HOST}', '-d', "[OR,novary]");
        $this->assertTrue($condition->hasFlag(Flag::TYPE_ORNEXT));
        $this->assertTrue($condition->hasFlag(Flag::TYPE_NOVARY));
        $this->assertFalse($condition->hasFlag(Flag::TYPE_NOCASE));


    }


    function conditionProvider() {
        return array(
            array('%{REQUEST_FILENAME}', '!-d', '', '%{REQUEST_FILENAME} !-d'),
            array('%{HTTP_USER_AGENT}', '^Mozilla/5.0.*', '', '%{HTTP_USER_AGENT} ^Mozilla/5.0.*'),
            array('%{REMOTE_HOST}', '^host1.*', '[OR]', '%{REMOTE_HOST} ^host1.* [OR]'),
            array('%{HTTP_REFERER}', '!dev-trickss.com', '[NC]', '%{HTTP_REFERER} !dev-trickss.com [NC]'),
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
    function testDoesToStringFunction($test, $cond, $flags, $output) {
        $condition = new Condition($test, $cond, $flags);
        $this->assertEquals($output, (string)$condition);
    }


    function matchProvider() {
        return array(
            array('%{SERVER_NAME}','=htrouter.phpunit.example.org',"", true),
            array('%{SERVER_NAME}','=HTROUTER.PHPUNIT.EXAMPLE.ORG',"[NC]", true),
            array('%{SERVER_NAME}','=HTROUTER.PHPUNIT.EXAMPLE.ORG',"", false),
            array('%{SERVER_NAME}','<htrouter',"", true),
            array('%{SERVER_NAME}','<.example.org',"", false),
            array('%{SERVER_NAME}','>.example.org',"", true),
            array('%{SERVER_NAME}','>somethingthatislongerthantheactualservernameexample.org',"", false),
            array('%{SERVER_NAME}','>g',"", true),

            array('%{API_VERSION}', '=123.45', "", true),
            array('%{API_VERSION}', '[\d\.]+', "", true),
            array('%{API_VERSION}', '![\d\.]+', "", false),

            array('%{SERVER_NAME}','org$',"", true),
            array('%{SERVER_NAME}','ORG$',"", false),
            array('%{SERVER_NAME}','ORG$',"[nocase]", true),
        );
    }

    /**
     * @dataProvider matchProvider
     *
     * @param $test
     * @param $cond
     * @param $flags
     * @param $doesMatch
     */
    function testDoesMatchFunction($test, $cond, $flags, $doesMatch) {
        $condition = new Condition($test, $cond, $flags);

        if ($doesMatch) {
            $this->assertTrue($condition->matches($this->_request));
        } else {
            $this->assertFalse($condition->matches($this->_request));
        }
    }


    function matchProviderExceptions() {
        return array(
            array('%1','!-d',''),
            array('$1','!-d',''),
        );
    }

//    /**
//     * @dataProvider matchProviderExceptions
//     * @expectedException DomainException
//     *
//     * @param $test
//     * @param $cond
//     * @param $flags
//     */
//    function testDoUnsupportedMatchesThrowExceptionsFunction($test, $cond, $flags) {
//        $condition = new Condition($test, $cond, $flags);
//
//        $condition->matches($this->_request);
//    }

}