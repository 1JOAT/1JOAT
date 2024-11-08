<?php
$servername = "sdb-68.hosting.stackcp.net";
$username = "infinity-35303439ebe1";
$password = "x5m1lnq03l";
$dbname = "infinity-35303439ebe1";
$conn = @new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error); 
}
$conn->set_charset("utf8");
?>