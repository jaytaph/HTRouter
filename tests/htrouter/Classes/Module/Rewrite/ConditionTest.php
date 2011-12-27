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
        $this->_condition = new Condition('%{REQUEST_FILENAME}', '!-f', '[NV]');

        $this->_router = new \HTRouter();
        $this->_request = new \HTrouter\Request($this->_router);
        $this->_request->setApiVersion("12345");

        $this->_rule = new Rule($this->_request, '.*\.(gif|jpg|png)$', '-', '[F]');
    }

    /**
     * @expectedException DomainException
     */
    function testDoesGetRequestWithoutLinkFunction() {
        // Cannot get request when not linked to a rule
        $this->assertNull($this->_condition->getRequest());
    }

    function testDoesGetRequestWithLinkFunction() {
        $this->_condition->linkRule($this->_rule);
        $this->assertEquals($this->_request, $this->_condition->getRequest());
    }

    function parseTestProvider() {
        return array(
            array('$0', MockCondition::TYPE_RULE_BACKREF),
            array('$9', MockCondition::TYPE_RULE_BACKREF),
            array('%0', MockCondition::TYPE_COND_BACKREF),
            array('%9', MockCondition::TYPE_COND_BACKREF),
            array('%{IS_SUBREQ}', MockCondition::TYPE_SPECIAL),
            array('%{THE_REQUEST}', MockCondition::TYPE_SPECIAL),
            array('%{TIME_HOUR}', MockCondition::TYPE_SERVER),
            array('%{SERVER_ADDR}', MockCondition::TYPE_SERVER),
        );
    }
    function parseTestProviderExceptions() {
        return array(
            array('', MockCondition::TYPE_RULE_BACKREF),
            array('$', MockCondition::TYPE_RULE_BACKREF),
            array('%', MockCondition::TYPE_RULE_BACKREF),
            array('$10', MockCondition::TYPE_RULE_BACKREF),
            array('%10', MockCondition::TYPE_RULE_BACKREF),
            array('%{FOOBAR}', MockCondition::TYPE_COND_BACKREF),
            array('%{}', MockCondition::TYPE_COND_BACKREF),
            array('%{A}', MockCondition::TYPE_COND_BACKREF),
        );
    }

    /**
     * @dataProvider parseTestProvider
     *
     * @param $test
     * @param $type
     */
    function testDoesParseTestStringFunction($test, $type) {
        $condition = new MockCondition($test, "!-d", "");
        $this->assertEquals($type, $condition->getProtectedProperty("_testStringType"));
    }

    /**
     * @dataProvider parseTestProviderExceptions
     * @expectedException UnexpectedValueException
     *
     * @param $test
     * @param $type
     */
    function testDoesParseTestStringExceptionsFunction($test, $type) {
        $condition = new MockCondition($test, "!-d", "");
        $this->assertEquals($type, $condition->getProtectedProperty("_testStringType"));
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
     * @expectedException UnexpectedValueException
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
     * @expectedException UnexpectedValueException
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
     * @expectedException UnexpectedValueException
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

            array('%{API_VERSION}', '=12345', "", true),
            array('%{API_VERSION}', '\d+', "", true),
            array('%{API_VERSION}', '!\d+', "", false),

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
        $condition->linkRule($this->_rule);

        if ($doesMatch) {
            $this->assertTrue($condition->matches());
        } else {
            $this->assertFalse($condition->matches());
        }
    }


    function matchProviderExceptions() {
        return array(
            array('%1','!-d',''),
            array('$1','!-d',''),
        );
    }

    /**
     * @dataProvider matchProviderExceptions
     * @expectedException DomainException
     *
     * @param $test
     * @param $cond
     * @param $flags
     */
    function testDoUnsupportedMatchesThrowExceptionsFunction($test, $cond, $flags) {
        $condition = new Condition($test, $cond, $flags);
        $condition->linkRule($this->_rule);

        $condition->matches();
    }

}