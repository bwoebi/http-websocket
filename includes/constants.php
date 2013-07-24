<?php

const
	DB_CLASS = "rawFiles",
	SQL_USER = "",
	SQL_PASS = "",
	SQL_NAME = "",
	SQL_HOST = "localhost";

const
	PROC_TITLE = "WebSocket Daemon";

const
	SEQUENTIAL_PARALLEL_WORKERS = 16; // two per CPU (when one thread is blocked because of i/o (or sql))

const
	DAEMON_PORT_LISTEN  = 8989,
	DAEMON_HTTP_ADDR_LISTEN  = 'websocketpoll.local',
	DAEMON_ADDR_LISTEN  = '0.0.0.0',
	DAEMON_SLEEP_TIME   = 5, // milliseconds
	DAEMON_SOCKET_BYTES = 65536;

const
	POLL_SLEEP_TIME     = 50, // milliseconds
	POLL_SHMOP_BYTES    = 3,
	POLL_MAX_ALIVE_TIME = 60; // seconds

// DON'T SET IT LOWER THAN 0.5; WILL CAUSE THE LOAD TO SKYROCKET
const
	MAX_SOCKET_SLEEP_TIME = 100;//2; // milliseconds
