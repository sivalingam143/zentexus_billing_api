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
// 1. CREATE SERVICE
// ==================================================================
if (isset($obj['service_name'], $obj['category_id']) && !isset($obj['edit_service_code'])) {
    $service_name   = $conn->real_escape_string($obj['service_name']);
    $service_hsn    = (int)($obj['service_hsn'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id    = $conn->real_escape_string($obj['category_id']);
    $category_name  = $conn->real_escape_string($obj['category_name'] ?? '');
    $add_image      = $conn->real_escape_string($obj['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? '');
    $tax            = $conn->real_escape_string($obj['tax_rate'] ?? 'None'); // renamed to "tax" in DB

    // Prevent duplicate in same category
    $check = $conn->query("SELECT id FROM service WHERE service_name = '$service_name' AND category_id = '$category_id' AND delete_at = 0");
    if ($check->num_rows > 0) {
        $output["head"]["code"] = 409;
        $output["head"]["msg"]  = "Service already exists in this category";
    } else {
        $insert = "INSERT INTO service (
            service_name, service_hsn, unit_id, unit_value, category_id, category_name,
            add_image, sale_price, tax, create_at, delete_at
        ) VALUES (
            '$service_name', $service_hsn, '$unit_id', '$unit_value', '$category_id', '$category_name',
            '$add_image', '$sale_price', '$tax', '$timestamp', 0
        )";

        if ($conn->query($insert)) {
            $new_id = $conn->insert_id;
            $service_code = uniqueID('service', $new_id); // your existing function
            $conn->query("UPDATE service SET service_code = '$service_code' WHERE id = $new_id");

            // Return full created record
            $fetch = $conn->query("SELECT * FROM service WHERE id = $new_id");
            $new_service = $fetch->fetch_assoc();

            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Service created successfully";
            $output["body"] = $new_service;
        } else {
            $output["head"]["code"] = 500;
            $output["head"]["msg"]  = "DB Error: " . $conn->error;
        }
    }
}

// ==================================================================
// 2. UPDATE SERVICE
// ==================================================================
else if (isset($obj['edit_service_code'])) {
    $edit_code      = $conn->real_escape_string($obj['edit_service_code']);
    $service_name   = $conn->real_escape_string($obj['service_name']);
    $service_hsn    = (int)($obj['service_hsn'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id    = $conn->real_escape_string($obj['category_id']);
    $category_name  = $conn->real_escape_string($obj['category_name'] ?? '');
    $add_image      = $conn->real_escape_string($obj['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? '');
    $tax            = $conn->real_escape_string($obj['tax_rate'] ?? 'None');

    $check = $conn->query("SELECT id FROM service WHERE service_name = '$service_name' AND category_id = '$category_id' AND service_code != '$edit_code' AND delete_at = 0");
    if ($check->num_rows > 0) {
        $output["head"]["code"] = 409;
        $output["head"]["msg"]  = "Another service with this name already exists in this category";
    } else {
        $update = "UPDATE service SET
            service_name='$service_name', service_hsn=$service_hsn,
            unit_id='$unit_id', unit_value='$unit_value',
            category_id='$category_id', category_name='$category_name',
            add_image='$add_image', sale_price='$sale_price', tax='$tax'
            WHERE service_code='$edit_code' AND delete_at=0";

        if ($conn->query($update)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Service updated successfully";
        } else {
            $output["head"]["code"] = 500;
            $output["head"]["msg"]  = "Update failed: " . $conn->error;
        }
    }
}

// ==================================================================
// 3. DELETE SERVICE
// ==================================================================
else if (isset($obj['delete_service_code'])) {
    $code = $conn->real_escape_string($obj['delete_service_code']);
    if ($conn->query("UPDATE service SET delete_at = 1 WHERE service_code = '$code'")) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"]  = "Service deleted successfully";
    } else {
        $output["head"]["code"] = 500;
        $output["head"]["msg"]  = "Deletion failed";
    }
}

// ==================================================================
// 4. FETCH ALL SERVICES (with search)
// ==================================================================
else {
    $search = isset($obj['search_text']) ? $conn->real_escape_string($obj['search_text']) : '';
    $where = $search 
        ? "(s.service_name LIKE '%$search%' OR s.service_code LIKE '%$search%' OR c.category_name LIKE '%$search%')" 
        : "1=1";

    $sql = "SELECT s.*, c.category_name 
            FROM service s 
            LEFT JOIN category c ON s.category_id = c.id 
            WHERE s.delete_at = 0 AND $where
            ORDER BY s.id DESC";

    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"]  = "Success";
    $output["body"]["services"] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["services"][] = $row;
        }
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
$conn->close();
?>