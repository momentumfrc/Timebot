<?php
include 'functions.php';

$TIMEBUCKS_RATE_LIMIT = 3600;
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
            #stopTimeout();
            $event = $data["event"];
            switch($event["type"]) {
                case "app_mention":
                    if(stripos($event["text"],"scoreboard") !== FALSE) {
                        $DB = createDBObject();
                        $scores = getScoreboard($DB);
                        $message = "";
                        if(count($scores) == 0) {
                           $message = array (
                               "channel"=>$event["channel"],
                               "text"=>"I have no customers"
                           );
                        } else {
                            $message = array(
                                "channel"=>$event["channel"],
                                "text"=>"My top 3 customers are:",
                                "attachments"=>array()
                            );
                            for($i = 0; $i < min(count($scores), 3); $i++ ) {
                                $message["attachments"][] = array(
                                    "text"=> ($i+1).". <@".$scores[$i]["id"]."> with $".number_format($scores[$i]["balance"],2)
                                );
                            }
                        }
                        postJSON(json_encode($message));
                    } elseif(stripos($event["text"],"flex") !== FALSE || stripos($event["text"],"rank") !== FALSE) {
                        $DB = createDBObject();
                        $scores = getScoreboard($DB);
                        $rank = 1;
                        $found = false;
                        foreach($scores as $score) {
                            if($score["id"] == $event["user"]) {
                                $found = true;
                                break;
                            }
                            $rank += 1;
                        }
                        if($found) {
                            $message = "You, <@".$scores[$rank-1]["id"].">, are my #".$rank." customer with $".number_format($scores[$rank-1]["balance"],2);
                        } else {
                            $message = "You, <@".$event["user"].">, aren't one of my customers";
                        }
                        postMessage($event["channel"],$message);
                    } else {
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
                            $message = "I'm sorry <@".$userinfo["id"].">, but you have insufficient TimeBucks!\nThis action costs $".number_format($cost,2)."\nYour current balance is $".number_format($userinfo["balance"],2);
                            postMessage($event["channel"],$message);
                        } else {
                            updateUserBalance($DB,$userinfo["id"],$timebucks);
                            postMessage($event["channel"],$response);
                        }
                    }
                    break;
                case "message";
                    # Prevent timebot from responding to itself
                    if($event["subtype"] == "bot_message" || $event["user"] == "") {
                        exit();
                    }

                    switch($event["channel_type"]) {
                        case "channel":
                            writeToLog("Message of type ".$event["channel_type"]." from ".$event["user"],"events");
                            $text = strtolower($event["text"]);
                            $matches = array();
        
                            $valid_time = false;
                            
                            writeToLog("Matching \"".$text."\" ?".(preg_match("/it\'?s 0?(([1-9]|1[0-2])\:[0-5][0-9]) ?(am|pm)/",$text)?"true":"false"),"events");

                            if(preg_match("/it(\'|’)?s 0?(([1-9]|1[0-2])\:[0-5][0-9]) ?(am|pm)/",$text,$matches)) {
                                # 12 hour time
                                $supposed_time = $matches[2].$matches[4];
                                $current_time = date("g:ia", floor($event["ts"]));
                                $valid_time = $supposed_time == $current_time;
                            } elseif(preg_match("/it(\'|’)?s 0?((1?[1-9]|2[0-4])\:[0-5][0-9])/",$text,$matches)) {
                                # 24 hour time
                                $supposed_time = $matches[2];
                                $current_time = date("G:i", floor($event["ts"]));
                                $valid_time = $supposed_time == $current_time;
                            }
        
                            if($valid_time) {
                                $DB = createDBObject();
                                checkUserInDB($DB, $event["user"]);
                                $userinfo = getUserInfo($DB,$event["user"]);
                                if(floor($event["ts"]) - $userinfo["cooldown"] > $TIMEBUCKS_RATE_LIMIT) {
                                    $timebucks = $userinfo["balance"] + $TIMEBUCKS_INCREMENT;
                                    updateUserBalance($DB, $userinfo["id"],$timebucks);
                                    $message = "Your TimeBucks balance is now $".number_format($timebucks,2);
                                    postEphemeral($event["channel"],$userinfo["id"],$message);
                                } else {
                                    postEphemeral($event["channel"],$userinfo["id"],"TimeBucks can only be earned once every ".($TIMEBUCKS_RATE_LIMIT/3600)." hours.\nYour next TimeBuck unlocks at ".date("g:i a",floor($event["ts"])+$TIMEBUCKS_RATE_LIMIT));
                                }
                                resetUserCooldown($DB, $userinfo["id"], floor($event["ts"]));
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