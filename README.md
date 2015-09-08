**WARNING: This project is not actively maintained. I'm happy to merge PR request but I'm not able to fix any current issue myself** 

tl;dr: Use your plain .htaccess files on the PHP v5.4 built-in web server.

![Travis CI Build Status](https://secure.travis-ci.org/jaytaph/HTRouter.png)

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

We still need to do a lot of stuff on the mod_rewrite, and we must update the unit-tests and documentation!


Installation
------------

The software is packaged as a PHAR file, which can be build by:

 1. Check in your `php.ini` that `phar.readonly = Off`
 2. Build the phar:

        $ git clone https://github.com/jaytaph/HTRouter.git
        $ php HTRouter/phar/buildphar.php

 3. You may now revert `phar.readonly` in your `php.ini`

Usage

 * $ php -S 0.0.0.0:80 -t /var/www htrouter.phar

Warning
=======
The built-in PHP web server is *NOT* meant to be a replacement for any production web server. It should used for
development purposes only! Not following this will result in me performing my infamous "I told you so!"-dance!