<?php
require 'functions.php';
/**
* Exchanges a verification code for an access token
* @see https://api.slack.com/docs/oauth
* @param string $code A temporary authorization code
* @return array The access token and scopes
*/
function getTokenFromVerificationCode($code) {
	global $slack_clientid, $slack_clientsecret;
	$data = array(
	  "client_id"=>$slack_clientid,
	  "client_secret"=>$slack_clientsecret,
	  "code"=>$code
	);
	return json_decode(get_query_slack("https://slack.com/api/oauth.access",$data),true);
}

/**
 * Redirects the user to the oauth login page
 */
function redirectToLogin() {
	global $slack_clientid;
	$_SESSION["oauth_state"] = bin2hex(openssl_random_pseudo_bytes(8));
	header('Location: https://slack.com/oauth/authorize?scope=identity.basic&client_id='.$slack_clientid.'&state='.$_SESSION["oauth_state"]);
	exit();
}

session_start();

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] && isset($_SESSION["name"]) && isset($_SESSION["userid"])) {
	header('Location: configure.php');
	exit();
}

if(isset($_GET["code"]) && isset($_GET["state"]) && $_GET["state"] === $_SESSION["oauth_state"]) {
	unset($_SESSION["oauth_state"]);
	$tokendata = getTokenFromVerificationCode($_GET["code"]);
	if(!(isset($tokendata["ok"]) && $tokendata["ok"])) {
		if(isset($tokendata["error"])) {
			writeToLog("Token exchange failed with error: ".$tokendata["error"], "oauth");
			redirectToLogin();
		} else {
			writeToLog("Token exchange failed", "oauth");
			redirectToLogin();
		}
	}
	$userdata = json_decode(get_query_slack("https://slack.com/api/users.identity",array("token"=>$tokendata["access_token"])), true);
	if(!(isset($userdata["ok"]) && $userdata["ok"])) {
		if(isset($userdata["error"])) {
			writeToLog("Error retrieving user data: ".$userdata["error"], "oauth");
			redirectToLogin();
		} else {
			writeToLog("Error retrieving user data", "oauth");
			redirectToLogin();
		}
	}
	if(in_array($userdata["team"]["id"], $slack_teamids)) {
		$_SESSION["loggedin"] = true;
		$_SESSION["name"] = $userdata["user"]["name"];
		$_SESSION["userid"] = $userdata["user"]["id"];
		setCSRFToken();
		writeToLog("Successfully logged in as ".$_SESSION["name"], "oauth");
		header('Location: index.php');
		exit();
	} else {
		writeToLog("User from invalid workspace ".$userdata["team"]["id"]." attempted to log in", "oauth");
		redirectToLogin();
	}
} else {
	redirectToLogin();
}
?>