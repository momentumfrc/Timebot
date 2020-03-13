<html>
<head>
    <title>Login</title>
    <?php
    session_start();
    require_once 'specificvars.php';
    if(isset($_SESSION["loggedIn"]) && $_SESSION["loggedIn"]) {
        header('Location: console.php');
    }
    $loginfail = false;
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["password"])) {
        if($_POST["password"] === $usepassword) {
            $_SESSION["loggedIn"] = true;
            file_put_contents("./users.log", date("d-m-Y_h:i:s")."-- IP ".$_SERVER['REMOTE_ADDR']." logged in\r\n", FILE_APPEND);
            header('Location: console.php');
        } else {
            $loginfail = true;
        }
    }


    ?>

    <style>
    * {
        font-family: helvetica, arial, sans-serif;
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
    form {
        text-align: center;
    }
    #loginfail {
        color: red;
        text-align: center;
    }
    </style>

</head>
<body>
<div id="maindiv">
    <h1>Enter password:</h1>
    <form method="POST" action="<?php echo(htmlentities($_SERVER["PHP_SELF"])); ?>">
        <input name="password" type="password" placeholder="Password">
        <input type="submit" value="Log In">
    </form>
    <?php
    if($loginfail) {
        echo('<p id="loginfail">Incorrect password</p>');
    }
    ?>
</div>
</body>
</html>
