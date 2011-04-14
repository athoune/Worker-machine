Worker-machine
==============

Async worker for PHP, with a little help from Redis.

For now, it's just a POC.

Why should I use this?
----------------------

 * You want to launch a task without waiting for the result
 * You want to do heavy stuff, using all your CPUs, even all your servers
 * You want to handle async events, like xmpp connection
 * You want to try Redis

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

API
---

The pattern is the classical [map-reduce](http://en.wikipedia.org/wiki/MapReduce) : 

You've got a pool of workers, dispatched between cpu cores, or even in different servers.
The main application send events via a Redis list, with function name and serialized arguments.
Worker use infinite loop, when its job is finished, it poll the task list, waiting for a new job.
Responses and errors came back with a Redis pubsub.

Some magical global variables is provided :

 * **$\_PID**, the id of the batch.
 * **$\_CONTEXT**, a Redis hash for sharing stuff in a batch.

The big picture of the map reduce test.

![Big picture](https://github.com/athoune/Worker-machine/raw/master/mapreduce.png)

You don't have to wait for each http download sequentialy, you can parralelized and saving time.
With 1 worker, 10 urls take 10 seconds, with 4 workers, it only take 3.7 seconds.

Features and todo
-----------------

 * √ Fire and forget
 * √ Map-reduce
 * _ Multiple queue
 * _ Watching worker with something like [Launchtool](http://people.debian.org/~enrico/launchtool.html)
 * √ Handling errors
 * _ XMPP example
 * _ http frontend
 * _ metrics
