<?php

function writeBack ($user, $data, $stack) {
	$stack->sendTo($user->id, "echo", $data);
}

$handlers = [
	// on event => call callback
	// "event" => "callback" [function signature (User $user, array $data, DaemonData $stack)]
	"echo" => "writeBack",
];
