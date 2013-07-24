<?php

function writeBack ($user, $data, $stack) {
	$stack->sendTo($user->id, "echo", $data);
}

function randNr ($user, $data, $stack) {
	if (!is_numeric($data))
		return;

	$stack->sendTo($user->id, "randomNumber", ($user->rand ^ ((1111 * $data%3333) ^ ($data * $user->rand))) % 10000);
}

$handlers = [
	// on event => call callback
	// "event" => "callback" [function signature (User $user, array $data, DaemonData $stack)]
	"echo" => "writeBack",
	"randomNumber" => "randNr",
];
