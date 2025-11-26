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
// 1. CREATE PRODUCT (Handles Unit ID and Category Name) - Checked First
// ==================================================================
// NOTE: Added 'category_id' back to the required fields check.
if (isset($obj['type'], $obj['item_name'], $obj['category_id']) && !isset($obj['edit_item_code'])) {
    $type = $conn->real_escape_string($obj['type']);
    $item_name = $conn->real_escape_string($obj['item_name']);
    $hsn_code = (int)($obj['hsn_code'] ?? 0);
    $unit_id = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id = $conn->real_escape_string($obj['category_id']);
    $category_name = $conn->real_escape_string($obj['category_name'] ?? '');
    $sale_price = $conn->real_escape_string($obj['sale_price'] ?? '0');
    $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? '0');
    $stock = $conn->real_escape_string($obj['stock'] ?? '0');

    $check = $conn->query("SELECT id FROM product WHERE item_name = '$item_name' AND category_id = '$category_id' AND delete_at = 0");
    if ($check->num_rows > 0) {
        $output["head"]["code"] = 409; 
        $output["head"]["msg"] = "Product already exists in this category";
    } else {
        $insert = "INSERT INTO product (
            type, item_name, hsn_code, unit_id, unit_value, 
            category_id, category_name, sale_price, purchase_price, stock, 
            create_at, delete_at
        ) VALUES (
            '$type', '$item_name', $hsn_code, '$unit_id', '$unit_value',
            '$category_id', '$category_name', '$sale_price', '$purchase_price', '$stock',
            '$timestamp', 0
        )";

        if ($conn->query($insert)) {
            $new_id = $conn->insert_id;
            // Assuming uniqueID() is a defined function to generate item_code
            $item_code = uniqueID('product', $new_id); 
            $conn->query("UPDATE product SET item_code = '$item_code' WHERE id = $new_id");

            // --- CRUCIAL FIX: FETCH THE COMPLETE RECORD FOR THE RESPONSE BODY ---
            $fetch_sql = "SELECT * FROM product WHERE id = $new_id";
            $fetch_result = $conn->query($fetch_sql);
            $new_product_data = [];
            
            if ($fetch_result && $fetch_result->num_rows > 0) {
                // Assign the full database row to the response body
                $new_product_data = $fetch_result->fetch_assoc();
            }
            // -------------------------------------------------------------------

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Product created successfully";
            $output["body"] = $new_product_data; // <-- Now contains ALL data
        } else {
            $output["head"]["code"] = 500;
            $output["head"]["msg"] = "DB Error: " . $conn->error;
        }
    }
}
// ==================================================================
// 2. EDIT 
// ==================================================================
else if (isset($obj['edit_item_code'])) {
    $edit_item_code = $conn->real_escape_string($obj['edit_item_code']);
    $type = $conn->real_escape_string($obj['type'] ?? '');
    $item_name = $conn->real_escape_string($obj['item_name'] ?? '');
    $hsn_code = (int)($obj['hsn_code'] ?? 0);
    $unit_id = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id = $conn->real_escape_string($obj['category_id'] ?? '');
    $category_name = $conn->real_escape_string($obj['category_name'] ?? '');
    $sale_price = $conn->real_escape_string($obj['sale_price'] ?? '0');
    $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? '0');
    $stock = $conn->real_escape_string($obj['stock'] ?? '0');

    // NOTE: Changed 'products' back to 'product' for consistency if needed, assuming 'product' is correct
    $check = $conn->query("SELECT id FROM product WHERE item_name = '$item_name' AND category_id = '$category_id' AND item_code != '$edit_item_code' AND delete_at = 0");
    if ($check->num_rows > 0) {
        $output["head"]["msg"] = "Another product with this name already exists in this category";
    } else {
        // NOTE: Changed 'products' back to 'product' for consistency if needed
        $update = "UPDATE product SET 
            type = '$type', item_name = '$item_name', hsn_code = $hsn_code,
            unit_id = '$unit_id', unit_value = '$unit_value',
            category_id = '$category_id', category_name = '$category_name',
            sale_price = '$sale_price', purchase_price = '$purchase_price', stock = '$stock'
            WHERE item_code = '$edit_item_code' AND delete_at = 0";

        if ($conn->query($update)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Product updated successfully";
        } else {
            $output["head"]["code"] = 500;
            $output["head"]["msg"] = "Update failed: " . $conn->error;
        }
    }
}
// ==================================================================
// 3. DELETE 
// ==================================================================
else if (isset($obj['delete_item_code'])) {
    $code = $conn->real_escape_string($obj['delete_item_code']);
    if ($conn->query("UPDATE product SET delete_at = 1 WHERE item_code = '$code'")) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Deleted successfully";
    } else {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Deletion failed: " . $conn->error;
    }
}
// ==================================================================
// 4. FETCH ALL PRODUCTS (Default/Search) - Checked Last
// ==================================================================
else {
    $search = isset($obj['search_text']) ? $conn->real_escape_string($obj['search_text']) : '';
    $where = $search ? "(p.item_name LIKE '%$search%' OR p.item_code LIKE '%$search%' OR c.category_name LIKE '%$search%')" : "1=1";

    $sql = "SELECT p.*, c.category_name 
            FROM product p 
            LEFT JOIN category c ON p.category_id = c.id 
            WHERE p.delete_at = 0 AND $where
            ORDER BY p.id DESC";

    $result = $conn->query($sql);
    
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["products"] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["products"][] = $row;
        }
    }
}

// ALWAYS RETURN JSON
echo json_encode($output, JSON_UNESCAPED_UNICODE);
$conn->close();
?>