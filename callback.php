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
if($headers["Content-Type"] !== "application/x-www-form-urlencoded") {
    request_error("invalid_mime_type");
}

// TODO: maybe validate that the two input variables exist and aren't empty?
$slack = new SlackClient($bot_token, $slack_signing_secret);

if(!$slack->verify_request_origin()) {
    request_error("not_authed");
}

$data = json_decode($_POST["payload"], true);

if(!isset($data["type"])) {
    request_error("invalid_request");
}

$db = new Database($database, $table, $dbuser, $dbpass);

switch($data["type"]) {
    case "block_actions":
        $actions = json_decode(file_get_contents("actions.json"), true);

        $selected = $data["actions"][0]["value"];

        $response = "It's ".SlackClient::format_date_string(floor($data["container"]["message_ts"]));
        $cost = 1;

        $display = "Normal response";

        foreach($actions as $action) {
            if($selected == $action["name"]) {
                $response = $action["value"];
                $response = str_replace("%timestring%",SlackClient::format_date_string(floor($data["container"]["message_ts"])), $response);
                $cost = $action["cost"];
                $display = $action["display"];
                break;
            }
        }

        $slack->use_response_url($data["response_url"], "You chose *".$display."*", true);

        $db->add_user_if_not_exists($data["user"]["id"]);
        $user = $db->get_user($data["user"]["id"]);
        $new_balance = $user->balance - $cost;
        if($new_balance < 0) {
            $message = array(
                "channel"=>$data["channel"]["id"],
                "text"=>"I'm sorry <@$user->id>, but you have insufficient TimeBucks!\nThis action costs $".number_format($cost,2)."\nYour current balance is $".number_format($user->balance,2)
            );
            $slack->chat_postMessage($message);
            break;
        }

        $user->balance = $new_balance;
        $db->save_user($user);

        $message = array(
            "channel"=>$data["channel"]["id"],
            "text"=>$response
        );
        $slack->chat_postMessage($message);
    break;
    default:
    break;
}

?>
