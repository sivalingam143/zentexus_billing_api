<?php
// Assumes db/config.php provides $conn (MySQLi connection) and the uniqueID function
include 'db/config.php';

header('Access-Control-Allow-Origin: *');
// ... (headers)
// ... (setup variables)

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();
// ... (date/time setup)

// ===================== 1. DELETE Logic =====================
if (isset($obj->delete_categories_id)) {
    $delete_categories_id = $obj->delete_categories_id;
    if (!empty($delete_categories_id)) {
        $deleteUnit = "UPDATE category SET delete_at=1 WHERE category_id='$delete_categories_id'";
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Category Deleted Successfully!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete category: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide the category ID for deletion.";
    }
} 
// ===================== 2. LIST / SEARCH Logic (Runs if no other specific action is requested) =====================
// This is your main listing block. It should run if search_text is present OR if nothing is present (empty POST body).
// We'll run this block if NO specific action (like delete or create) was found.
// The easiest way to handle the empty body (list all) is to check if it's NOT a creation or deletion request.
else if (isset($obj->search_text) || !isset($obj->category_name)) { // Assume listing if no explicit action is given
    
    // Set search text. Use provided search text, otherwise default to empty string for "list all."
    $search_text = isset($obj->search_text) ? $conn->real_escape_string($obj->search_text) : "";

    // Corrected column name to 'category_name'
    $sql = "SELECT * FROM category WHERE delete_at = 0 AND category_name LIKE '%$search_text%' ORDER BY id DESC";
    $result = $conn->query($sql);

    if (!$result) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database Error: " . $conn->error;
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["categories"] = []; // Correct key: 'categories'
     
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $output["body"]["categories"][] = $row;
            }
        } else {
            $output["head"]["msg"] = "No categories found";
        }
    }
}
// ===================== 3. CATCH-ALL: No recognizable parameter =====================
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch or Operation not recognized.";
}

// ⚠️ FINAL FIX: Ensure ALL execution paths lead to echo json_encode($output);
echo json_encode($output);
?>