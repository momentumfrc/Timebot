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
function getDateString() {
  return "<!date^".date('U')."^{time}|".date('g:i A').">";
}

date_default_timezone_set('America/Los_Angeles');
if($_SERVER["REQUEST_METHOD"] == "POST") {
  if($_POST["token"] == $token) {
    $currentDate = time();
    $responded = false;
    if(file_exists("./lastTime.txt")){
      $lastDate = file_get_contents("./lastTime.txt");
      if($currentDate - $lastDate > 2592000) {
        $response = array("response_type" => "in_channel","text" => "It's ".getDateString()."\n\n_It's been a long, long time. How have you been?.._");
        echo(json_encode($response));
        $responded = true;
      }
    }
    file_put_contents("./lastTime.txt", $currentDate);
    if($responded) {
      exit();
    }
    $rand = rand(0,4);
    $time = date("g:i a");
    $response = array("response_type"=> "in_channel", "text" => "It's ".getDateString());
    switch($time) {
        case "4:20 pm":
            if($rand == 0) {
                $response["text"] .= "\nayyyy";
            }
            break;
        case "10:00 pm":
            $response["text"] .= "\n_Goodnight Andrew_";
            break;
    }
    echo(json_encode($response));
  }
}
?>
