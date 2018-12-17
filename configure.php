<?php
session_start();
if(!isset($_SESSION["loggedin"]) || !$_SESSION["loggedin"]) {
    header('Location: login.php');
}
?>
<html>
<head>
</head>
<body>
<h1>Timebot configuration page<h1>
<h2>Responses</h2>
<form action="<?php echo(htmlentities($_SERVER['PHP_SELF'])); ?>" method="post">
<table>
<tr>
    <th>Time</th>
    <th>Responses</th>
</tr>
<?php

function formatResponses($time,$responses) {
    $out = '
    <table>
    <tr>
        <th>Text</th>
        <th>Frequency</th>
    </tr>
    ';
    foreach($responses as $response) {
        $out .= '
        <tr>
            <td><textarea name="data['.$time.'][text]" rows="2" cols="10">'.$response["text"].'</textarea></td>
            <td><input type="number" name=data['.$time.'][freq]" value="'.$response["freq"].'"></td>
        </tr>
        ';
    }
    $out .= '</table>';
    return $out;
}

$data = getAllData($db);
foreach($data as $time=>$responses) {
    echo('
        <tr>
            <td>'.$time.'</td>
            <td>'.formatResponses($time,$responses).'</td>
        </tr>
    ');
}
?>
</table>
<input type="hidden" name="csrf" value="<?php echo(getCSRFToken()); ?>">
</form>

</body>
</html>