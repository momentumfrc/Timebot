<?php
require 'logger.php';

class SlackClient {
    private $token;

    function __construct(string $token, string $signing_secret) {
        $this->token = $token;
        $this->signing_secret = $signing_secret;
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
            error("verify_request_origin", "missing headers");
            return false;
        }
        if(abs(time() - $headers['X-Slack-Request-Timestamp']) > 60 * 5) {
            error("verify_request_origin", "request too old");
            return false;
        }
        $signature = 'v0:' . $headers['X-Slack-Request-Timestamp'] . ":" . file_get_contents('php://input');
        $signature_hashed = 'v0=' . hash_hmac('sha256', $signature, $slack_signing_secret);
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
}
?>