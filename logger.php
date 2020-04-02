<?php

class Logger {
    private static function writeToLog($string, $log) {
        if (!file_exists("./logs/")) {
            mkdir("./logs/",0777,true);
        }
        file_put_contents("./logs/".$log.".log", date("d-m-Y_h:i:s")."-- ".$string."\r\n", FILE_APPEND);
    }

    public static function log_api($string) {
        self::writeToLog($string, "api");
    }

    public static function log_balance($string) {
        self::writeToLog($string, "balance");
    }
}

?>
