<?php
include 'functions.php';

if($_SERVER["REQUEST_METHOD"] == "POST" && verifySlack()) {
    writeToLog($_POST["payload"],"raw");
    $headers = getallheaders();
    if(isset($headers["X-Slack-Retry-Reason"])) {
        writeToLog("Slack retry because ".$headers["X-Slack-Retry-Reason"],"response");
    }
    $data = json_decode($_POST["payload"], true);

    if($data["type"] == "interactive_message") {
        writeToLog("Recieved message callback: ".json_encode($data), "response");

        $actions = json_decode(file_get_contents("actions.json"), true);

        $selected = $data["actions"][0]["value"];

        writeToLog("Recieved message callback of type ".$selected,"response");

        $response = "It's ".getDateString(floor($data["message_ts"]));
        $cost = 1;

        $display = "Normal response";

        foreach($actions as $action) {
            if($selected == $action["name"]) {
                $response = $action["value"];
                $response = str_replace("%timestring%",getDateString(floor($data["message_ts"])), $response);
                $cost = $action["cost"];
                $display = $action["display"];
                break;
            }
        }

        $replacement = array(
            "text"=>"You chose *".$display."*",
            "replace_original"=>true
        );

        json_post_query_slack($data["response_url"],$bot_token,json_encode($replacement));

        $DB = createDBObject();
        checkUserInDB($DB, $data["user"]["id"]);
        $userinfo = getUserInfo($DB,$data["user"]["id"]);

        $timebucks = $userinfo["balance"] - $cost;
        if($timebucks < 0) {
            $message = "I'm sorry <@".$userinfo["id"].">, but you have insufficient TimeBucks!\nThis action costs $".number_format($cost,2)."\nYour current balance is $".number_format($userinfo["balance"],2);
            postMessage($data["channel"]["id"],$message);
        } else {
            updateUserBalance($DB,$userinfo["id"],$timebucks);
            postMessage($data["channel"]["id"],$response);
        }
    }

    
} else {
    writeToLog("Recieved improper request","response");
    echo("You're not supposed to be here");
}


?>