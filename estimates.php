
<?php
include 'db/config.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "head" => ["code" => 405, "msg" => "Method Not Allowed - Only POST allowed"]
    ]);
    exit();
}

// Read JSON input
$json = file_get_contents('php://input');
$obj = json_decode($json);

if (!$obj) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Invalid JSON data"]
    ]);
    exit();
}

$output = array();
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// SEARCH BLOCK
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $sql = "SELECT *, 
            (`total` - `received_amount`) AS balance_due 
            FROM `estimates` 
            WHERE `delete_at` = 0 
            AND (`name` LIKE '%$search_text%' OR `estimate_no` LIKE '%$search_text%')
            ORDER BY `id` DESC";
    
    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["estimates"] = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Force correct number format
            $row['total'] = number_format((float)$row['total'], 2, '.', '');
            $row['received_amount'] = number_format((float)$row['received_amount'], 2, '.', '');
            $row['balance_due'] = number_format((float)$row['balance_due'], 2, '.', '');
            $row['converted_to_sale'] = (int)$row['converted_to_sale'];
            $row['sale_id'] = $row['sale_id'] ?? null;
            $row['converted_to_sale'] = (int)$row['converted_to_sale'];
            $row['sale_id'] = $row['sale_id'] ?? null;
             if ($row['converted_to_sale'] == 1) {
              $row['status'] = 'Converted';  // or 'Accepted'
            }
            // Add proper status
            if ($row['delete_at'] == 1) {
                $row['status'] = 'Cancelled';
            } elseif ($row['balance_due'] == 0) {
                $row['status'] = 'Paid';
            } elseif ($row['received_amount'] == 0 && $row['balance_due'] > 0) {
                $row['status'] = 'Unpaid';
            } elseif ($row['received_amount'] > 0 && $row['balance_due'] > 0) {
                $row['status'] = 'Partially Paid';
            } else {
                $row['status'] = 'Unpaid';
            }

            $output["body"]["estimates"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "No records found";
    }
    
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit();
}

// CREATE NEW ESTIMATE
else if (isset($obj->estimate_no) && !isset($obj->edit_estimates_id)) {
    $estimate_no = $conn->real_escape_string($obj->estimate_no);
    $parties_id = isset($obj->parties_id) ? $conn->real_escape_string($obj->parties_id) : '';
    $name = $conn->real_escape_string($obj->name);
    $phone = isset($obj->phone) ? $conn->real_escape_string($obj->phone) : '';
    $billing_address = isset($obj->billing_address) ? $conn->real_escape_string($obj->billing_address) : '';
    $shipping_address = isset($obj->shipping_address) ? $conn->real_escape_string($obj->shipping_address) : '';
    $estimate_date = isset($obj->estimate_date) ? $conn->real_escape_string($obj->estimate_date) : '';
    $state_of_supply = isset($obj->state_of_supply) ? $conn->real_escape_string($obj->state_of_supply) : '';
    $products = $conn->real_escape_string($obj->products ?? '[]');
    
    // IMPORTANT FIX: Handle both spellings (rount_off from frontend, round_off in DB)
    $round_off = 0;
    if (isset($obj->rount_off)) {
        $round_off = ($obj->rount_off == 1 || $obj->rount_off === true) ? 1 : 0;
    } elseif (isset($obj->round_off)) {
        $round_off = ($obj->round_off == 1 || $obj->round_off === true) ? 1 : 0;
    }
    
    $round_off_amount = isset($obj->round_off_amount) ? floatval($obj->round_off_amount) : 0;
    $total = isset($obj->total) ? floatval($obj->total) : 0;
    $received_amount = isset($obj->received_amount) ? floatval($obj->received_amount) : 0;
    $payment_type = isset($obj->payment_type) ? $conn->real_escape_string($obj->payment_type) : '';
    $description = isset($obj->description) ? $conn->real_escape_string($obj->description) : '';
    $add_image = isset($obj->add_image) ? $conn->real_escape_string($obj->add_image) : '';
    $documents = isset($obj->documents) ? $conn->real_escape_string($obj->documents) : '[]';

    $balance_due = $total - $received_amount;

    // AUTO CALCULATE STATUS
    if ($balance_due == 0) {
        $status = 'Paid';
    } elseif ($received_amount == 0 && $balance_due > 0) {
        $status = 'Unpaid';
    } elseif ($received_amount > 0 && $balance_due > 0) {
        $status = 'Partially Paid';
    } else {
        $status = 'Unpaid';
    }

    // Prevent duplicate estimate_no
    $check = $conn->query("SELECT id FROM estimates WHERE estimate_no = '$estimate_no' AND delete_at = 0");
    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Estimate number already exists!";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $createUnit = "INSERT INTO `estimates`(`estimate_id`, `parties_id`, `name`, `phone`, `billing_address`, `shipping_address`, `estimate_no`, `estimate_date`, `state_of_supply`, `products`, `round_off`, `round_off_amount`, `payment_type`,`description`,`add_image`,`documents`,`total`,`received_amount`,`status`,`create_at`, `delete_at`) 
                  VALUES (NULL, '$parties_id', '$name', '$phone', '$billing_address', '$shipping_address', '$estimate_no', '$estimate_date', '$state_of_supply', '$products', '$round_off', '$round_off_amount', '$payment_type','$description','$add_image','$documents','$total','$received_amount','$status', '$timestamp', '0')";
    
    if ($conn->query($createUnit)) {
        $id = $conn->insert_id;
        $enId = uniqueID('estimate', $id);
        $updateUserId = "UPDATE `estimates` SET estimate_id ='$enId' WHERE `id`='$id'";
        $conn->query($updateUserId);
        // NEW CODE: Update estimate if converted from estimate
            if (isset($obj->from_estimate_id) && !empty($obj->from_estimate_id)) {
                $estimate_id = $conn->real_escape_string($obj->from_estimate_id);
                
                $updateEstimate = "UPDATE `estimates` SET 
                    `converted_to_sale` = 1,
                    `sale_id` = '$enId'
                    WHERE `estimate_id` = '$estimate_id'";
                
                $conn->query($updateEstimate);
            }
        
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Successfully estimate Created";
        $output["body"]["estimate_no"] = $estimate_no;
        $output["body"]["sale_id"] = $enId;
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to create estimate: " . $conn->error;
    }
    
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit();
}

// EDIT ESTIMATE
else if (isset($obj->edit_estimates_id)) {
    $edit_id = $conn->real_escape_string($obj->edit_estimates_id);
    
    if (empty($edit_id)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Edit ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    
    $total = floatval($obj->total ?? 0);
    $received_amount = floatval($obj->received_amount ?? 0);
    $balance_due = $total - $received_amount;

    // RECALCULATE STATUS ON UPDATE
    if ($balance_due == 0) {
        $status = 'Paid';
    } elseif ($received_amount == 0 && $balance_due > 0) {
        $status = 'Unpaid';
    } elseif ($received_amount > 0 && $balance_due > 0) {
        $status = 'Partially Paid';
    } else {
        $status = 'Unpaid';
    }

    $parties_id = $conn->real_escape_string($obj->parties_id ?? '');
    $name = $conn->real_escape_string($obj->name ?? '');
    $phone = $conn->real_escape_string($obj->phone ?? '');
    $billing_address = $conn->real_escape_string($obj->billing_address ?? '');
    $shipping_address = $conn->real_escape_string($obj->shipping_address ?? '');
    $estimate_no = $conn->real_escape_string($obj->estimate_no ?? '');
    $estimate_date = $conn->real_escape_string($obj->estimate_date ?? '');
    $state_of_supply = $conn->real_escape_string($obj->state_of_supply ?? '');
    $products = $conn->real_escape_string($obj->products ?? '[]');
    
    // IMPORTANT FIX: Handle both spellings
    $round_off = 0;
    if (isset($obj->rount_off)) {
        $round_off = ($obj->rount_off == 1 || $obj->rount_off === true) ? 1 : 0;
    } elseif (isset($obj->round_off)) {
        $round_off = ($obj->round_off == 1 || $obj->round_off === true) ? 1 : 0;
    }
    
    $round_off_amount = floatval($obj->round_off_amount ?? 0);
    $payment_type = $conn->real_escape_string($obj->payment_type ?? '');
    $description = $conn->real_escape_string($obj->description ?? '');
    $add_image = $conn->real_escape_string($obj->add_image ?? '');
    $documents = $conn->real_escape_string($obj->documents ?? '');

    $updateUnit = "UPDATE `estimates` SET 
        `parties_id`='$parties_id',
        `name`='$name',
        `phone`='$phone',
        `billing_address`='$billing_address',
        `shipping_address`='$shipping_address',
        `estimate_no`='$estimate_no',
        `estimate_date`='$estimate_date',
        `state_of_supply`='$state_of_supply',
        `products`='$products',
        `round_off`='$round_off',
        `round_off_amount`='$round_off_amount',
        `payment_type`='$payment_type',
        `description`='$description',
        `add_image`='$add_image',
        `documents`='$documents',
        `total`='$total',
        `received_amount`='$received_amount',
        `status`='$status'
        WHERE `estimate_id`='$edit_id'";

    if ($conn->query($updateUnit)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Successfully estimate Details Updated";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "SQL Error: " . $conn->error;
    }
    
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit();
}

// DELETE ESTIMATE
else if (isset($obj->delete_estimates_id)) {
    $delete_estimates_id = $conn->real_escape_string($obj->delete_estimates_id);
    
    if (!empty($delete_estimates_id)) {
        $deleteUnit = "UPDATE `estimates` SET `delete_at`=1 WHERE `estimate_id`='$delete_estimates_id'";
        
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Estimate Deleted Successfully!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete estimate: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
    
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit();
}

// INVALID REQUEST
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit();
}
