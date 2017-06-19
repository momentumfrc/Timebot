<?php
header('Content-Type: application/json');
require 'token.php';
if($_SERVER["REQUEST_METHOD"] == "POST") {
  if($_POST["token"] == $token) {
    $response = array("response_type" => "in_channel","text" => "It's ".date("h:i"));
    echo(json_encode($response));
  }
}
 ?>
