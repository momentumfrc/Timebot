<?php
include 'specificvars.php';

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
/**
* Preform a GET request on a specified url with the specified parameters
* @param string $url The url to query
* @param array $opts The url paramteters
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
 * Posts the specified text to the specified channel
 * @param string $channel The id of the channel to post to
 * @param string $message The text to post
 */
function postMessage($channel, $message) {
    global $bot_token;
    post_query_slack("https://slack.com/api/chat.postMessage",array("token"=>$bot_token,"channel"=>$channel,"text"=>$message));
}
/**
 * Writes the specified text to the specified log file
 * @param string $string The text to write
 * @param string $log The name of the log to write to
 */
function writeToLog($string, $log) {
	file_put_contents("./".$log.".log", date("d-m-Y_h:i:s")."-- ".$string."\r\n", FILE_APPEND);
}
/**
 * Prevents slack from thinking the bot timed out
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
 * Verifies if the request came from slack, using the $slack_signing_secret saved in specificvars.php
 * @return bool If the request is genuinely from slack
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
 * Formats the specified timestamp to display in slack3
 * @param int $timestamp The timestamp to format
 * @return string The formatted string
 */
function getDateString($timestamp) {
    return "<!date^".$timestamp."^{time}|".date('g:i A', $timestamp).">";
}

/**
 * Sets a random token used to preform CSRF verification on form requests
*/
function setCSRFToken() {
	if(session_status() == PHP_SESSION_NONE) {
		session_start();
	}
	$_SESSION["CSRF_token"] = clean(bin2hex(openssl_random_pseudo_bytes(16)));
}
/**
 * Gets the current CSRF token
 * @return String The current CSRF token
 */
 function getCSRFToken() {
	 if(session_status() == PHP_SESSION_NONE) {
		 session_start();
	 }
	 return $_SESSION["CSRF_token"];
 }
/**
 * Checks if the given token matches the CSRF_token for the current session
 * @param String $token The token to be checked against the saved token
 * @return bool If the provided token matches the saved CSRF token
 */
function checkCSRFToken($token) {
	if(session_status() == PHP_SESSION_NONE) {
		session_start();
	}
	return hash_equals($_SESSION["CSRF_token"], $token);
}

/**
 * Get a database object for storing and retrieving information
 * @return mixed A mysqli object representing a database connection, or FALSE if not logged in
 */
function getDBObject() {
    global $db, $dbuser, $dbpass;
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"]) {
        return new mysqli("localhost",$dbuser, $dbpass, $db);
    } else {
        return FALSE;
    }
}

/**
 * Get all time event data
 * @return array An associative array matching a time to its responses
 */
function getAllData($db) {
    global $table;
    $stmt = $db->prepare("SELECT (`time`,`responses`) FROM ".$table);
    if($stmt === FALSE) {
        throw new Exception($db->error);
    }
    $stmt->execute();
    $stmt->bind_result($time, $responses);
    $results = array();
    while($stmt->fetch()) {
        $results[$time] = json_decode($responses, true);
    }
    $stmt->close();
    return $results;
}

?>