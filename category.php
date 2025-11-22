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
// 1. Search / List Categories
// ==================================================================
if (isset($obj['search_text'])) {
    $search_text = $conn->real_escape_string($obj['search_text']);
    $sql = "SELECT * FROM category 
            WHERE delete_at = 0 
              AND category_name LIKE '%$search_text%' 
            ORDER BY id DESC";

    $result = $conn->query($sql);

    $output["head"]["code"] = 200;
    $output["head"]["msg"]  = "Success";
    $output["body"]["categories"] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["categories"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "No categories found";
    }
}

// ==================================================================
// 2. Create New Category
// ==================================================================
else if (isset($obj['category_name']) && !isset($obj['edit_category_id'])) {
    $category_name = $conn->real_escape_string($obj['category_name']);

    // Check for duplicate category name
    $check = $conn->query("SELECT id FROM category WHERE category_name = '$category_name' AND delete_at = 0");
    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Category name already exists.";
    } else {
        $insert = "INSERT INTO category (category_name, create_at, delete_at) 
                   VALUES ('$category_name', '$timestamp', 0)";

        if ($conn->query($insert)) {
            $new_id = $conn->insert_id;

            // Generate unique category_id (same logic as unit.php)
            $enId = uniqueID('category', $new_id);

            // Update the row with generated category_id
            $conn->query("UPDATE category SET category_id = '$enId' WHERE id = '$new_id'");

            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Category created successfully";
            $output["body"]["category_id"] = $enId;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Failed to create category: " . $conn->error;
        }
    }
}

// ==================================================================
// 3. Edit Existing Category
// ==================================================================
else if (isset($obj['edit_category_id']) && isset($obj['category_name'])) {
    $edit_id       = $conn->real_escape_string($obj['edit_category_id']);
    $category_name = $conn->real_escape_string($obj['category_name']);

    // Prevent duplicate name except for current record
    $check = $conn->query("SELECT id FROM category 
                           WHERE category_name = '$category_name' 
                             AND delete_at = 0 
                             AND category_id != '$edit_id'");
    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Another category with this name already exists.";
    } else {
        $update = "UPDATE category SET 
                        category_name = '$category_name'
                   WHERE category_id = '$edit_id' AND delete_at = 0";

        if ($conn->query($update)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Category updated successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Update failed: " . $conn->error;
        }
    }
}

// ==================================================================
// 4. Delete (Soft Delete) Category
// ==================================================================
else if (isset($obj['delete_category_id'])) {
    $delete_id = $conn->real_escape_string($obj['delete_category_id']);

    if (!empty($delete_id)) {
        $delete = "UPDATE category SET delete_at = 1 WHERE category_id = '$delete_id'";
        if ($conn->query($delete)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"]  = "Category deleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"]  = "Delete failed: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"]  = "Invalid category ID";
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