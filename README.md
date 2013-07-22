http-websocket
==============

Setup
-----

Just some little example of a threaded websocket/http server.

To make it work, put your setup into includes/constants.php and create two tables:

```
+-------------------+
| users             |
+-------------------+
| id | other fields |
+-------------------+
```

```
+-----------------+
| session         |
+-----------------+
| id | session_id |
+-----------------+
```

Run it
------

index.php is what your webserver (apache, nginx, ...) should serve.

Run `php daemon.php` on your command line (in screen, in background etc. what ever you like)
