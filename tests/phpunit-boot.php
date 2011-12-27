<?php

include "../htrouter/autoload.php";

$a = array();
$a['HTTP_HOST'] = 'htrouter.phpunit.example.org';
$a['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:8.0.1) Gecko/20100101 Firefox/8.0.1';
$a['HTTP_ACCEPT'] = '*/html,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
$a['HTTP_ACCEPT_LANGUAGE'] = 'en-us,en;q=0.5';
$a['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate';
$a['HTTP_ACCEPT_CHARSET'] = 'ISO-8859-1,utf-8;q=0.7,*;q=0.7';
$a['HTTP_CONNECTION'] = 'keep-alive';
$a['PATH'] = '/usr/local/bin:/usr/bin:/bin';
$a['SERVER_SIGNATURE'] = 'PHPUnit/1.0.0 (Debian) Server at htrouter.phpunit.example.org Port 80';
$a['SERVER_SOFTWARE'] = 'PHPUnit/1.0.0 (Debian)';
$a['SERVER_NAME'] = 'htrouter.phpunit.example.org';
$a['SERVER_ADDR'] = '192.168.56.101';
$a['SERVER_PORT'] = 80;
$a['REMOTE_ADDR'] = '192.168.56.1';
$a['DOCUMENT_ROOT'] = '/etc/apache2/htdocs';
$a['SERVER_ADMIN'] = 'info@example.org';
$a['SCRIPT_FILENAME'] = '/wwwroot/router/public/index.php';
$a['REMOTE_PORT'] = rand(10000, 65000);
$a['GATEWAY_INTERFACE'] = 'CGI/1.1';
$a['SERVER_PROTOCOL'] = 'HTTP/1.1';
$a['REQUEST_METHOD'] = 'GET';
$a['QUERY_STRING'] = '';
$a['REQUEST_URI'] = '/index.php';
$a['SCRIPT_NAME'] = '/index.php';
$a['PHP_SELF'] = '/index.php';
$a['PHP_AUTH_USER'] = 'phpunit';
$a['PHP_AUTH_PW'] = 'secret';
$a['REQUEST_TIME'] = time();

$_SERVER = $a;
