<?php

/**
 * Emulates the apache functionality. Does not do everything correctly yet.
 */

// Check if apache extension is loaded, if not emulate the functionality
if (!function_exists("apache_request_headers")) {

    function apache_child_terminate() {
        return false;
    }

    function apache_get_modules() {
        $router = \HTRouter::getInstance();
        return $router->getModulesAsList();
    }

    function apache_get_version() {
        $router = \HTRouter::getInstance();
        return $router->getServerSoftware();
    }

    function apache_getenv($variable, $walk_to_top) {
        return false;
    }

    function apache_lookup_uri($filename) {
        return false;
    }

    function apache_note($note_name, $note_value = null) {
        return "";
    }

    function apache_request_headers() {
        return array();
    }

    function apache_reset_timeout() {
        return true;
    }

    function apache_response_headers() {
        return array();
    }

    function apache_setenv($variable) {
        return false;
    }

    function getallheaders() {
        return apache_request_headers();
    }

    function virtual($filename) {
        return false;
    }

}