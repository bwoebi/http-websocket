http-websocket
==============

Needed extensions
-----------------

 - [pthreads](https://github.com/krakjoe/pthreads)
 - [pecl_http](http://pecl.php.net/package/pecl_http)
 - (posix (just needed in error conditions for cleaner shutdown instead of segmentation fault))

Setup
-----

Just some little example of a threaded websocket/http server.

To make it work, put your setup into includes/constants.php.

If you want to use file based storage, make sure that the `includes/databases/rawFiles` directory contents are writable by the `index.php`/`general.php` and `daemon.php` executing user.

Run it
------

index.php is what your webserver (apache, nginx, ...) should serve.

Run `php daemon.php` on your command line (in screen, in background etc. what ever you like)
