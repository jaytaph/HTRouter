<?php

use HTRouter\Module\Rewrite\Flag;


class module_rewrite_flagTest extends PHPUnit_Framework_TestCase {

    function testDoesGetTypeFunction() {
        $flag = new Flag(Flag::TYPE_BEFORE, "foo", "bar");
        $this->assertEquals(Flag::TYPE_BEFORE, $flag->getType());

        $flag = new Flag(Flag::TYPE_LAST, "foo", "bar");
        $this->assertEquals(Flag::TYPE_LAST, $flag->getType());
    }

    function testDoesGetKeyFunction() {
        $flag = new Flag(Flag::TYPE_LAST, "foo", "bar");
        $this->assertEquals("foo", $flag->getKey());
    }

    function testDoesGetValueFunction() {
        $flag = new Flag(Flag::TYPE_LAST, "foo", "bar");
        $this->assertEquals("bar", $flag->getValue());
    }

    function flagProvider() {
        return array(
            array(Flag::TYPE_BEFORE, null, null, "B"),
            array(Flag::TYPE_LAST, "foo", null, "L=foo"),
            array(Flag::TYPE_MIMETYPE, "foo", "bar", "T=foo:bar"),
            array(Flag::TYPE_QSA, "foo", "", "QSA=foo"),
        );
    }

    /**
     * @dataProvider flagProvider
     */
    function testDoesToStringFunction($type, $key, $value, $output) {
        $flag = new Flag($type, $key, $value);
        $this->assertEquals($output, (string)$flag);
    }

}