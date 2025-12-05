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
  $status_code = 0;
$status_name = 'active';


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
                        sale_price, purchase_price, stock,status_code, status_name,
                        create_at, delete_at
                   ) VALUES (
                        '$type', '$product_name', $hsn_code,
                        '$unit_id', '$unit_value',
                        '$category_id', '$category_name',
                        '$product_code', '$add_image',
                        '$sale_price', '$purchase_price', '$stock', '$status_code', '$status_name',
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
else if (isset($obj['edit_product_id'])) {
    // We assume 'edit_product_id' contains the unique string product_id (e.g., PROD-0001)
    $edit_id = $conn->real_escape_string($obj['edit_product_id']);
    
    // --- 1. Fetch Existing Data ---
    $current_res = $conn->query("SELECT * FROM product WHERE product_id = '$edit_id' AND delete_at = 0 LIMIT 1");

    if (!$current_res || $current_res->num_rows === 0) {
        $output["head"]["code"] = 404;
        $output["head"]["msg"]  = "Product not found or already deleted.";
        echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }

    $current = $current_res->fetch_assoc();
    $primary_id = $current['id']; // Store the actual integer primary key (id) for final update query

    // --- 2. Determine New Values (New Data overwrites Old Data) ---
    // Use data from $obj if set, otherwise use current data
    $type           = $conn->real_escape_string($obj['type'] ?? $current['type']);
    $product_name   = $conn->real_escape_string($obj['product_name'] ?? $current['product_name']);
    $hsn_code       = (int)($obj['hsn_code'] ?? $current['hsn_code'] ?? 0);
    $unit_id        = $conn->real_escape_string($obj['unit_id'] ?? $current['unit_id']);
    $unit_value     = $conn->real_escape_string($obj['unit_value'] ?? $current['unit_value']);
    $product_code   = $conn->real_escape_string($obj['product_code'] ?? $current['product_code']);
    $add_image      = $conn->real_escape_string($obj['add_image'] ?? $current['add_image']);
    $sale_price     = $conn->real_escape_string($obj['sale_price'] ?? $current['sale_price']);
    $purchase_price = $conn->real_escape_string($obj['purchase_price'] ?? $current['purchase_price']);
    
    // Status update logic
    $status_code = isset($obj['status_code']) ? (int)$obj['status_code'] : (int)($current['status_code'] ?? 0);
    $status_name = ($status_code == 1) ? 'inactive' : 'active';
    
    // Handle Stock - Ensure it's treated as a string for DB storage
   $stock          = $obj['stock'] ?? '{}';
    // --- 3. Category Update Logic ---
    $category_id    = $conn->real_escape_string($obj['category_id'] ?? $current['category_id']);
    $category_name  = $current['category_name']; // Default to current name

    // If a new category_id is provided, fetch the corresponding category_name from the 'category' table
    if (isset($obj['category_id'])) {
        $res = $conn->query("SELECT category_name FROM category WHERE category_id = '$category_id' AND delete_at = 0 LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $category_name = $res->fetch_assoc()['category_name'];
        }
    }
    // If category_name is explicitly passed, use it (might be used when category_id is unchanged)
    else if (isset($obj['category_name'])) {
         $category_name = $conn->real_escape_string($obj['category_name']);
    }

    // --- 4. Duplicate Check ---
    $check = $conn->query("
        SELECT id FROM product
        WHERE product_name = '$product_name'
          AND category_id = '$category_id'
          AND id != '$primary_id' 
          AND delete_at = 0
    ");

    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Another product with this name already exists in this category.";
    } else {
        // --- 5. Perform Update ---
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
                        stock = '$stock',
                        status_code = '$status_code',
                        status_name = '$status_name'
                   WHERE id = '$primary_id'
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
// 3B. BULK STATUS UPDATE (ACTIVE / INACTIVE)
// ==================================================================
else if (isset($obj['bulk_status_update']) && isset($obj['product_ids'])) {
    $ids = $obj['product_ids'];
    
    // status_code is ENUM('0','1') → treat as string
    $status_code = ($obj['status_code'] === '1' || $obj['status_code'] == 1) ? '1' : '0';
    $status_name = ($status_code === '1') ? 'inactive' : 'active';

    if (!is_array($ids) || empty($ids)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "No products selected.";
    } else {
        // Build IN clause safely
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $conn->prepare("UPDATE product SET status_code = ?, status_name = ? WHERE product_id IN ($placeholders) AND delete_at = 0");
        
        // First two params: string for ENUM, string for status_name
        $types = 'ss' . str_repeat('s', count($ids));
        $params = array_merge([$status_code, $status_name], $ids);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Updated {$stmt->affected_rows} items";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Update failed: " . $stmt->error;
        }
        $stmt->close();
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
