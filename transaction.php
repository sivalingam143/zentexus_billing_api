<?php
include 'db/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
header('Content-Type: application/json; charset=utf-8');
$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');




if (isset($obj->search_text)) {
    $search_text = $obj->search_text;

    $output["head"]["code"] = 200;
    $output["head"]["msg"]  = "Success";
    $output["body"]["parties"] = [];
    $output["body"]["sales"]   = [];

    // Search in parties table
    $sql = "SELECT * FROM parties WHERE delete_at = 0 AND name LIKE '%$search_text%' ORDER BY id DESC";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["parties"][] = $row;
        }
    }

    // Search in sales table
    $sql = "SELECT * FROM `sales` WHERE `delete_at` = 0 AND `name` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["sales"][] = $row;
        }
    }

    // Update message if both tables returned no records
    if (empty($output["body"]["parties"]) && empty($output["body"]["sales"])) {
        $output["head"]["msg"] = "No records found in parties or sales";
    }
}
echo json_encode($output, JSON_NUMERIC_CHECK);
?>