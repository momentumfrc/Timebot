<?php

class Timebot {

    function __construct(object $slack_client) {
        $this->slack = $slack_client;
    }

    function handle_request() {
        $this->validate_request();
    
        
    }
}

$tb = new Timebot();
$tb.handle_request();


?>
