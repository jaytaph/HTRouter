<?php

namespace HTRouter\Module\Auth;
use HTRouter\ModuleInterface;

class Basic extends \AuthModule {

    public function authenticateUser(\HTRequest $request) {
        print "<h2>SPLHASH (2): ".spl_object_hash($this)."</h2>";
        print "Trying to authenticate the basic user...";

        print "BASIC!";
    }

    public function getName() {
        return "auth_basic";
    }

}