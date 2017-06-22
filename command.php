<?php
header('Content-Type: application/json');
require 'token.php';

function querySlack($url,$postdata) {
  $fields = http_build_query($postdata);
  /*foreach($postdata as $key=>$value) {
    $fields .= $key.'='.$value.'&';
  }
  rtrim($fields, '&');*/
  writeToLog('Will post: '.$fields.' to '.$url,'curl');
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $result = curl_exec($ch);
  curl_close($ch);
  return json_decode($result, true);
}
function writeToLog($string, $log) {
	file_put_contents($log.".log", date("d-m-Y_h:i:s")."-- ".$string."\r\n", FILE_APPEND);
}
date_default_timezone_set('America/Los_Angeles');
if($_SERVER["REQUEST_METHOD"] == "POST") {
  if($_POST["token"] == $token) {
    if(rand(0,4) == 0) {
      $info = querySlack('https://slack.com/api/users.info', array('token'=>$oauth,'user'=>$_POST['user_id']));
      $response = array('response_type'=>'in_channel', 'text'=>'I\'m sorry '.$info['user']['profile']['first_name'].', I\'m afraid I can\'t do that');
      echo(json_encode($response));
    } else {
      $response = array("response_type" => "in_channel","text" => "It's ".date("h:i"));
      echo(json_encode($response));
    }
  }
}
?>
