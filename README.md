Run CakePHP Apps from the PHP 5.4 built in webserver.
=======
**WARNING: This project is not actively maintained. I'm happy to merge PR request but I'm not able to fix any current issue myself** 

tl;dr: Use your plain .htaccess files on the PHP v5.4 built-in web server.


HTRouter
========
This is a modified version of jaytaph's HTRouter.  It is still very rudimentary.  But has been altered to work with CakePHP Apps, on Windows.

It may allow other apps to function as well.


Work in progress
----------------
This stuff is work in progress. Although though some .htaccess functionality is already possible there is still a lot of
work that needs to be done to implement it so it can run out of the box! This project mimics a lot of functionality of
the Apache2 (2.2) web-server.

We still need to do a lot of stuff on the mod_rewrite, and we must update the unit-tests and documentation!


Installation
------------
The software can be run without making a PHAR file like so:

* Set working directory to webroot for cakephp app.
* php.exe -S 0.0.0.0:8765 -t (path to webroot) HTRouter\phar\stub.php

The software is frequently packaged as a PHAR file. This means the whole setup can be run by the following:
=======

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
Don't use this instead of apache...  Even for development...  I am using this solely to provide a compact webserver for
an app that runs behind a firewall and pushes data from a client/server application to a web-server for further processing.
