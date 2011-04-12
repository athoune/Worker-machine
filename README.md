Worker-machine
==============

Async worker for PHP, with a little help from Redis.

For now, it's just a POC.

Why should I use this?
----------------------

 * You wont to launch task without waiting for the result
 * You wont to do heavy stuff, using all your CPU, even all your server
 * You wont to handle async events, like xmpp connection
 * You wont to try Redis

Testing it
----------

Launch Redis

	redis-server

In differents terminals, launch some workers

	php test.php --worker

In an other terminal, lauch the test

	php test.php --async

---

Another test, the classic map/reduce, just like Google :

Launch some workers:

	php test_mapreduce.php --worker

And one client:

	php  test_mapreduce.php --mapreduce

Features and todo
-----------------

 * √ Fire and forget
 * √ Map-reduce
 * _ Multiple queue
 * _ Watching worker with something like [Launchtool](http://people.debian.org/~enrico/launchtool.html)
 * _ Handling errors
 * _ XMPP example
 * _ http frontend
 * _ metrics