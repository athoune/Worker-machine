Worker-machine
==============

Async worker for PHP, with a little help from Redis.

For now, it's just a POC.

Testing it
----------

Launch Redis

	redis-server

In differents terminals, launch some workers

	php test.php worker

In an other terminal, lauch the test

	php test.php
