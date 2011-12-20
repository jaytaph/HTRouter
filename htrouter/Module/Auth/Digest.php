<?php

namespace HTRouter\Module\Auth;
use HTRouter\ModuleInterface;

class Digest extends \AuthModule {

    public function authenticateUser(\HTRequest $request) {
        print "<h2>SPLHASH (2): ".spl_object_hash($this)."</h2>";
        print "Trying to authenticate the digest user...";

        print "DIGEST!";
    }

    public function getName() {
        return "auth_digest";
    }

}