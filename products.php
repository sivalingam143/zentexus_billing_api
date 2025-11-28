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

    $sql = "SELECT * FROM product
            WHERE delete_at = 0
              AND product_name LIKE '%$search%'
            ORDER BY id DESC";

    $result = $conn->query($sql);

    $output["head"]["code"] = 200;
    $output["head"]["msg"]  = "Success";
    $output["body"]["products"] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["products"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "No products found";
    }
}

// ==================================================================
// 2. Create New Product
// ==================================================================
else if (
    isset($obj['type']) &&
    isset($obj['product_name']) &&
    !isset($obj['edit_product_id'])
) {
    $type           = $conn->real_escape_string($obj['type']);
    $product_name   = $conn->real_escape_string($obj['product_name']);
    $hsn_code       = (int)($obj['hsn_code'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id    = $conn->real_escape_string($obj['category_id'] ?? '');
    $category_name  = $conn->real_escape_string($obj['category_name'] ?? '');
    $product_code = $conn->real_escape_string($obj['product_code'] ?? '');

    $add_image      = $conn->real_escape_string($obj['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? '0');
    $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? '0');
    $stock          = $conn->real_escape_string($obj['stock'] ?? '0');

    // Check duplicate product in same category
    $check = $conn->query("
        SELECT id FROM product 
        WHERE product_name = '$product_name' 
          AND category_id = '$category_id'
          AND delete_at = 0
    ");

    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Product name already exists in this category.";
    } else {
        $insert = "INSERT INTO product (
                        type, product_name, hsn_code, unit_id, unit_value,
                        category_id, category_name, product_code,add_image,
                        sale_price, purchase_price, stock,
                        create_at, delete_at
                   ) VALUES (
                        '$type', '$product_name', $hsn_code,
                        '$unit_id', '$unit_value',
                        '$category_id', '$category_name',
                        '$product_code', 
                        '$add_image',
                        '$sale_price', '$purchase_price', '$stock',
                        '$timestamp', 0
                   )";

        if ($conn->query($insert)) {
            $new_id = $conn->insert_id;

            // Generate product_id (same logic as category.php)
            $product_id = uniqueID('product', $new_id);

            $conn->query("UPDATE product SET product_id = '$product_id' WHERE id = '$new_id'");

            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Product created successfully";
            $output["body"]["product_id"] = $product_id;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Failed to create product: " . $conn->error;
        }
    }
}

// ==================================================================
// 3. Edit Existing Product
// ==================================================================
else if (isset($obj['edit_product_id'])) {
    $edit_id        = $conn->real_escape_string($obj['edit_product_id']);
    $type           = $conn->real_escape_string($obj['type'] ?? '');
    $product_name   = $conn->real_escape_string($obj['product_name'] ?? '');
    $hsn_code       = (int)($obj['hsn_code'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? '');
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? '');
    $category_id    = $conn->real_escape_string($obj['category_id'] ?? '');
    $category_name  = $conn->real_escape_string($obj['category_name'] ?? '');
     $product_code  = $conn->real_escape_string($obj['product_code'] ?? '');
    $add_image      = $conn->real_escape_string($obj['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? '0');
    $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? '0');
    $stock          = $conn->real_escape_string($obj['stock'] ?? '0');

    // prevent duplicate name except current record
    $check = $conn->query("
        SELECT id FROM product
        WHERE product_name = '$product_name'
          AND category_id = '$category_id'
          AND product_id != '$edit_id'
          AND delete_at = 0
    ");

    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Another product with this name already exists.";
    } else {
        $update = "UPDATE product SET
                        type = '$type',
                        product_name = '$product_name',
                        hsn_code = $hsn_code,
                        unit_id = '$unit_id',
                        unit_value = '$unit_value',
                        category_id = '$category_id',
                        category_name = '$category_name',
                       product_code = '$product_code',
                        add_image = '$add_image',
                        sale_price = '$sale_price',
                        purchase_price = '$purchase_price',
                        stock = '$stock'
                   WHERE product_id = '$edit_id' 
                     AND delete_at = 0";

        if ($conn->query($update)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Product updated successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Update failed: " . $conn->error;
        }
    }
}

// ==================================================================
// 4. Delete Product (Soft Delete)
// ==================================================================
else if (isset($obj['delete_product_id'])) {
    $delete_id = $conn->real_escape_string($obj['delete_product_id']);

    if (!empty($delete_id)) {
        if ($conn->query("UPDATE product SET delete_at = 1 WHERE product_id = '$delete_id'")) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Product deleted successfully";
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
