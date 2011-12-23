<?php

/**
 * Register the autoloader
 */
spl_autoload_register("htrouter_autoloader");

// Generic apache functionality
include "Apache.php";

function htrouter_autoloader($class) {

    // We need to strip HTROUTER\\ namespace
    if (strpos($class, "HTRouter\\") === 0) {
        $class = str_replace("HTRouter\\", "", $class);
    }

    // Namespace to directory conversion
    $class = str_replace("\\", "/", $class);

    $class = dirname(__FILE__)."/".$class.".php";

    include_once($class);
}