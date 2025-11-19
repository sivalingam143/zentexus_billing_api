<?php
// Assumes db/config.php provides $conn (MySQLi connection) and the uniqueID function
include 'db/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); 
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

///delete

if (isset($obj->delete_units_id)) {
    $delete_units_id = $obj->delete_units_id;
    if (!empty($delete_units_id)) {
        $deleteUnit = "UPDATE unit SET delete_at=1 WHERE unit_id='$delete_units_id'";
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "unit Deleted Successfully!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete unit: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide the unit ID for deletion.";
    }
    echo json_encode($output);
    exit;

} 

// <<<<<<<<<<===================== LIST / SEARCH Categories (POST) =====================>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $obj->search_text;
} else {
    $search_text = "";
}
$sql = "SELECT * FROM unit WHERE delete_at = 0 AND unit_name LIKE '%$search_text%' ORDER BY id DESC";
$result = $conn->query($sql);
if (!$result) {
    // Return a server error if the SQL failed (this is unlikely if 'category_name' is correct)
    $output["head"]["code"] = 500;
    $output["head"]["msg"] = "Database Error: " . $conn->error;
    echo json_encode($output);
    exit;
}
$output["head"]["code"] = 200;
$output["head"]["msg"] = "Success";

$output["body"]["units"] = [];
 
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $output["body"]["units"][] = $row;
    }
} else {
    $output["head"]["msg"] = "No units found";
}

echo json_encode($output);




?>