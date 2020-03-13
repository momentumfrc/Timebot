<?php
    include_once('functions.php');
    
    session_start();
    if(!(isset($_SESSION["loggedIn"])  && $_SESSION["loggedIn"]) ) {
        header('Location: login.php');
        exit();
    }

    if($_SERVER["REQUEST_METHOD"] === "POST") {
        switch($_POST["form"]) {
            case "settings":
                $settings = load_settings();
                foreach($settings as $key => $value) {
                    if(array_key_exists($key, $_POST)) {
                        $settings[$key] = $_POST[$key];
                    }
                }
                save_settings($settings);
            break;
            case "console":
                if(isset($_POST["message"])) {
                    postJSON($_POST["message"]);
                }
            break;
            default:
            break;
        }
    }
?>
<html>
    <head>
        <style>
            * {
                font-family: helvetica, arial, sans-serif;
            }
            table, th, td {
                border: 1px solid black;
                border-collapse: collapse;
            }
            h1 {
                text-align: center;
                font-family: helvetica, arial, sans-serif;
            }
            body {
                margin: 0;
                background-image: url("grey.png");
                background-attachment: fixed;
            }
            #maindiv {
                width: 60%;
                min-width: 500;
                margin: auto;
                background-color: rgba(255, 255, 255, 0.67);
                padding: 15px 70px;
                min-height: 100%;
                box-shadow: 0px 0px 10px 1px #06ceff;
                overflow-x: auto;
            }
            table {
                width: 100%;
            }
            form {
                margin: 0;
            }
            td {
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div id="maindiv">
            <form action="<?php echo(htmlentities($_SERVER['PHP_SELF'])); ?>" method="POST">
                <table>
                    <?php
                        $settings = load_settings();
                        foreach($settings as $key => $value) {
                            echo("<tr><td><p>".$key."</p></td><td><input type=\"text\" name=\"".$key."\" value=\"".$value."\">");
                        }
                    ?>
                    <input type="hidden" name="form" value="settings">
                </table>
                <input type="submit" value="Submit">
            </form>
            <form action="<?php echo(htmlentities($_SERVER['PHP_SELF'])); ?>" method="POST">
                <textarea name="message" rows="10" cols="80"></textarea>
                <input type="hidden" name="form" value="console">
                <input type="submit" value="Submit">
            </form>
        </div>
    </body>
</html>

