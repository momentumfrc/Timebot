<?php
require 'Logger.php';

class ChannelMessage {
    public $type;
    public $channel;
    public $channel_type;
    public $user;
    public $text;
    public $ts;

    function __construct(string $type, string $channel, string $user, string $text, float $ts) {
        $this->type = $type;
        $this->channel = $channel;
        $this->user = $user;
        $this->text = $text;
        $this->ts = $ts;
    }
}

interface Response {
    function __construct(SlackClient $slack, Database $database, ChannelMessage $message);
    function respond();
}

interface CommandResponse extends Response {
    static function get_trigger_words();
}

interface ConversationResponse extends Response {
    static function should_respond($message_text);
}

class ScoreboardResponse implements CommandResponse {
    private $slack;
    private $db;
    private $message;

    function __construct(SlackClient $slack, Database $database, ChannelMessage $message) {
        $this->slack = $slack;
        $this->db = $database;
        $this->message = $message;
    }

    static function get_trigger_words() {
        return array("scoreboard");
    }

    function respond() {
        $users = $this->db->get_scoreboard(3);
        $message = null;
        if(count($users) == 0) {
            $message = array(
                "channel"=>$this->message->channel,
                "text"=>"I have no customers"
            );
        } else {
            $message_text = "My top customers are:";
            $current_balance = $users[0]->balance;
            $current_level = 0;
            foreach($users as $user) {
                if($user->balance != $current_balance) {
                    $current_balance = $user->balance;
                    $current_level += 1;
                }
                if($current_level === 0) { $mesasge_text .= "\n:first_place_medal: "; }
                if($current_level === 1) { $message_text .= "\n:second_place_medal: "; }
                if($current_level === 2) { $message_text .= "\n:third_place_medal: "; }
                if($current_level >= 3) { break; }

                $amount = number_format($users[$i]->balance, 2);
                $message_text .= "<@".$users[$i]->id."> with $".$amount;
                if($amount === "49.99") {
                    $message_text .= ":hyperlogo:";
                }
            }
            $message = array(
                "channel"=>$this->message->channel,
                "text"=>$message_text
            );
        }
        $this->slack->chat_postMessage($message);
    }
}

class FlexResponse implements CommandResponse {
    private $slack;
    private $db;
    private $message;

    function __construct(SlackClient $slack, Database $database, ChannelMessage $message) {
        $this->slack = $slack;
        $this->db = $database;
        $this->message = $message;
    }

    static function get_trigger_words() {
        return array("flex", "rank");
    }

    function respond() {
        $message_text = null;

        $user = $this->db->get_user($this->message->user);
        if($user === null) {
            $message_text = "You, <@".$this->message->user.">, aren't one of my customers";
        } else {
            $rank = $this->db->get_user_rank($user->balance);
            $amount = number_format($user->balance, 2);
            $message_text = "You, <@".$this->message->user.">, are my #$rank customer with \$$amount";
        }

        $message = array(
            "channel"=>$this->message->channel,
            "text"=>$message_text
        );
        $this->slack->chat_postMessage($message);
    }
}

class TransferResponse implements CommandResponse {
    private $slack;
    private $db;
    private $message;

    function __construct(SlackClient $slack, Database $database, ChannelMessage $message) {
        $this->slack = $slack;
        $this->db = $database;
        $this->message = $message;
    }

    static function get_trigger_words() {
        return array("gift", "send");
    }

    private function post_plaintext($message_text) {
        $message = array(
            "channel"=>$this->message->channel,
            "text"=>$message_text
        );
        $this->slack->chat_postMessage($message);
        return;
    }

    function respond() {
        $verbs = explode(" ", $this->message->text);

        if(strtolower($verbs[1]) !== "gift" && strtolower($verbs[1]) !== "send" && count($verbs) !== 4) {
            $this->post_plaintext("Invalid syntax.\nUsage: `@timebot gift [user] [amount]`");
            return;
        }

        $timebot = strtolower($verbs[0]);
        $raw_reciever = strtolower($verbs[2]);
        $raw_amount = $verbs[3];

        if(strtolower($timebot) === strtolower($user)) {
            $this->post_plaintext("What use would I have for TimeBucks?");
        }

        $amount = null;
        $matches = array();
        if(preg_match("/\\$?([0-9.]+)/", $raw_amount, $matches) && is_numeric($matches[1])) {
            $amount = $matches[1];
        } else {
            $this->post_plaintext("I'm sorry, $raw_amount is not a valid number of TimeBucks");
            return;
        }

        $reciever_str = null;
        $matches = array();
        if(preg_match("/<@([[:alnum:]]{9})>/",$raw_reciever, $matches)) {
            $reciever_str = strtoupper($matches[1]);
        } else {
            $this->post_plaintext("I'm sorry, $raw_reciever is not a valid user");
        }

        $this->db->add_user_if_not_exists($this->message->user);
        $this->db->add_user_if_not_exists($reciever_str);

        $sender = $this->db->get_user($this->message->user);
        $reciever = $this->db->get_user($reciever_str);

        if($sender->id === $reciever->id) {
            $this->post_plaintext("You cant transfer to yourself!");
            return;
        }
        
        if($sender->balance - $amount < 0) {
            $this->post_plaintext("Insufficient funds. Your request to send $".number_format($amount, 2)." failed because you only have $".number_format($sender->balance, 2));
            return;
        }

        Logger::log_balance("Transfer : $amount\t from $sender->id to $reciever->id");

        $sender->balance -= $amount;
        $reciever->balance += $amount;

        $this->db->save_users(array($sender, $reciever));
    }
}

class TimePromptResponse implements CommandResponse {
    private $slack;
    private $db;
    private $message;

    function __construct(SlackClient $slack, Database $database, ChannelMessage $message) {
        $this->slack = $slack;
        $this->db = $database;
        $this->message = $message;
    }

    static function get_trigger_words() {
        return array("");
    }

    function respond() {
        $actions = json_decode(file_get_contents("actions.json"), true);

        $message = array(
            "channel"=>$event["channel"],
            "user"=>$event["user"],
            "text"=>"Your device does not support choosing a message",
            "attachments"=>array(),
            "blocks"=>array(
                array(
                    "type"=>"section",
                    "text"=>array(
                        "type"=>"mrkdwn",
                        "text"=>"What message would you like to send?"
                    )
                ), array(
                    "type"=>"actions",
                    "elements"=>array()
                )
            )
        );

        foreach($actions as $action) {
            $add = false;
            if($action["time"] === "any") {
                $add = true;
            } elseif(strpos($action["time"],"-") !== FALSE) {
                $times = explode("-",$action["time"]);
                $start = DateTime::createFromFormat("g:ia",$times[0]);
                $end = DateTime::createFromFormat("g:ia",$times[1]);
                $now = DateTime::createFromFormat("U",floor($this->message->ts));
                if($start < $now && $now < $end) {
                    $add = true;
                }
            } else {
                $now = date("g:ia", floor($this->message->ts));
                if($now == $action["time"]) {
                    $add = true;
                }
            }
            if($add) {
                $message["blocks"][1]["elements"][] = array(
                    "type"=>"button",
                    "text"=>array(
                        "type"=>"plain_text",
                        "text"=>$action["display"]
                    ),
                    "action_id"=>"tb_option",
                    "value"=>$action["name"],
                    "confirm"=>array(
                        "title"=>array(
                            "type"=>"plain_text",
                            "text"=>"Are you sure?"
                        ),
                        "text"=>array(
                            "type"=>"mrkdwn",
                            "text"=>"This action will use $".number_format($action["cost"],2)
                        ),
                        "ok_text"=>array(
                            "type"=>"plain_text",
                            "text"=>"Yes"
                        ),
                        "dismiss_text"=>array(
                            "type"=>"plain_text",
                            "text"=>"No"
                        )
                    )
                );
            }
        }

        $this->slack->chat_postEphemeral($message);
    }
}

class AwardTimebucksResponse implements ConversationResponse {
    private $slack;
    private $db;
    private $message;

    private const TIMEBUCKS_RATE_LIMIT = 3600;
    private const TIMEBUCKS_INCREMENT = 1;

    function __construct(SlackClient $slack, Database $database, ChannelMessage $message) {
        $this->slack = $slack;
        $this->db = $db;
        $this->message = $message;
    }

    static function should_respond(string $text) {
        return preg_match("/^it(\'|’)?s /", $text);
    }

    function respond() {
        $valid_time = false;

        $matches = array();

        $userinfo = $this->slack->user_info($this->message->user);
        $tz = floor($userinfo->tz_offset / 3600);

        $current_datetime = new DateTime("@".floor($this->message->ts));
        $current_datetime->setTimezone(new DateTimeZone($tz));

        if(preg_match("/^it(\'|’)?s ((0?[1-9]|1[0-2])\:[0-5][0-9]) ?(am|pm)/",$text,$matches)) {
            $supposed_time = $matches[2].$matches[4];
            $current_time = $current_datetime->format("g:ia");
            $valid_time = $supposed_time == $current_time;
        } elseif(preg_match("/^it(\'|’)?s (((0|1)?[0-9]|2[0-3])\:[0-5][0-9])/",$text,$matches)) {
            $supposed_time = $matches[2];
            $current_time = $current_datetime->format("G:i");
            $valid_time = $supposed_time == $current_time;
        }

        if($valid_time) {
            $this->db->add_user_if_not_exists($this->message->user);
            $user = $this->db->get_user($this->message->user);

            $message_text = null;

            if(floor($this->message->ts) - $user->cooldown < self::TIMEBUCKS_RATE_LIMIT) {
                $frequency_hours = self::TIMEBUCKS_RATE_LIMIT/3600;
                $hours_formatted = number_format($frequency_hours, 2)."hours";
                if($frequency_hours == 1) {
                    $hours_formatted = "hour";
                }
                $message_text = "TimeBucks can only be earned once every $hours_formatted.\nYour next TimeBuck unlocks at ".date("g:i a",floor($this->message->ts)+self::TIMEBUCKS_RATE_LIMIT);
            } else {
                $new_balance = $user->balance + self::TIMEBUCKS_INCREMENT;
                Logger::log_balance("Increment: id=$user->id old_balance:$user->balance new_balance:$new_balance");
                $user->balance = $new_balance;
                $message_text = "Your TimeBucks balance is now $".number_format($new_balance, 2);
            }

            $new_cooldown = floor($this->message->ts);

            // Lock cooldowns to 10 seconds before the minute
            $new_cooldown -= ($new_cooldown % 60) + 10;

            $user->cooldown = $new_cooldown;
            $this->db->save_user($user);
            

            $message = array(
                "channel"=>$this->message->channel,
                "text"=>$message_text
            );

            $this->slack->chat_postEphemeral($message);
        }
    }
}

class AyyyResponse implements ConversationResponse {
    private $slack;
    private $db;
    private $message;

    function __construct(SlackClient $slack, Database $database, ChannelMessage $message) {
        $this->slack = $slack;
        $this->db = $db;
        $this->message = $message;
    }

    static function should_respond(string $text) {
        return preg_match("/[Aa]y{3,4}/", $text);
    }

    function respond() {
        $time = date("g:i a", floor($this->message->ts));
        if($time == "4:21 pm" || $time == "4:22 pm") {
            $message = array(
                "channel"=>$this->message->channel,
                "text"=>"F"
            );
            $this->slack->chat_postMessage($message);
        }
    }
}

class DMResponse implements Response {
    private $slack;
    private $db;
    private $message;

    function __construct(SlackClient $slack, Database $database, ChannelMessage $message) {
        $this->slack = $slack;
        $this->db = $db;
        $this->message = $message;
    }

    function respond() {
        $this->db->add_user_if_not_exists($this->message->user);
        $user = $this->db->get_user($this->message->user);
        $rank = $this->db->get_user_rank($user->balance);
        $amount = number_format($user->balance, 2);
        $message = array(
            "channel"=>$this->message->channel,
            "text"=>"You are my #$rank customer with \$$amount"
        );
        $this->slack->chat_postMessage($message);
    }
}

$command_responses = array('ScoreboardResponse', 'FlexResponse', 'TransferResponse', 'TimePromptResponse');
$conversation_responses = array('AwardTimebucksResponse', 'AyyyResponse');

?>
