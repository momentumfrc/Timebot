<?php

require 'token.php';

function querySlack($url,$postdata) {
  $fields = json_encode($postdata);
  writeToLog('Will post: '.$fields.' to '.$url,'curl');
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json'
  ));
  $result = curl_exec($ch);
  curl_close($ch);
  return json_decode($result, true);
}
function writeToLog($string, $log) {
	file_put_contents($log.".log", date("d-m-Y_h:i:s")."-- ".$string."\r\n", FILE_APPEND);
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
  if(isset($post_url)) {
    //$data = str_replace("\n",'\n', $_POST["msg_data"]);
    $data = $_POST["msg_data"];
    querySlack($post_url, array("text"=>$data));
  }
}

 ?>
<html>
<head>
  <title>Timebot Console</title>
  <style>
    body {
      margin: 0px;
      background-color: #EFEFEF;
    }
    #center {
      width: 60%;
      margin: auto;
      min-height: 100%;
      padding: 5px;
      background-color: #FFFFFF;
    }
    #console {
      height: 60%;
      display: block;
    }
    #ref {
      display: block;
      height: 0px;
    }
  </style>
  <script>
    window.onload = function() {
      resizeconsole();
    }
    window.onresize = function() {
      resizeconsole();
    }
    function resizeconsole() {
      margin = 10;
      document.getElementById("console").style.width = (document.getElementById("ref").offsetWidth - (2 * margin)) + "px";
    }
  </script>
</head>
<body>
  <div id="center">
    <div id="ref"></div>
    <form action="<?php echo(htmlspecialchars($_SERVER["PHP_SELF"]));?>" method="post">
      <p>Enter your message:</p>
      <textarea name="msg_data" id="console"></textarea>
      <input type="submit">
    </form>
  </div>
</body>
</html>
