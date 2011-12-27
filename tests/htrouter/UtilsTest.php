<?php

class utilsTest extends PHPUnit_Framework_TestCase {

    /**
     * @var \HTRouter\Utils
     */
    protected $_utils;

    function setUp() {
        $this->_utils = new \HTRouter\Utils();
    }

    /**
     * @expectedException LogicException
     */
    function testDoesValidatePasswordThrowsExceptionsOnWrongType1Function() {
        $this->_utils->validatePassword('foobar', '$apr1$foobar');
    }

    /**
     * @expectedException LogicException
     */
    function testDoesValidatePasswordThrowsExceptionsOnWrongType2Function() {
        $this->_utils->validatePassword('foobar', '{SHA}foobar');
    }

    function testDoesValidatePasswordFunction() {
        $this->assertTrue($this->_utils->validatePassword('bar', 'aihoX8g71L/E6'));
        $this->assertTrue($this->_utils->validatePassword('pong', 'G59oew8j1zC36'));
    }


    function matchingIPprovider() {
        return array(
            array("192.168.56.101", "192.168.56.101", true),
            array("192.168.56",     "192.168.56.101", true),
            array("192.168.",       "192.168.56.101", true),
            array("192.",           "192.168.56.101", true),
            array("19",             "192.168.56.101", false),
            array("192.16.",        "192.168.56.101", false),

            array("192.168.56.101/32", "192.168.56.101", true),
            array("192.168.56.5/24",   "192.168.56.101", true),
            array("192.168.54.5/24",   "192.168.56.101", false),
            array("192.168.56.100/28", "192.168.56.101", true),
            array("192.168.56.50/28",  "192.168.56.101", false),
            array("192.41.52.63/8",    "192.168.56.101", true),

            array("192.168.56.63/255.255.255.0",    "192.168.56.101", true),
            array("192.164.63.101/255.255.255.0",   "192.168.56.101", false),
            array("192.168.56.63/255.255.255.128",  "192.168.56.101", true),
            array("192.168.56.163/255.255.255.128", "192.168.56.101", false),
        );
    }

    function matchingIPproviderExceptions() {
        return array(
            array("192.168.56.63/255.", "192.168.56.101"),
            array("192.164.63.101/255.255.", "192.168.56.101"),
            array("192.168.56.63/32", "999.999.999.999"),
            array("192.168.56.63/12", "test"),
            array("192.168.56.63/8", "foobar"),
            array("192.168.56.63/foo", "127.0.0.1"),
            array("192.168.56.63/255.255.255.0", "foobar"),
            array("192.168.56.63/foo.bar.255.0", "127.0.0.1"),
            array("foo.168.56.63/255.255.255.0", "127.0.0.1"),
            array("foo.168.56.63/32", "127.0.0.1"),
        );
    }

    /**
     * @dataProvider matchingIPprovider
     */
    function testDoesCheckMatchingIPFunction($src, $dst, $doesMatch) {
        if ($doesMatch) {
            $this->assertTrue($this->_utils->checkMatchingIP($src, $dst));
        } else {
            $this->assertFalse($this->_utils->checkMatchingIP($src, $dst));
        }
    }

    /**
     * @expectedException \UnexpectedValueException
     * @dataProvider matchingIPproviderExceptions
     */
    function testDoescheckMatchingIPExceptionsFunction($src, $dst) {
        $this->_utils->checkMatchingIP($src, $dst);
    }



    function testDoesCheckMatchingHostFunction() {
        // @TODO: This is a server that resolved back to itself (host -> ip -> host)
        $ip = gethostbyname("auth1.xs4all.nl");

        $this->assertTrue($this->_utils->checkMatchingHost("auth1.xs4all.nl", $ip));
        $this->assertTrue($this->_utils->checkMatchingHost("xs4all.nl", $ip));
        $this->assertTrue($this->_utils->checkMatchingHost(".xs4all.nl", $ip));
        $this->assertFalse($this->_utils->checkMatchingHost("foobar.xs4all.nl", $ip));
        $this->assertFalse($this->_utils->checkMatchingHost("4all.nl", $ip));
        $this->assertTrue($this->_utils->checkMatchingHost(".nl", $ip));
    }

    function testDoesGetStatusLineFunction() {
        $this->assertEquals("Not Extended", $this->_utils->getStatusLine(510));
        $this->assertEquals("Not Found", $this->_utils->getStatusLine(404));
        $this->assertEquals("OK", $this->_utils->getStatusLine(200));
        $this->assertEquals("Moved Permanently", $this->_utils->getStatusLine(301));
    }

    /**
     * @expectedException OutOfBoundsException
     */
    function testDoesGetStatusLineExceptionFunction() {
        $this->_utils->getStatusLine(999);
    }


    function testDoesIsUrlFunction() {
        $this->assertTrue($this->_utils->isUrl("http://www.google.com"));
        $this->assertFalse($this->_utils->isUrl("://1"));
        $this->assertTrue($this->_utils->isUrl("1"));
        $this->assertTrue($this->_utils->isUrl("test"));
    }

    function testDoesfetchDirectiveFlagsFunction() {
        $this->assertEquals("on", $this->_utils->fetchDirectiveFlags("on", array("on" => "on", "off" => "off")));
        $this->assertEquals("on", $this->_utils->fetchDirectiveFlags("ON", array("on" => "on", "off" => "off")));
        $this->assertEquals(1, $this->_utils->fetchDirectiveFlags("on", array("on" => 1, "off" => 2)));
        $this->assertTrue($this->_utils->fetchDirectiveFlags("on", array("on" => true, "off" => false)));
        $this->assertFalse($this->_utils->fetchDirectiveFlags("off", array("on" => true, "off" => false)));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    function testDoesfetchDirectiveFlagsExceptionFunction() {
        $this->_utils->fetchDirectiveFlags("foobar", array("on" => "on", "off" => "off"));
    }


    /**
     * @dataProvider unparseProvider
     */
    function testDoesUnParseUrlFunction($arr, $url) {
        $this->assertEquals($url, $this->_utils->unparse_url($arr));
    }

    function unparseProvider() {
        return array(
            array(array("scheme" => "http",
                        "host" => "www.google.com"
                        ), "http://www.google.com"),
            array(array("scheme" => "http",
                        "host" => "www.google.com",
                        "user" => "foo"
                        ), "http://foo@www.google.com"),
            array(array("scheme" => "http",
                        "host" => "www.google.com",
                        "user" => "foo",
                        "pass" => "bar",
                        ), "http://foo:bar@www.google.com"),
            array(array("scheme" => "http",
                        "host" => "www.google.com",
                        "pass" => "bar",
                        ), "http://:bar@www.google.com"),
            array(array("scheme" => "http",
                        "host" => "www.google.com",
                        "fragment" => "bookmark"
                        ), "http://www.google.com#bookmark"),
            array(array("scheme" => "http",
                        "host" => "www.google.com",
                        "query" => "foo=bar",
                        "fragment" => "bookmark"
                        ), "http://www.google.com?foo=bar#bookmark"),
            array(array("scheme" => "ssl",
                        "host" => "www.google.com",
                        "query" => "foo=bar",
                        "fragment" => "bookmark",
                        "path" => "/test/path"
                        ), "ssl://www.google.com/test/path?foo=bar#bookmark"),
            array(array("scheme" => "ssl",
                        "host" => "www.google.com",
                        "query" => "foo=bar",
                        "port" => 1337,
                        "fragment" => "bookmark",
                        "path" => "/test/path"
                        ), "ssl://www.google.com:1337/test/path?foo=bar#bookmark"),
            );
    }

}