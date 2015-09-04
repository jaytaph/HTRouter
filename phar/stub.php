<?php
//Clear out the index.php that the PHP Internal Webserver Adds...
if(strpos($_SERVER['REQUEST_URI'], "index.php") === false && $_SERVER['SCRIPT_NAME'] == '/index.php'){
    $_SERVER['SCRIPT_NAME'] = '';
    $_SERVER['PHP_SELF'] = str_replace("/index.php", "", $_SERVER['PHP_SELF']);
    $_SERVER['SCRIPT_FILENAME'] = str_replace("index.php", "", $_SERVER['SCRIPT_FILENAME']);
}
//var_dump($_SERVER);
$newPath = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'htrouter';
set_include_path(get_include_path() . PATH_SEPARATOR . $newPath);
include_once ("autoload.php");

$router = HTRouter::getInstance();
return $router->route();

