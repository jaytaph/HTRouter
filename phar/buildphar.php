<?php

// Create a new htrouter phar file
@unlink('htrouter.phar');
$phar = new Phar('htrouter.phar', 0, 'htrouter.phar');

$basePath = realpath(dirname(__FILE__)."/../htrouter");
$phar->buildFromDirectory($basePath, '/\.php$/');
$phar->addFile(dirname(__FILE__). '/stub.php', '/stub.php');

// Add stub
$phar->setStub($phar->createDefaultStub('stub.php', 'stub.php'));

