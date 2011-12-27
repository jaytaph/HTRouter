<?php

/**
 * Register the autoloader
 */
spl_autoload_register("htrouter_autoloader");

// Generic apache functionality
require_once "Apache.php";

function htrouter_autoloader($class) {

    // We need to strip HTROUTER\\ namespace
    if (strpos($class, "HTRouter\\") === 0) {
        $class = str_replace("HTRouter\\", "", $class);
    }

    // Namespace to directory conversion
    $class = str_replace("\\", "/", $class);

    $classes = array();
    $classes[] = dirname(__FILE__)."/".$class.".php";
    $classes[] = dirname(__FILE__)."/Classes/".$class.".php";

    foreach ($classes as $class) {
        if (file_exists ($class)) {
            include_once($class);
        }
    }

}