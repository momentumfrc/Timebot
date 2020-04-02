<?php
require_once 'vars.php';
require_once 'slack-client.php';
require_once 'response.php';

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

$db = new Database($database, $table, $dbuser, $dbpass);

switch($data["type"]) {
    case "url_verification":
        header("Content-type: application/json");
        echo(json_encode(array("challenge"=>$data["challenge"])));
    break;
    case "event_callback":
        $event_assoc = $data["event"];
        $event = new ChannelMessage($event_assoc["type"], $event_assoc["channel"], $event_assoc["user"], $event_assoc["text"], $event_assoc["ts"]);
        switch($event->type) {
            case "app_mention":
                $matches = array();
                if(preg_match("/^\S*\s?([[:alnum:]]*)/", $event->text, $matches)) {
                    $command = $matches[1];
                    foreach($command_responses as $response_name) {
                        $words = ($response_name.'::get_trigger_words')();
                        if(in_array($command, $words)) {
                            $response = new $response_name($slack, $db, $event);
                            $response->respond();
                            break;
                        }
                    }
                }
            break;
            case "message":
                if($event_assoc["subtype"] === "bot_message" || $event["user"] == "") {
                    exit();
                }
                switch($event_assoc["channel_type"]) {
                    case "channel":
                        foreach($conversation_responses as $response_name) {
                            if( ($conversation_responses.'::should_respond')($event->text) ) {
                                $response = new $response_name($slack, $db, $event);
                                $response->respond();
                            }
                        }
                    break;
                    case "im":
                        $response = new DMResponse($slack, $db, $event);
                        $response->respond();
                    break;
                }
            break;
            default:
            break;
        }
    break;
    default:
    break;
}


?>
