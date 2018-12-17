<?php
include 'functions.php';

$addendums = array(
    "_Can't you just use a clock?_",
    "_Why is this my purpose in life?_",
    "_Here I am, brain the size of a planet and they ask me to tell the time. Call that job satisfaction? 'Cos I don't._",
    "_Was that really easier than just looking at a clock?_",
    "_What mind-numbingly dull task shall I perform next?_"
);

function normalResponse($event) {
    global $addendums;
    $response = "It's ".getDateString(floor($event["ts"]));
    $time = date("g:i a", floor($event["ts"]));
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
    postMessage($event["channel"],$response);
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