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
$obj = json_decode($json, true);
$output = array();
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// ==================================================================
// 1. Search / List Units
// ==================================================================
if (isset($obj['search_text'])) {
    $search_text = $conn->real_escape_string($obj['search_text']);
    $sql = "SELECT * FROM unit 
            WHERE delete_at = 0 
              AND unit_name LIKE '%$search_text%' 
            ORDER BY id DESC";

    $result = $conn->query($sql);

    $output["head"]["code"] = 200;
    $output["head"]["msg"]  = "Success";
    $output["body"]["units"] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["units"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "No units found";
    }
}

// ==================================================================
// 2. DELETE Unit (Soft Delete) - Comes first to avoid conflicts
// ==================================================================
else if (isset($obj['delete_unit_id'])) {
    $delete_id = $conn->real_escape_string($obj['delete_unit_id']);

    if (!empty($delete_id)) {
        $delete = "UPDATE unit SET delete_at = 1 WHERE unit_id = '$delete_id' AND delete_at = 0";
        if ($conn->query($delete) && $conn->affected_rows > 0) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Unit deleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Unit not found or already deleted";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Invalid unit ID";
    }
}

// ==================================================================
// 3. EDIT Existing Unit - Must come BEFORE create!
// ==================================================================
else if (isset($obj['edit_unit_id']) && isset($obj['unit_name'])) {
    $edit_id     = $conn->real_escape_string($obj['edit_unit_id']);
    $unit_name   = $conn->real_escape_string($obj['unit_name']);
    $short_name  = isset($obj['short_name']) ? $conn->real_escape_string($obj['short_name']) : '';

    // Check if unit exists
    $existCheck = $conn->query("SELECT id FROM unit WHERE unit_id = '$edit_id' AND delete_at = 0");
    if ($existCheck->num_rows == 0) {
        $output["head"]["code"] = 404;
        $output["head"]["msg"]  = "Unit not found";
    } 
    // Check duplicate name (excluding current record)
    else {
        $check = $conn->query("SELECT id FROM unit 
                               WHERE unit_name = '$unit_name' 
                                 AND delete_at = 0 
                                 AND unit_id != '$edit_id'");
        if ($check->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Another unit with this name already exists.";
        } else {
            $update = "UPDATE unit SET 
                            unit_name   = '$unit_name',
                            short_name  = '$short_name'
                           
                       WHERE unit_id = '$edit_id' AND delete_at = 0";

            if ($conn->query($update) && $conn->affected_rows > 0) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"]  = "Unit updated successfully";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"]  = "No changes made or update failed";
            }
        }
    }
}

// ==================================================================
// 4. CREATE New Unit
// ==================================================================
else if (isset($obj['unit_name']) && empty($obj['edit_unit_id'] ?? '')) {
    $unit_name  = $conn->real_escape_string($obj['unit_name']);
    $short_name = isset($obj['short_name']) ? $conn->real_escape_string($obj['short_name']) : '';

    if (empty(trim($unit_name))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Unit name is required";
    } else {
        // Check for duplicate unit name
        $check = $conn->query("SELECT id FROM unit WHERE unit_name = '$unit_name' AND delete_at = 0");
        if ($check->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Unit name already exists.";
        } else {
            $insert = "INSERT INTO unit (unit_name, short_name, create_at, delete_at) 
                       VALUES ('$unit_name', '$short_name', '$timestamp', 0)";

            if ($conn->query($insert)) {
                $new_id = $conn->insert_id;

                // Generate unique unit_id
                $enId = uniqueID('unit', $new_id);

                // Update the row with generated unit_id
                $conn->query("UPDATE unit SET unit_id = '$enId' WHERE id = '$new_id'");

                $output["head"]["code"] = 200;
                $output["head"]["msg"]  = "Unit created successfully";
                $output["body"]["unit_id"] = $enId;
            } else {
                $output["head"]["code"] = 500;
                $output["head"]["msg"]  = "Failed to create unit: " . $conn->error;
            }
        }
    }
}

// ==================================================================
// Default: Invalid Request
// ==================================================================
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"]  = "Invalid or missing parameters";
}

// Return JSON response
echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
$conn->close();
?>