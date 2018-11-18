<?php
include 'functions.php';

$TIMEBUCKS_RATE_LIMIT = 3600;
$TIMEBUCKS_INCREMENT = 1;

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
                    # SCOREBOARD
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
                                $attach = array(
                                    "text"=> ($i+1).". <@".$scores[$i]["id"]."> with $".number_format($scores[$i]["balance"],2)
                                );
                                if($i == 0) { $attach["color"] = "#FFD700"; }
                                if($i == 1) { $attach["color"] = "#COCOCO"; }
                                if($i == 2) { $attach["color"] = "#CD7F32"; }
                                $message["attachments"][] = $attach;
                            }
                        }
                        postJSON(json_encode($message));
                    # FLEX
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
                    # TRANSFER
                    } elseif (stripos($event["text"],"gift") !== FALSE || stripos($event["text"],"send")) {
                        // @timebot gift @user 10
                        $verbs = explode(" ",$event["text"]);

                        if( !(strtolower($verbs[1]) == "gift" || strtolower($verbs[1]) == "send") || count($verbs) != 4) {
                            postMessage($event["channel"], "Invalid syntax.\nUsage: `@timebot gift [user] [amount]`");
                            break;
                        }

                        $user = $verbs[2];
                        $amount = $verbs[3];

                        if($verbs[0] == $verbs[2]) {
                            postMessage($event["channel"], "What use would I have for TimeBucks?");
                            break;
                        }

                        $matches = array();
                        if(preg_match("/\\$?([0-9.]+)/", $amount, $matches) && is_numeric($matches[1])) {
                            $amount = $matches[1];
                        } else {
                            // post invalid amount error
                            postMessage($event["channel"], "I'm sorry, ".$verbs[3]." is not a valid number of TimeBucks");
                            break;
                        }
                        $matches = array();
                        $user_regex = false;
                        $user_info = false;
                        if(preg_match("/<@([[:alnum:]]{9})>/",$user, $matches)) {
                            $user_regex = true;
                            $user = strtoupper($matches[1]);
                        }
                        try {
                            $userinfo = getSlackProfile($user);
                            $user_info = true;
                        } catch (Exception $e) {
                            $user_info = false;
                        }
                        if(!$user_regex || !$user_info) {
                            // post invalid user error
                            postMessage($event["channel"], "I'm sorry, ".$verbs[2]." is not a valid user");
                            break;
                        }

                        writeToLog("Transferring ".$amount." from ".$event["user"]." to ".$user, "events");

                        $DB = createDBObject();
                        checkUserInDB($DB, $event["user"]);
                        checkUserInDB($DB, $user);
                        $senderinfo = getUserInfo($DB,$event["user"]);
                        $recieverinfo = getUserInfo($DB,$user);

                        $senderBalance = $senderinfo["balance"] - $amount;
                        $recieverBalance = $recieverinfo["balance"] + $amount;

                        if($senderBalance < 0) {
                            // post not enough timebucks error
                            postMessage($event["channel"], "I'm sorry, you can't send $".number_format($amount,2)." because you only have $".number_format($senderinfo["balance"],2));
                            break;
                        }

                        updateUserBalance($DB,$senderinfo["id"],$senderBalance);
                        updateUserBalance($DB,$recieverinfo["id"],$recieverBalance);

                        postMessage($event["channel"],"<@".$event["user"]."> sent <@".$user."> $".number_format($amount,2)."!");
                        
                    # NORMAL
                    } else {
                        $actions = json_decode(file_get_contents("actions.json"), true);

                        $message = array(
                            "channel"=>$event["channel"],
                            "user"=>$event["user"],
                            "text"=>"What message would you like to send?",
                            "attachments"=>array(
                                array(
                                    "text"=>"Choose a message",
                                    "fallback"=>"Your device does not support choosing a message",
                                    "callback_id"=>"tb_message",
                                    "actions"=>array()
                                )
                            )
                        );

                        foreach($actions as $action) {
                            $add = false;
                            if($action["time"] == "any") {
                                $add = true;
                            } elseif(strpos($action["time"],"-") !== FALSE) {
                                $times = explode("-",$action["time"]);
                                $start = DateTime::createFromFormat("g:ia",$times[0]);
                                $end = DateTime::createFromFormat("g:ia",$times[1]);
                                $now = DateTime::createFromFormat("U",floor($event["ts"]));
                                if($start < $now && $now < $end) {
                                    $add = true;
                                }
                            } else {
                                $now = date("g:ia", floor($event["ts"]));
                                if($now == $action["time"]) {
                                    $add = true;
                                }
                            }
                            if($add) {
                                $message["attachments"][0]["actions"][] = array(
                                    "name"=>"tb_option",
                                    "text"=>$action["display"],
                                    "type"=>"button",
                                    "value"=>$action["name"],
                                    "confirm"=>array(
                                        "title"=>"Are you sure?",
                                        "text"=>"This action will use $".number_format($action["cost"],2),
                                        "ok_text"=>"Yes",
                                        "dismiss_text"=>"No"
                                    )
                                );
                            }
                            
                        }
                        postEphemeralJSON(json_encode($message));
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

                            $userinfo = getSlackProfile($event["user"]);
                            $tz = floor($userinfo["tz_offset"]/3600);

                            writeToLog("Creating datetime using time ".$event["ts"]." and timezone offset ".$tz,"events");

                            if(preg_match("/it(\'|’)?s 0?(([1-9]|1[0-2])\:[0-5][0-9]) ?(am|pm)/",$text,$matches)) {
                                # 12 hour time
                                $supposed_time = $matches[2].$matches[4];
                                $current_datetime = new DateTime("@".floor($event["ts"]));
                                $current_datetime->setTimezone(new DateTimeZone($tz));
                                $current_time = $current_datetime->format("g:ia");
                                writeToLog("It's ".$current_time." and the user said it's ".$supposed_time,"events");
                                $valid_time = $supposed_time == $current_time;
                            } elseif(preg_match("/it(\'|’)?s 0?((1?[1-9]|2[0-4])\:[0-5][0-9])/",$text,$matches)) {
                                # 24 hour time
                                $supposed_time = $matches[2];
                                $current_datetime = new DateTime("@".floor($event["ts"]));
                                $current_datetime->setTimezone(new DateTimeZone($tz));
                                $current_time = $current_datetime->format("G:i");
                                writeToLog("It's ".$current_time." and the user said it's ".$supposed_time,"events");
                                $valid_time = $supposed_time == $current_time;
                            }
        
                            if($valid_time) {
                                $DB = createDBObject();
                                checkUserInDB($DB, $event["user"]);
                                $userinfo = getUserInfo($DB,$event["user"]);
                                if(floor($event["ts"]) - $userinfo["cooldown"] >= $TIMEBUCKS_RATE_LIMIT) {
                                    $timebucks = $userinfo["balance"] + $TIMEBUCKS_INCREMENT;
                                    updateUserBalance($DB, $userinfo["id"],$timebucks);
                                    $message = "Your TimeBucks balance is now $".number_format($timebucks,2);
                                    postEphemeral($event["channel"],$userinfo["id"],$message);
                                } else {
                                    postEphemeral($event["channel"],$userinfo["id"],"TimeBucks can only be earned once every ".($TIMEBUCKS_RATE_LIMIT/3600)." hours.\nYour next TimeBuck unlocks at ".date("g:i a",floor($event["ts"])+$TIMEBUCKS_RATE_LIMIT));
                                }
                                $newtime = floor($event["ts"]);
                                $newtime = $newtime - ($newtime % 60);
                                resetUserCooldown($DB, $userinfo["id"], $newtime);
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
                default:
                    break;
            }
            break;
        default:
            break;
    }
} else {
    writeToLog("Recieved improper request","events");
    echo("You're not supposed to be here");
}
?>