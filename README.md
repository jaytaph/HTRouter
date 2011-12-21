HTRouter
========
This is a very rudimentary setup for a router system that will mimic a Apache's .htaccess file.

This way it is possible to use a .htaccess file together with the PHP 5.4's internal webserver.

The whole idea will be that we should be able to run any framework or system that normally depends on htaccess
(mostly the rewrite parts):

> $ php -S 127.0.0.1:8080 -t router.php

The router script should read and interpret the .htaccess file found in the current directory and rewrite / fetch
the actual file it needs.



Installation
------------
Your router.php does not have to be inside your document root (far from it).

```php
<?php

include_once ("../htrouter/autoload.php");
$router = new HTRouter();
$router->route();
```


Packaging
---------
Will try to package this as a phar file. This would make that we can use "-t router.phar" without the need to install
the complete package.



Warning
=======
The internal PHP webserver is *NOT* ment to be a replacement for any production webserver. It should only used for 
development purposes! 


