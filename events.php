<?php
require_once 'vars.php';
require_once 'slack-client.php';
require_once 'response.php';
require_once 'database.php';
require_once 'logger.php';

date_default_timezone_set("America/Los_Angeles");

function request_error(string $message) {
    http_response_code(400);
    error_log("request_error: $message");
    die(json_encode(array(
        "ok" => "false",
        "error" => $message
    )));
}

function server_error(string $message) {
    http_response_code(500);
    error_log("server_error: $message");
    die(json_encode(array(
        "ok" => "false",
        "error" => $message
    )));
}

if($_SERVER["REQUEST_METHOD"] !== "POST") {
    request_error("invalid_request_method");
}

$headers = getallheaders();
if($headers["Content-Type"] !== "application/json") {
    request_error("invalid_mime_type");
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
        switch($event_assoc["type"]) {
            case "app_mention":
                $event = new ChannelMessage($event_assoc["type"], $event_assoc["channel"], $event_assoc["user"], $event_assoc["text"], $event_assoc["ts"]);
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
                if(isset($event_assoc["subtype"])) {
                    break;
                }
                if(isset($event_assoc["bot_id"])) {
                    break;
                }
                $event = new ChannelMessage($event_assoc["type"], $event_assoc["channel"], $event_assoc["user"], $event_assoc["text"], $event_assoc["ts"]);
                switch($event_assoc["channel_type"]) {
                    case "channel":
                        foreach($conversation_responses as $response_name) {
                            if( ($response_name.'::should_respond')(strtolower($event->text)) ) {
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
