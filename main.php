<?php
require_once 'vars.php';
require_once 'slack-client.php';
require_once 'timebot.php';

function request_error(string $message) {
    http_response_code(400);
    die($message);
}

function server_error(string $message) {
    http_response_code(500);
    die($message);
}

if($_SERVER["REQUEST_METHOD"] !== "POST") {
    request_error("invalid_request_method");
}

// TODO: maybe validate that the two input variables exist and aren't empty?
$slack = new SlackClient($bot_token, $slack_signing_secret);

if(!$slack->verify_request_origin()) {
    request_error("not_authed");
}

$data = json_decode(file_get_contents('php://input'), true);

if(!isset($data["type"])) {
    request_error("invalid_request");
}


?>
