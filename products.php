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
    $product_code   = $conn->real_escape_string($obj['product_code'] ?? '');
    $add_image      = $conn->real_escape_string($obj['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? '0');
    $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? '0');
    $stock          = $conn->real_escape_string($obj['stock'] ?? '0');

    // Duplicate check
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
                        category_id, category_name, product_code, add_image,
                        sale_price, purchase_price, stock,
                        create_at, delete_at
                   ) VALUES (
                        '$type', '$product_name', $hsn_code,
                        '$unit_id', '$unit_value',
                        '$category_id', '$category_name',
                        '$product_code', '$add_image',
                        '$sale_price', '$purchase_price', '$stock',
                        '$timestamp', 0
                   )";

        if ($conn->query($insert)) {

            $new_id = $conn->insert_id;

            // Generate product_id
            $product_id = uniqueID('product', $new_id);
            $conn->query("UPDATE product SET product_id = '$product_id' WHERE id = '$new_id'");

            // Fetch full inserted row
            $res = $conn->query("SELECT * FROM product WHERE id = $new_id LIMIT 1");
            $product_row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Product created successfully";
            $output["body"]["product"] = $product_row;    // Full product data
           
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Failed to create product: " . $conn->error;
        }
    }
}


// ==================================================================
// 3. Edit Existing Product
// ==================================================================
// ... (products.php existing code up to line 150)

// ==================================================================
// 3. Edit Existing Product
// ==================================================================
else if (isset($obj['edit_product_id'])) {
    // NOTE: The frontend (MoveCategoryModal.jsx) sends the primary key 'id' as 'edit_product_id'
    $edit_id        = $conn->real_escape_string($obj['edit_product_id']);
    
    // Retrieve fields sent by the frontend (MoveCategoryModal.jsx only sends edit_product_id and category_id)
    $category_id    = $conn->real_escape_string($obj['category_id'] ?? '');

    // The frontend doesn't send these, so we need to fetch the existing values for mandatory fields 
    // to avoid setting them to empty/zero, or we fetch the new category name.
    $type           = $conn->real_escape_string($obj['type'] ?? $conn->query("SELECT type FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['type'] ?? '');
    $product_name   = $conn->real_escape_string($obj['product_name'] ?? $conn->query("SELECT product_name FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['product_name'] ?? '');
    $hsn_code       = (int)($obj['hsn_code'] ?? $conn->query("SELECT hsn_code FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['hsn_code'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? $conn->query("SELECT unit_id FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['unit_id'] ?? '');
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? $conn->query("SELECT unit_value FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['unit_value'] ?? '');
    
    // Fetch the current category_id for duplicate check later if the frontend didn't send a new one
    $current_category_id = $conn->query("SELECT category_id FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['category_id'] ?? '';

    $category_name  = ''; // Initialize category_name

    // 1. If a new category_id is provided, fetch the corresponding category_name from the 'category' table
    if (!empty($category_id)) {
        $res = $conn->query("SELECT category_name FROM category WHERE category_id = '$category_id' AND delete_at = 0 LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $category_name = $res->fetch_assoc()['category_name'];
        }
    }
    // 2. If no new category_id is provided, fetch the existing category_name
    else {
         $category_name = $conn->real_escape_string($obj['category_name'] ?? $conn->query("SELECT category_name FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['category_name'] ?? '');
         $category_id = $current_category_id; // Use existing category ID
    }

    $product_code  = $conn->real_escape_string($obj['product_code'] ?? $conn->query("SELECT product_code FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['product_code'] ?? '');
    $add_image      = $conn->real_escape_string($obj['add_image'] ?? $conn->query("SELECT add_image FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['add_image'] ?? '');
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? $conn->query("SELECT sale_price FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['sale_price'] ?? '0');
    $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? $conn->query("SELECT purchase_price FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['purchase_price'] ?? '0');
    $stock          = $obj['stock'] ?? $conn->query("SELECT stock FROM product WHERE id = '$edit_id' LIMIT 1")->fetch_assoc()['stock'] ?? '{}';


    // prevent duplicate name except current record
    $check = $conn->query("
        SELECT id FROM product
        WHERE product_name = '$product_name'
          AND category_id = '$category_id'
          AND id != '$edit_id' /* <-- **FIXED: Must check against primary key 'id'** */
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
                        category_id = '$category_id', /* <-- New category ID */
                        category_name = '$category_name', /* <-- New category Name (fetched above) */
                       product_code = '$product_code',
                        add_image = '$add_image',
                        sale_price = '$sale_price',
                        purchase_price = '$purchase_price',
                        stock = '$stock'
                   WHERE id = '$edit_id' /* <-- **FIXED: Use primary key 'id'** */
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
// ... (products.php remaining code)

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
