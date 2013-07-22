// socket.on('eventidentifier', (callable)eventHandler);
// if a message for the 'eventidentifier' is recieved, the passed eventHandler is executed with the message as parameter

// socket.emit('eventidentifier', data)
// sends data to server for the 'eventidentifier'

$.support.cors = true; // fix for IE

function socketPollHandler (addr, port) {
	var wsurl = "ws://" + addr + ":" + port + "/";
	var pollfallback = "http://" + addr + ":" + port + "/";

	WebSocketAvailable = typeof WebSocket !== 'undefined';

	this.handlers = [];

	this.gotData = function (data) {
		data = data.split("\x00");
		for (var i = 0; i < data.length; i += 2)
			socket.runHandlers(data[i], data[i + 1]);
	}

	this.runHandlers = function (on, jsondata) {
		console.log(jsondata);
		if (typeof socket.handlers[on] === 'undefined')
			return;
		data = JSON.parse(jsondata);
		for (var i = 0; i < this.handlers[on].length; i++)
			this.handlers[on][i](data);
	}

	this.on = function (on, handler) {
		if (typeof this.handlers[on] === 'undefined')
			this.handlers[on] = [];
		this.handlers[on].push(handler);
	}

	this.emit = function (on, data) {
		var jsondata = JSON.stringify(data);
		socket.poll(on, jsondata);
	}

	this.openHandlers = [];
	this.emitQueue = [];
	this.active = false;

	this.onstart = function (handler) {
		if (WebSocketAvailable)
			this.openHandlers.push(handler);
		if (socket.active)
			handler();
	};

	this.runOpenHandlers = function () {
		for (var i = 0; i < this.openHandlers.length; i++) {
			this.openHandlers[i]();
			console.log(i);
		}
	}

	if (WebSocketAvailable) {
		this.start = function () {
			setTimeout(function () { socket.__start() }, 0); // timeout-wrapper for preventing reaching stack recursion limit
		};
		this.__start = function () {
			this.socket = new WebSocket(wsurl);
			this.socket.onopen = function () {
				socket.active = true;
				socket.runOpenHandlers();
				for (var i = 0; i < socket.emitQueue.length; i++)
					socket.socket.send(socket.emitQueue[i]);
				socket.emitQueue = [];
			};
			this.socket.onerror = function (e) {
				// old socket
				socket.socket.close();
				socket.active = false;
				// new socket
				socket.start();
			};
			this.socket.onmessage = function (e) {
				socket.gotData(e.data);
			};
			this.socket.onclose = function () {
				socket.active = false;
			};
		};
		this.poll = function (on, data) {
			if (socket.active)
				socket.socket.send(on + "\x00" + data);
			else
				this.emitQueue.push(on + "\x00" + data);
		};
	}

	else {
		this.active = true;

		this.start = function () {
			socketPollHandler_timeout = setTimeout(function () { socket.poll(null) }, 0); // timeout-wrapper for preventing reaching stack recursion limit
		};

		socketPollHandler_ajax = null;

		this.open_requests = 0;

		this.poll = function (on, data) {
			//if (socketPollHandler_ajax !== null)
			//	socketPollHandler_ajax.abort(); // only one poll at a time. if the user is too fast (clicking), nothing will be send

			// polling
			this.open_requests++;
			socketPollHandler_ajax = $.ajax(pollfallback,
				{
					async: true,
					cache: false,
					type: "POST",
					data: (data !== null && data !== 'undefined' && on !== null && on !== 'undefined' ? { on: on, data: data } : ''),
					headers: {"X-POLL": "true"}, // prevent any CSRF try
					xhrFields: { withCredentials: true },
					success: function (data) {
						if (data == '')
							// message to user
							updateMinibarConnectionStatus(0); // disconnect
						if (--this.open_requests == 0)
							socket.start();

						if (data != '\x00')
							socket.gotData(data);
					},
					error: function(e, state) {
						if (--this.open_requests == 0)
							socket.start();

						if (state == 'abort') // Do NOT restart when it's manually aborted
							return;
						console.log(e);
					}
				});
		};
	}
}
