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

	./worker-start test_mapreduce.php

_worker-start_ is a simple bash tool to launch and detach 25 workers.

And one client:

	php  test_mapreduce.php --mapreduce

Don't forget to kill them all, when your test is finished

	killall php

API
---

The pattern is the classical [map-reduce](http://en.wikipedia.org/wiki/MapReduce) : 

You've got a pool of workers, dispatched between cpu cores, or even in different servers.
The main application send events via a Redis list, with function name and serialized arguments.
Worker use infinite loop, when its job is finished, it poll the task list, waiting for a new job.
Responses and errors came back with a Redis list.

The big picture of the map reduce test.

![Big picture](https://github.com/athoune/Worker-machine/raw/master/mapreduce.png)

You don't have to wait for each http download sequentialy, you can parralelized and saving time.
With 1 worker, 10 urls take 10 seconds, with 4 workers, it only take 3.7 seconds.

````php
require 'lib/worker.php';

function getTitle($url) {
	$html = file_get_contents("http://$url");
	if($html == FALSE) {
		throw new Exception("can't fetch url");
	}
	preg_match('/<title>(.*)<\/title>/i', $html, $matches);
	return html_entity_decode($matches[1]);
}

if($argv[1] == '--mapreduce') {
	$sites = array(
		'linuxfr.org',
		'www.slashdot.org',
		'www.boingboing.net',
		'www.rue89.com',
		'blog.makezine.com',
		'www.4chan.org',
		'news.ycombinator.com',
		'danstonchat.com',
		'freshmeat.net',
		'www.flickr.com'
	);
	$worker = new Worker();
	foreach($worker->batch('getTitle', $sites) as $title) {
		echo " $title\n";
	}
}
```

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
