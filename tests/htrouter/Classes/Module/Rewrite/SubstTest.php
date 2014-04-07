<?php

use HTRouter\Module\Rewrite\Rule;
use HTRouter\Module\Rewrite\Condition;
use HTRouter\Module\Rewrite\Flag;

class module_rewrite_substTest extends PHPUnit_Framework_TestCase {

    /**
     * @var HTRouter\Request
     */
    protected $_request;

    function setUp() {
        $router = \HTRouter::getInstance();

        $this->_request = new \HTRouter\Request();
        $this->_request->setFilename("foo");
        $this->_request->setDocumentRoot("/www");
        $this->_request->setHostname("php.unittest.org");
    }

    function testSubstitution_001() {
        $a = array("foo", "bar", "baz");

        //$condition = new Condition("%{HTTP_USER_AGENT}", ".+", "");
        $this->assertEquals("TESTfoo", Rule::expandSubstitutions("TEST$1", $this->_request, $a, array()));
        $this->assertEquals("TESTbar", Rule::expandSubstitutions("TEST$2", $this->_request, $a, array()));
        $this->assertEquals("TESTbaz", Rule::expandSubstitutions("TEST$3", $this->_request, $a, array()));
    }

    /**
     * @expectedException RuntimeException
     */
    function testSubstitution_002() {
        $a = array("foo", "bar", "baz");
        Rule::expandSubstitutions("TEST$5", $this->_request, $a, array());
    }

    function testSubstitution_003() {
        $a = array("do", "re", "mi");

        $this->assertEquals("TESTdo", Rule::expandSubstitutions("TEST%1", $this->_request, array(), $a));
        $this->assertEquals("TESTre", Rule::expandSubstitutions("TEST%2", $this->_request, array(), $a));
        $this->assertEquals("TESTmi", Rule::expandSubstitutions("TEST%3", $this->_request, array(), $a));
    }

    /**
     * @expectedException RuntimeException
     */
    function testSubstitution_004() {
        $a = array("do", "re", "mi");
        Rule::expandSubstitutions("TEST%5", $this->_request, array(), $a);
    }

    function testSubstitution_005() {
        $this->assertEquals("Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:8.0.1) Gecko/20100101 Firefox/8.0.1htrouter.phpunit.example.org*/html,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8", Rule::expandSubstitutions("%{HTTP_USER_AGENT}%{HTTP_REFERER}%{HTTP_COOKIE}%{HTTP_FORWARDED}%{HTTP_HOST}%{HTTP_PROXY_CONNECTION}%{HTTP_ACCEPT}", $this->_request));
    }

    function testSubstitution_006() {
        $_SERVER['REMOTE_HOST'] = "foo";
        $tmp = $_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_HOST'] . $_SERVER['REMOTE_PORT'];
        $this->assertEquals("$tmp", Rule::expandSubstitutions("%{REMOTE_ADDR}%{REMOTE_HOST}%{REMOTE_PORT}", $this->_request));
    }

    function testSubstitution_007() {
        $this->_request->setMethod("PATCH");
        $this->_request->setAuthUser("jthijssen");
        $this->assertEquals("jthijssenPATCH", Rule::expandSubstitutions("%{REMOTE_USER}%{REMOTE_IDENT}%{REQUEST_METHOD}", $this->_request));
    }

    function testSubstitution_008() {
        $this->_request->setFilename("foo.php");
        $this->_request->setPathInfo("/bar");
        $this->_request->setArgs(array("k" => "v", "t" => "s"));
        $this->assertEquals("foo.php/bark=v&t=s", Rule::expandSubstitutions("%{SCRIPT_FILENAME}%{PATH_INFO}%{QUERY_STRING}", $this->_request));
    }

    function testSubstitution_009() {
        $type = new \HTRouter\Module\Auth\Digest();
        $this->_request->setAuthType($type);
        $this->assertEquals("Digest", Rule::expandSubstitutions("%{AUTH_TYPE}", $this->_request));

        $this->_request->setAuthType(null);
        $this->assertEquals("", Rule::expandSubstitutions("%{AUTH_TYPE}", $this->_request));
    }

    function testSubstitution_010() {
        $this->assertEquals("/wwwinfo@example.orghtrouter.phpunit.example.org".$_SERVER['SERVER_ADDR']."80HTTP/1.1", Rule::expandSubstitutions("%{DOCUMENT_ROOT}%{SERVER_ADMIN}%{SERVER_NAME}%{SERVER_ADDR}%{SERVER_PORT}%{SERVER_PROTOCOL}", $this->_request));
    }

    function testSubstitution_011() {
        $this->assertEquals("123.45Apache/2.2.0 (HTRouter)", Rule::expandSubstitutions("%{API_VERSION}%{SERVER_SOFTWARE}", $this->_request));
    }

    function testSubstitution_012() {
        $date = Date("Y m dHiswYmdHis");
        $this->assertEquals("$date", Rule::expandSubstitutions("%{TIME_YEAR} %{TIME_MON} %{TIME_DAY}%{TIME_HOUR}%{TIME_MIN}%{TIME_SEC}%{TIME_WDAY}%{TIME}", $this->_request));
    }

    function testSubstitution_013() {
        $this->_request->setUri("blaat");
        $_SERVER['SCRIPT_FILENAME'] = "foo.php";
        $this->assertEquals("blaat /etc/apache2/htdocsblaat true off", Rule::expandSubstitutions("%{REQUEST_URI} %{REQUEST_FILENAME} %{IS_SUBREQ} %{HTTPS}", $this->_request));
    }

}