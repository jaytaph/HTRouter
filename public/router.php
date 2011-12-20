<?php

apc_clear_cache();

// Setup autoloader
include_once ("../htrouter/autoload.php");

$router = new HTRouter();
$router->route();
