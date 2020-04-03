<?php

class SlackUser {
    public $id;
    public $tz_offset;
    public $is_bot;

    function __construct(string $id, int $tz_offset, $is_bot) {
        $this->id = $id;
        $this->tz_offset = $tz_offset;
        $this->is_bot = $is_bot;
    }
}

class Auth {
    public $url;
    public $team;
    public $user;
    public $team_id;
    public $user_id;
    public $bot_id;

    function __construct(string $url, string $team, string $user, string $team_id, string $user_id, string $bot_id) {
        $this->url = $url;
        $this->team = $team;
        $this->user = $user;
        $this->team_id = $team_id;
        $this->user_id = $user_id;
        $this->bot_id = $bot_id;
    }
}

class SlackClient {
    private $token;

    function __construct(string $token, string $signing_secret) {
        $this->token = $token;
        $this->signing_secret = $signing_secret;
    }

    static function format_date_string($timestamp) {
        return "<!date^".$timestamp."^{time}|".date('g:i A', $timestamp).">";
    }

    private function get_query(string $url, array $data) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url.'?'.http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }

    private function post_query_json(string $url, array $data) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->token
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }

    private function error(string $method, string $message) {
        Logger::log_api("error in method $method : $message");
    }

    function verify_request_origin() {
        $headers = getallheaders();
        if(!isset($headers['X-Slack-Request-Timestamp']) || !isset($headers['X-Slack-Signature'])) {
            $this->error("verify_request_origin", "missing headers");
            return false;
        }
        if(abs(time() - $headers['X-Slack-Request-Timestamp']) > 60 * 5) {
            $this->error("verify_request_origin", "request too old");
            return false;
        }
        $signature = 'v0:' . $headers['X-Slack-Request-Timestamp'] . ":" . file_get_contents('php://input');
        $signature_hashed = 'v0=' . hash_hmac('sha256', $signature, $this->signing_secret);
        return hash_equals($signature_hashed, $headers['X-Slack-Signature']);
    }

    function chat_postMessage(array $message) {
        if(!isset($message["channel"]) || !isset($message["text"])) {
            throw new Exception("invalid message");
        }
        $response = $this->post_query_json("https://slack.com/api/chat.postMessage", $message);
        $response = json_decode($response, true);

        if(!isset($response["ok"]) || !$response["ok"]) {
            $message = "[BLANK]";
            if(isset($response["error"])) {
                $message = $response["error"];
            }
            $this->error("chat_postMessage", $message);
            return false;
        }

        return true;
    }

    function chat_postEphemeral(array $message) {
        if(
            !isset($message["attachments"])
            || !isset($message["channel"])
            || !isset($message["text"])
            || !isset($message["user"])
        ) {
            throw new Exception("invalid message");
        }

        $response = $this->post_query_json("https://slack.com/api/chat.postEphemeral", $message);
        $response = json_decode($response, true);

        if(!isset($response["ok"]) || !$response["ok"]) {
            $message = "[BLANK]";
            if(isset($response["error"])) {
                $message = $response["error"];
            }
            $this->error("chat_postMessage", $message);
            return false;
        }

        return true;
    }

    function user_info(string $id) {
        $data = array(
            "token" => $this->token,
            "user" => $id
        );
        $response = $this->get_query("https://slack.com/api/users.info", $data);
        $response = json_decode($response, true);

        if(!isset($response["ok"]) || !$response["ok"]) {
            $message = "[BLANK]";
            if(isset($response["error"])) {
                $message = $response["error"];
            }
            $this->error("user_info", $message);
            return null;
        }

        $member = $response["user"];
        return new SlackUser($member["id"], $member["tz_offset"], $member["is_bot"]);
    }

    function auth_test() {
        $response = $this->post_query_json("https://slack.com/api/auth.test", array());
        $response = json_decode($response, true);

        if(!isset($response["ok"]) || !$response["ok"]) {
            $message = "[BLANK]";
            if(isset($response["error"])) {
                $message = $response["error"];
            }
            $this->error("auth_test", $message);
            return null;
        }
        return new Auth($response["url"], $response["team"], $response["user"], $response["team_id"], $response["user_id"], $response["bot_id"]);
    }

    function use_response_url(string $response_url, string $message, $replace_original) {
        $data = array(
            "text"=>$message,
            "replace_original"=>$replace_original
        );
        $response = $this->post_query_json($response_url, $data);
        $response = json_decode($response, true);

        if(!isset($response["ok"]) || !$response["ok"]) {
            $message = "[BLANK]";
            if(isset($response["error"])) {
                $message = $response["error"];
            }
            $this->error("response_url", $message);
        }
    }

}
?>
