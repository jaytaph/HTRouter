<?php

// --------------------- %< ----------------------------
// @TODO: This must be removed
error_reporting(-1);
ini_set("display_errors", true);
ob_start();
// --------------------- %< ----------------------------

// Start autoloader
include_once (dirname(__FILE__)."/../htrouter/autoload.php");

$router = HTRouter::getInstance();
$router->route();
