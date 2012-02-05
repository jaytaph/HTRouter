<?php

// --------------------- %< ----------------------------
// @TODO: This must be removed
error_reporting(-1);
ini_set("display_errors", true);
ob_start();
// --------------------- %< ----------------------------

// Start autoloader
include_once (__DIRNAME__."/../htrouter/autoload.php");

$router = HTRouter::getInstance();
$router->route();
