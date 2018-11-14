<?php
include 'specificvars.php';

date_default_timezone_set("America/Los_Angeles");

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

$addendums = array(
    "_Can't you just use a clock?_",
    "_Why is this my purpose in life?_",
    "_Here I am, brain the size of a planet and they ask me to tell the time. Call that job satisfaction? 'Cos I don't._",
    "_Was that really easier than just looking at a clock?_",
    "_What mind-numbingly dull task shall I perform next?_"
);

private $startTime = null;

    public function __construct($showSeconds = true)
    {
        $this->startTime = microtime(true);
    }

    public function __destruct()
    {
        $endTime = microtime(true);
        $time = $endTime - $this->startTime;

        $hours = (int)($time / 60 / 60);
        $minutes = (int)($time / 60) - $hours * 60;
        $seconds = (int)$time - $hours * 60 * 60 - $minutes * 60;
    }
$counter = 0;
$resetCounter = 60;

function normalResponse($event) {
    global $addendums;
    $response = "It's ".getDateString(floor($event["ts"]));
    $time = date("g:i a", floor($event["ts"]));
	global $counter;
	global $resetTime;
	
	if($counter <= 4){
		switch($time) {
			case "4:20 am":
				$response .= "\nayyyy";
				$response .= "\n_It's too early for this shit_";
				break;
			case "4:20 pm":
				$date = (int)date("dmY");
				srand($date);
				$val = rand(0,4);
				if($val == 1) {
					$response .= "\nayyy";
				} elseif ($val == 2) {
					$response = "no";
				} elseif ($val == 3) {
					$response = 'Please pay $0.99 to unlock this dank meme';
				}
				break;
			case "10:00 pm":
				$response .= "\n_Goodnight Andrew_";
				break;
			case "11:11 pm":
				$response .= "\n_Make a wish!_";
				break;
			default:
				if(rand(0,4) == 1) {
					$response .= "\n".$addendums[array_rand($addendums)];
				}
				break;
		}
	} elseif ($counter >4 ){
		$response .= "\n_I have more important things to be doing_"
	}
		postMessage($event["channel"],$response);
		$counter = $counter + 1
	if($seconds == 60){
		$counter = 0;
		$this->startTime = microtime(true);
	}
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
                    # Moved all the logic to the normalResponse() method so that timebot can respond to phrases other than just '@timebot'
                    normalResponse($event);
                    break;
                case "message":
                    #writeToLog("Message of type ".$event["channel_type"]." from ".$event["user"],"events");
                    if($event["user"] == "") {
                        exit();
                    }
                    switch($event["channel_type"]) {
                        case "channel":
                            # Respond to an 'ayyy' in #random
                            if(stripos($event["text"], "ayyy") !== false) {
                                $time = date("g:i a", floor($event["ts"]));
                                if($time == "4:21 pm" || $time == "4:22 pm") {
                                    postMessage($event["channel"], "F");
                                }
                            }
                            # Respond to 'Timebot, ACTIVATE' in the same way as '@timebot'
                            if(strpos($event["text"], "Timebot, ACTIVATE") !== false) {
                                normalResponse($event);
                            }
                            if(date("m-d") == "06-19" && strpos($event["text"], "Happy Birthday Timebot!")) {
                                sleep(1);
                                postMessage($event["channel"], "Why thank you!");
                            }
                            break;
                        case "im":
                            # Respond to DMs
                            $response = "It's ".getDateString(floor($event["ts"]));
                            $response .= "\n".$addendums[array_rand($addendums)];
                            postMessage($event["channel"], $response);
                            break;
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