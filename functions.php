<?php
include 'specificvars.php';

date_default_timezone_set("America/Los_Angeles");

/**
 * Write a message to a log. The log is in the same directory as the current php file and has the name $log with the extension .log
 * @param string $string The message to write
 * @param string $log The name of the log to write to
 */
function writeToLog($string, $log) {
	file_put_contents("./".$log.".log", date("d-m-Y_h:i:s")."-- ".$string."\r\n", FILE_APPEND);
}
/**
 * Preform a POST request on a specified url with the specified parameters
 * @param string $url The url to query
 * @param array $opts The url parameters
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
/**
 * Preform a POST request on a specified url with the json-encoded data
 * @param string $url The url to query
 * @param string $token The authorization token
 * @param string $encoded_data The json-encoded data
 * @return string The server's response
 */
function json_post_query_slack($url,$token,$encoded_data) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded_data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer '.$token,
        'Content-Length: '.strlen($encoded_data)
    ));
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}
/**
* Preform a GET request on a specified url with the specified parameters
* @param string $url The url to query
* @param array $opts The url parameters
* @return string The server's response
*/
function get_query_slack($url,$opts) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url.'?'.http_build_query($opts));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}
/**
 * Post a message to slack
 * @param string $channel The channel to post to
 * @param string $message The message text to send
 */
function postMessage($channel, $message) {
    global $bot_token;
    $result = json_decode(post_query_slack("https://slack.com/api/chat.postMessage",array("token"=>$bot_token,"channel"=>$channel,"text"=>$message)),true);
    if(!isset($result["ok"]) || !$result["ok"]) {
        writeToLog("Error posting message".json_encode($message),"slack");
        throw new Exception($result["error"]);
    }
}
/**
 * Post a json-encoded message to slack
 * @param string $message The message text to send
 */
function postJSON($message) {
    global $bot_token;
    $result = json_decode(json_post_query_slack("https://slack.com/api/chat.postMessage",$bot_token,$message),true);
    if(!isset($result["ok"]) || !$result["ok"]) {
        writeToLog("Error posting json message".json_encode($message),"slack");
        throw new Exception($result["error"]);
    }
}
function postEphemeral($channel, $user, $text) {
    global $bot_token;
    $options = array(
        "token"=>$bot_token,
        "channel"=>$channel,
        "user"=>$user,
        "text"=>$text,
        "as_user"=>false
    );
    $result = json_decode(post_query_slack("https://slack.com/api/chat.postEphemeral",$options),true);
    if(!isset($result["ok"]) || !$result["ok"]) {
        writeToLog("Error posting ephemeral message".json_encode($result),"slack");
        throw new Exception($result["error"]);
    }
}
/**
 * Prevent slack from thinking the server timed out
 */
function stopTimeout() {
    ignore_user_abort(true);
    ob_start();
    header('Connection: close');
    header('Content-Length: '.ob_get_length());
    http_response_code(200);
    ob_end_flush();
    flush();
}
/**
 * Make sure a recieved request is from slack
 * @return boolean If the request is verified to be from slack
 */
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
/**
 * Format a timestamp as a string that slack will interpret and display in the user's timezone
 * @param int The timestamp to format
 * @return string The formatted string
 */
function getDateString($timestamp) {
    return "<!date^".$timestamp."^{time}|".date('g:i A', $timestamp).">";
}
/**
 * Get a mysqli database object for the users database
 * @return mixed The mysqli object representing the database connection, or FALSE if there was an error connecting
 */
function createDBObject() {
    global $dbuser, $dbpass, $database;
    $DB = new mysqli("localhost", $dbuser, $dbpass, $database);
    if($DB->connect_error) {
        return FALSE;
    } else {
        return $DB;
    }
}
function checkUserInDB($DB, $user) {
    global $table;
    $stmt = $DB->prepare("INSERT INTO ".$table." (`id`) VALUES (?) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`)");
    if($stmt === FALSE) {
        throw new Exception($DB->error);
    }
    $stmt->bind_param("s",$user);
    $stmt->execute();
    $stmt->close();
}
/**
 * Get the information stored for a user
 * @param mysqli $DB The database connection
 * @param string $user The userid for the user whose information is to be returned
 * @return array An associative array containing the user id and timebucks balance of the user
 */
function getUserInfo($DB, $user) {
    global $table;
    $stmt = $DB->prepare("SELECT * FROM ".$table." WHERE `id`=?");
    if($stmt === FALSE) {
        throw new Exception($DB->error);
    }
    $stmt->bind_param("s",$user);
    $stmt->execute();
    $stmt->bind_result($id, $balance);
    $stmt->fetch();
    $stmt->close();
    return array("id"=>$id,"balance"=>$balance);
}
/**
 * Set the user's timebucks balance
 * @param mysqli $DB The database connection
 * @param string $user The userid for the user whose timebucks balance is to be updated
 * @param double $newbalance The new timebucks balance for the user
 */
function updateUserBalance($DB, $user, $newbalance) {
    global $table;
    $stmt = $DB->prepare("UPDATE ".$table." SET `balance`=? WHERE `id`=?");
    if($stmt === FALSE) {
        throw new Exception($DB->error);
    }
    $stmt->bind_param("ds",$newbalance,$user);
    $stmt->execute();
    $stmt->close();
}
/**
 * Get all the users in the table, sorted in decending order by their balances
 * @return array An array of associative arrays containing users' ids and balances
 */
function getScoreboard($DB) {
    global $table;
    $result = $DB->query("SELECT * FROM ".$table." ORDER BY `balance` DESC");
    $out = array();
    while($data = $result->fetch_assoc()) {
        $out[] = $data;
    }
    return $out;
}

?>