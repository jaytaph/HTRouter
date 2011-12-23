<?php


// @TODO: This must be removed
error_reporting(-1);
ini_set("display_errors", true);
ob_start();


if (php_sapi_name() == "apache2handler") {
    // This piece removes the router-script from some $_SERVER variables. It allows us to test
    // stuff through Apache/XDebug.

    $path = explode("/", $_SERVER['REQUEST_URI']);
    if (isset($path[1]) && $path[1] == basename(__FILE__)) {
        array_shift($path);
        array_shift($path);
    }

    $path = "/".join("/", $path);
    $_SERVER['REQUEST_URI'] = $path;
}

// --------------------- %< ----------------------------

// Start autoloader
include_once (dirname(__FILE__)."/../htrouter/autoload.php");

$router = new HTRouter();
$router->route();
