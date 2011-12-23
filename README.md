HTRouter
========
This is a very rudimentary setup for a router system that will mimic a Apache's .htaccess file. This way it is
possible to use a .htaccess file together with the PHP 5.4's built-in webserver.

The whole idea will be that we should be able to run any framework or system that normally depends on htaccess
(mostly the rewrite parts):

> $ php -S 127.0.0.1:8080 -t /var/www router.php

The router script should read and interpret the .htaccess file found in the current directory and rewrite / fetch
the actual file it needs.


Work in progress
----------------
This is work in progress. Allthough though some .htaccess functionality is already possible, there is still a lot of
work that needs to be done to implement it so it can run out of the box!


Installation
------------
The software is packaged as a PHAR file. This means the whole setup can be run by the following:

> Download the PHAR file (htrouter.phar) or build by running phar/buildphar.php
> $ php -S 0.0.0.0:80 -t /var/www path/to/htrouter.phar


Warning
=======
The internal PHP webserver is *NOT* meant to be a replacement for any production webserver. It should only used for
development purposes! 


