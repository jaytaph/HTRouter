This is a very rudimentary setup for a router system that will mimic a Apache's .htaccess file.

This way it is possible to use a .htaccess file together with the PHP 5.4's internal webserver.

The whole idea will be that we should be able to run any framework or system that normally depends on htaccess
(mostly the rewrite parts):

$ php -S 127.0.0.1:8080 -t router.php

The router script should read and interpret the .htaccess file found in the current directory and rewrite / fetch
the actual file it needs.



