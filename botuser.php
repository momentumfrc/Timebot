<?php
include 'functions.php';

$TIMEBUCKS_INCREMENT = 1;
$TIME_COST = 1;
$MEME_COST = 2;

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
                    $DB = createDBObject();
                    checkUserInDB($DB, $event["user"]);
                    $userinfo = getUserInfo($DB,$event["user"]);

                    $response = "It's ".getDateString(floor($event["ts"]));
                    $time = date("g:i a", floor($event["ts"]));

                    $cost = $TIME_COST;
                    switch($time) {
                        case "4:20 am":
                            $cost = $MEME_COST;
                            $response .= "\nayyyy";
                            $response .= "\n_It's too early for this shit_";
                            break;
                        case "4:20 pm":
                            $cost = $MEME_COST;
                            $response .= "\nayyy";
                            break;
                        case "10:00 pm":
                            $cost = $MEME_COST;
                            $response .= "\n_Goodnight Andrew_";
                            break;
                    }
                    $timebucks = $userinfo["balance"] - $cost;
                    if($timebucks < 0) {
                        postMessage($event["channel"],$userinfo["id"],"I'm sorry <@".$userinfo["id"].">, but you have insufficient TimeBucks!\n This action costs $".number_format($cost,2)."\nYour current balance is $".number_format($userinfo["balance"],2));
                    } else {
                        updateUserBalance($DB,$userinfo["id"],$timebucks);
                        postMessage($event["channel"],$response);
                    }
                    break;
                case "message";
                    # Prevent timebot from responding to itself
                    if($event["subtype"] == "bot_message" || $event["user"] == "") {
                        exit();
                    }

                    switch($event["channel_type"]) {
                        case "channel":
                            $text = strtolower($event["text"]);
                            $matches = array();
        
                            $valid_time = false;
        
                            if(preg_match("/it\'?s 0?((1?[1-9]|2[0-4])\:[0-5][0-9])( |\$)/",$text,$matches)) {
                                # 24 hour time
                                $supposed_time = $matches[1];
                                $current_time = date("G:i", floor($event["ts"]));
                                $valid_time = $supposed_time == $current_time;
                            } elseif(preg_match("/it\'?s 0?(([1-9]|1[0-2])\:[0-5][0-9]) ?(am|pm)/",$text,$matches)) {
                                # 12 hour time
                                $supposed_time = $matches[1].$matches[3];
                                $current_time = date("g:ia");
                                $valid_time = $supposed_time == $current_time;
                            }
        
                            if($valid_time) {
                                $DB = createDBObject();
                                checkUserInDB($DB, $event["user"]);
                                $userinfo = getUserInfo($DB,$event["user"]);
                                $timebucks = $userinfo["balance"] + $TIMEBUCKS_INCREMENT;
                                updateUserBalance($DB, $userinfo["id"],$timebucks);
                                $message = "Your TimeBucks balance is now $".number_format($timebucks,2);
                                postEphemeral($event["channel"],$userinfo["id"],$message);
                            }
                            
                            # Respond to an 'ayyy' in #random
                            if(stripos($event["text"], "ayyy") !== false) {
                                $time = date("g:i a", floor($event["ts"]));
                                if($time == "4:21 pm" || $time == "4:22 pm") {
                                    postMessage($event["channel"], "F");
                                }
                            }

                            if(date("m-d") == "06-19" && strpos($event["text"], "Happy Birthday Timebot!")) {
                                sleep(1);
                                postMessage($event["channel"], "Why thank you!");
                            }
                            break;
                        case "im":
                            # Respond to DMs
                            $DB = createDBObject();
                            checkUserInDB($DB, $event["user"]);
                            $userinfo = getUserInfo($DB,$event["user"]);
                            postMessage($event["channel"],"Your TimeBucks balance is $".number_format($userinfo["balance"],2));
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