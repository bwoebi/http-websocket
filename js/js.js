var begRequest = 0;
$(document).on("click", "#randomBegButton", function() {
	socket.emit("randomNumber", ++begRequest);
});

socket.on("randomNumber", function (data) {
	$("#randNumber").text(data);
});
