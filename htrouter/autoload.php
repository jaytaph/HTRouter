<?php

/**
 * Register the autoloader
 */
spl_autoload_register("htrouter_autoloader");

include "apache.php";


// @TODO: PSR-0?
function htrouter_autoloader($class) {

    // We need to strip HTROUTER\\ namespace
    if (strpos(strtolower($class), "htrouter\\") === 0) {
        $class = str_ireplace("HTRouter\\", "", $class);
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
