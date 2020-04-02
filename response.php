<?php
require 'Logger.php';

interface Response {
    function respond();
}

class ScoreboardResponse implements Response {
    private $slack;
    private $db;
    private $channel;

    function __construct(object $slack, object $database, string $channel) {
        $this->slack = $slack;
        $this->db = $database;
        $this->channel = $channel;
    }

    function respond() {
        $users = $this->db->get_scoreboard(3);
        $message = null;
        if(count($users) == 0) {
            $message = array(
                "channel"=>$this->channel,
                "text"=>"I have no customers"
            );
        } else {
            $message_text = "My top customers are:";
            for($i = 0; $i < count($users); $i++) {
                if($i === 0) { $mesasge_text .= "\n:first_place_medal: "; }
                if($i === 1) { $message_text .= "\n:second_place_medal: "; }
                if($i === 2) { $message_text .= "\n:third_place_medal: "; }

                $amount = number_format($users[$i]->balance, 2);
                $message_text .= "<@".$users[$i]->id."> with $".$amount;
                if($amount === "49.99") {
                    $message_text .= ":hyperlogo:";
                }
            }
            $message = array(
                "channel"=>$this->channel,
                "text"=>$message_text
            );
        }
        $this->slack->chat_postMessage($message);
    }
}

class FlexResponse implements Response {
    private $slack;
    private $db;
    private $channel;
    private $user;

    function __construct(object $slack, object $database, string $channel, string $user) {
        $this->slack = $slack;
        $this->db = $database;
        $this->channel = $channel;
        $this->user = $user;
    }

    function respond() {
        $message_text = null;

        $user = $this->db->get_user($this->user);
        if($user === null) {
            $message_text = "You, <@$this->user>, aren't one of my customers";
        } else {
            $rank = $this->db->get_user_rank($user->balance);
            $amount = number_format($user->balance, 2);
            $message_text = "You, <@$this->user>, are my #$rank customer with \$$amount";
        }

        $message = array(
            "channel"=>$this->channel,
            "text"=>$message_text
        );
        $this->slack->chat_postMessage($message);
    }
}

class TransferResponse implements Response {
    private $slack;
    private $db;
    private $channel;
    private $user;
    private $message;

    function __construct(object $slack, object $database, string $channel, string $user, string $message) {
        $this->slack = $slack;
        $this->db = $database;
        $this->channel = $channel;
        $this->user = $user;
        $this->message = $message;
    }

    private function post_plaintext($message_text) {
        $message = array(
            "channel"=>$this->channel,
            "text"=>$message_text
        );
        $this->slack->chat_postMessage($message);
        return;
    }

    function respond() {
        $verbs = explode(" ", $this->message);

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

        $this->db->add_user_if_not_exists($this->user);
        $this->db->add_user_if_not_exists($reciever_str);

        $sender = $this->db->get_user($this->user);
        $reciever = $this->db->get_user($reciever_str);

        if($sender->id === $reciever->id) {
            $this->post_plaintext("You cant transfer to yourself!");
            return;
        }
        
        if($sender->balance - $amount < 0) {
            $this->post_plaintext("Insufficient funds. Your request to send $".number_format($amount, 2)." failed because you only have $".number_format($sender->balance, 2));
            return;
        }

        Logger::log_balance("Transfer: $amount\t from $sender->id to $reciever->id");

        $sender->balance -= $amount;
        $reciever->balance += $amount;

        $this->db->save_users(array($sender, $reciever));
    }
}

class TimePromptResponse implements Response {
    private $slack;
    private $db;
    private $channel;
    private $user;
    private $ts;

    function __construct(object $slack, object $database, string $channel, string $user, float $ts) {
        $this->slack = $slack;
        $this->db = $database;
        $this->channel = $channel;
        $this->user = $user;
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
                $now = DateTime::createFromFormat("U",floor($this->ts));
                if($start < $now && $now < $end) {
                    $add = true;
                }
            } else {
                $now = date("g:ia", floor($this->ts));
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

?>
