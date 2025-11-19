<?php
$servername = "localhost";
$username = "root";   // Wamp default
$password = "";       // Wamp default
$dbname = "billing";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


function uniqueID($prefix_name, $auto_increment_id)
{

    date_default_timezone_set('Asia/Calcutta');
    $timestamp = date('Y-m-d H:i:s');
    $encryptId = $prefix_name . "" . $timestamp . "" . $auto_increment_id;

    $hashid = md5($encryptId);

    return $hashid;
}
?>