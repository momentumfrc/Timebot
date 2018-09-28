<?php
include 'specificvars.php';

date_default_timezone_set("America/Los_Angeles");

/**
* Preform a GET request on a specified url with the specified parameters
* @param string $url The url to query
* @param array $opts The url paramteters
* @return string The server's response
*/
function get_query_slack($url,$opts) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url.'?'.http_build_query($opts));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}
/**
 * Preform a POST request on a specified url with the specified parameters
* @param string $url The url to query
* @param array $opts The url paramteters
* @return string The server's response
*/
function post_query_slack($url,$opts) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($opts));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}
function postMessage($channel, $message) {
    global $bot_token;
    post_query_slack("https://slack.com/api/chat.postMessage",array("token"=>$bot_token,"channel"=>$channel,"text"=>$message));
}
function writeToLog($string, $log) {
	file_put_contents("./".$log.".log", date("d-m-Y_h:i:s")."-- ".$string."\r\n", FILE_APPEND);
}
function stopTimeout() {
    ignore_user_abort(true);
    ob_start();
    header('Connection: close');
    header('Content-Length: '.ob_get_length());
    http_response_code(200);
    ob_end_flush();
    flush();
}
function verifySlack() {
    global $slack_signing_secret;
    $headers = getallheaders();
    if(! (isset($headers['X-Slack-Request-Timestamp']) && isset($headers['X-Slack-Signature']))) {
      die("Invalid headers");
    }
    if(abs(time() - $headers['X-Slack-Request-Timestamp']) > 60 * 5) {
      die("Request too old");
    }
    $signature = 'v0:' . $headers['X-Slack-Request-Timestamp'] . ":" . file_get_contents('php://input');
    $signature_hashed = 'v0=' . hash_hmac('sha256', $signature, $slack_signing_secret);
    return hash_equals($signature_hashed, $headers['X-Slack-Signature']);
  }
function getDateString($timestamp) {
    return "<!date^".$timestamp."^{time}|".date('g:i A', $timestamp).">";
}
if($_SERVER["REQUEST_METHOD"] == "POST" && verifySlack()) {
    $headers = getallheaders();
    if(isset($headers["X-Slack-Retry-Reason"])) {
        writeToLog("Slack retry because ".$headers["X-Slack-Retry-Reason"],"events");
    }
    $data = json_decode(file_get_contents("php://input"), true);
    switch($data["type"]) {
        case "url_verification":
            writeToLog("Slack url verification","events");
            header("Content-type: application/json");
            echo(json_encode(array("challenge"=>$data["challenge"])));
            break;
        case "event_callback":
            stopTimeout();
            $event = $data["event"];
            switch($event["type"]) {
                case "app_mention":
                    writeToLog("Mention","events");
                    $response = "It's ".getDateString(floor($event["ts"]));
                    $time = date("g:i a", floor($event["ts"]));
                    switch($time) {
                        case "4:20 pm":
                            if(rand(0,4) == 0) {
                                $response .= "\nayyyy";
                            }
                            break;
                        case "10:00 pm":
                            $response .= "\n_Goodnight Andrew_";
                            break;
                    }
                    postMessage($event["channel"],$response);
                    break;
                case "message":
                    writeToLog("Message","events");
                    if(strpos($event["text"], "ayyy") !== false) {
                        $time = date("g:i a", floor($event["ts"]));
                        if($time == "4:21 pm" || $time == "4:22 pm") {
                            postMessage($event["channel"], "F");
                        }
                    }
                    break;
            }
            break;
        default:
            break;
    }
} else {
    echo("You're not supposed to be here");
}
?>