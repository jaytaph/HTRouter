tl;dr: Use your plain .htaccess files on the PHP v5.4 built-in webserver.

HTRouter
========
This is a very rudimentary setup for a router system that will mimic an Apache's .htaccess file. This way it is
possible to use a .htaccess file together with the PHP 5.4's built-in web server.

The whole idea will be that we should be able to run any framework or system that normally depends on .htaccess
configuration, which mostly is the rewrite parts and maybe some authentication/authorization.


Work in progress
----------------
This stuff is work in progress. Although though some .htaccess functionality is already possible there is still a lot of
work that needs to be done to implement it so it can run out of the box! This project mimics a lot of functionality of
the Apache2 (2.2) web-server.


Installation
------------
The software is packaged as a PHAR file. This means the whole setup can be run by the following:

> Download the PHAR file (htrouter.phar) or build by running phar/buildphar.php
> $ php -S 0.0.0.0:80 -t /var/www path/to/htrouter.phar


Warning
=======
The built-in PHP web server is *NOT* meant to be a replacement for any production web server. It should used for
development purposes only! Not following this will result in me performing my infamous "I told you so!"-dance!


