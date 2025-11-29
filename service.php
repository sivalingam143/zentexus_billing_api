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
// 1. Search / List Products
// ==================================================================
if (isset($obj['search_text'])) {
    $search = $conn->real_escape_string($obj['search_text']);

    $sql = "SELECT * FROM service
            WHERE delete_at = 0
              AND service_name LIKE '%$search%'
            ORDER BY id DESC";

    $result = $conn->query($sql);

    $output["head"]["code"] = 200;
    $output["head"]["msg"]  = "Success";
    $output["body"]["services"] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["services"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "No services found";
    }
}

// ==================================================================
// 2. Create New Product
// ==================================================================
else if (
    isset($obj['type']) &&
    isset($obj['service_name']) &&
    !isset($obj['edit_service_id'])
) {
    $type           = $conn->real_escape_string($obj['type']);
    $service_name   = $conn->real_escape_string($obj['service_name']);
    $service_hsn       = (int)($obj['service_hsn'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id    = $conn->real_escape_string($obj['category_id'] ?? '');
    $category_name  = $conn->real_escape_string($obj['category_name'] ?? 'select category');
    $service_code = $conn->real_escape_string($obj['service_code'] ?? '');

    $add_image      = $conn->real_escape_string($obj['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? '0');
    // $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? '0');
    // $stock          = $conn->real_escape_string($obj['stock'] ?? '0');
    $tax = $conn->real_escape_string($obj['tax_rate'] ?? 'None');

    // Check duplicate product in same category
    $check = $conn->query("
        SELECT id FROM service 
        WHERE service_name = '$service_name' 
          AND category_id = '$category_id'
          AND delete_at = 0
    ");

    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "service name already exists in this category.";
    } else {
        $insert = "INSERT INTO service (
                        type, service_name, service_hsn , unit_id, unit_value,
                        category_id, category_name, service_code,add_image,
                        sale_price,tax,
                        create_at, delete_at
                   ) VALUES (
                        '$type', '$service_name', $service_hsn ,
                        '$unit_id', '$unit_value',
                        '$category_id', '$category_name',
                        '$service_code', 
                        '$add_image',
                        '$sale_price',  '$tax',
                        '$timestamp', 0
                   )";
if ($conn->query($insert)) {
    $new_id = $conn->insert_id;

    // Generate service_id (same logic as category.php)
    $service_id = uniqueID('service', $new_id);

    $conn->query("UPDATE service SET service_id = '$service_id' WHERE id = '$new_id'");

    // Fetch the newly inserted row
    $res = $conn->query("SELECT * FROM service WHERE id = $new_id LIMIT 1");
    $service_row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

    $output["head"]["code"] = 200;
    $output["head"]["msg"]  = "service created successfully";
    $output["body"]["service"] = $service_row;
    $output["body"]["service_id"] = $service_id;
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"]  = "Failed to create service: " . $conn->error;
}

    }
}

// ==================================================================
// 3. Edit Existing Product
// ==================================================================
else if (isset($obj['edit_service_id'])) {
    $edit_id        = $conn->real_escape_string($obj['edit_service_id']);
    $type           = $conn->real_escape_string($obj['type'] ?? '');
    $service_name   = $conn->real_escape_string($obj['service_name'] ?? '');
    $service_hsn       = (int)($obj['service_hsn'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id    = $conn->real_escape_string($obj['category_id'] ?? '');
    $category_name  = $conn->real_escape_string($obj['category_name'] ?? '');
     $service_code  = $conn->real_escape_string($obj['service_code'] ?? '');
    $add_image      = $conn->real_escape_string($obj['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? '0');
    
    $tax         = $conn->real_escape_string($obj['tax'] ?? '0');

    // prevent duplicate name except current record
    $check = $conn->query("
        SELECT id FROM service
        WHERE service_name = '$service_name'
          AND category_id = '$category_id'
          AND service_id != '$edit_id'

          AND delete_at = 0
    ");

    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Another service with this name already exists.";
    } else {
        $update = "UPDATE service SET
                        type = '$type',
                       service_name = '$service_name',
                        service_hsn  = $service_hsn ,
                        unit_id = '$unit_id',
                        unit_value = '$unit_value',
                        category_id = '$category_id',
                        category_name = '$category_name',
                       service_code = '$service_code',
                        add_image = '$add_image',
                        sale_price = '$sale_price',
                       
                        tax = '$tax'
                   WHERE service_id = '$edit_id' 
                     AND delete_at = 0";

        if ($conn->query($update)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "service updated successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Update failed: " . $conn->error;
        }
    }
}

// ==================================================================
// 4. Delete Product (Soft Delete)
// ==================================================================
else if (isset($obj['delete_service_id'])) {
    $delete_id = $conn->real_escape_string($obj['delete_service_id']);

    if (!empty($delete_id)) {
        if ($conn->query("UPDATE service SET delete_at = 1 WHERE service_id = '$delete_id'")) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "servicedeleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Delete failed: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Invalid product ID";
    }
}

// ==================================================================
// Default: Invalid
// ==================================================================
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"]  = "Invalid or missing parameters";
}

echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
$conn->close();
?>
