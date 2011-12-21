<?php
/**
 * Additional classes used throughout the system. Most of them would probably have a APR_* equivalent
 */

class HTUtils {
    function validatePassword($passwd, $hash) {
        if (substr($hash, 0, 6) == "$apr1$") {
            // Can't do APR's MD5
            throw new LogicException("Cannot verify APR1 encoded passwords.");
        }

        if (substr($hash, 0, 5) == "{SHA}") {
            // Can't do SHA
            throw new LogicException("Cannot verify SHA1 encoded password.");
        }

        // It's CRYPT
        $cryptedPassword = crypt($passwd, $hash);
        return ($cryptedPassword == $hash);
    }

}