<?php
include 'specificvars.php';

function querySlack($url,$postdata) {
    $fields = http_build_query($postdata);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
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
    $data = json_decode(file_get_contents("php://input"), true);
    switch($data["type"]) {
        case "url_verification":
            header("Content-type: application/json");
            echo(json_encode(array("challenge"=>$data["challenge"])));
            break;
        case "event_callback":
            $event = $data["event"];
            switch($event["type"]) {
                case "app_mention":
                    break;
                case "message":
                    break;
            }
            break;
        default:
            break;
    }
}
?>