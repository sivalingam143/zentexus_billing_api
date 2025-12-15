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
$obj = json_decode($json);
$output = array();
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// SEARCH BLOCK - Replace this entire part
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $sql = "SELECT *, 
            (`total` - `received_amount`) AS balance_due 
            FROM `sales` 
            WHERE `delete_at` = 0 
            AND (`name` LIKE '%$search_text%' OR `invoice_no` LIKE '%$search_text%')
            ORDER BY `id` DESC";
    
    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["sales"] = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Force correct number format
            $row['total'] = number_format((float)$row['total'], 2, '.', '');
            $row['received_amount'] = number_format((float)$row['received_amount'], 2, '.', '');
            $row['balance_due'] = number_format((float)$row['balance_due'], 2, '.', '');

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

            $output["body"]["sales"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "No records found";
    }
}
// <<<<<<<<<<===================== This is to Create sale =====================>>>>>>>>>>
else if (isset($obj->invoice_no) && !isset($obj->edit_sales_id)) {
    // $invoice_no = $obj->invoice_no;
    $invoice_no = $obj->invoice_no;
    $parties_id = isset($obj->parties_id) ? $obj->parties_id : '';
    $name = $obj->name;
    $phone = isset($obj->phone) ? $obj->phone : '';
    $billing_address = isset($obj->billing_address) ? $obj->billing_address : '';
    $shipping_address = isset($obj->shipping_address) ? $obj->shipping_address : '';
    $invoice_date = isset($obj->invoice_date) ? $obj->invoice_date : '';
    $state_of_supply = isset($obj->state_of_supply) ? $obj->state_of_supply : '';
    $products = $obj->products ?? '[]';
    $rount_off = isset($obj->rount_off) ? $obj->rount_off : 0;
    $round_off_amount = isset($obj->round_off_amount) ? $obj->round_off_amount : 0;
    $total = isset($obj->total) ? $obj->total : 0;
    $received_amount = isset($obj->received_amount) ? floatval($obj->received_amount) : '';
    
    $payment_type = isset($obj->payment_type) ? $obj->payment_type : '';
    $description = isset($obj->description) ? $obj->description : '';
    $add_image = $conn->real_escape_string($obj->add_image ?? '');
    $documents = isset($obj->documents) ? $obj->documents : '[]';

    $total           = floatval($obj->total ?? 0);
    $received_amount = floatval($obj->received_amount ?? 0);
    $balance_due     = $total - $received_amount;

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

    
    $check = $conn->query("SELECT id FROM sales WHERE invoice_no = '$invoice_no' AND delete_at = 0");
    if ($check->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invoice number already exists!";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $unitCheck = $conn->query("SELECT `id` FROM `sales` WHERE `invoice_no`='$invoice_no' AND delete_at = 0");
    if ($unitCheck->num_rows == 0) {
        $createUnit = "INSERT INTO `sales`(`sale_id`, `parties_id`, `name`, `phone`, `billing_address`, `shipping_address`, `invoice_no`, `invoice_date`, `state_of_supply`, `products`, `rount_off`, `round_off_amount`, `payment_type`,`description`,`add_image`,`documents`,`total`,`received_amount`,`status`,`create_at`, `delete_at`) VALUES (NULL, '$parties_id', '$name', '$phone', '$billing_address', '$shipping_address', '$invoice_no', '$invoice_date', '$state_of_supply', '$products', '$rount_off', '$round_off_amount', '$payment_type','$description','$add_image','$documents','$total','$received_amount','$status', '$timestamp', '0')";
        if ($conn->query($createUnit)) {
            $id = $conn->insert_id;
            $enId = uniqueID('sale', $id);
            $updateUserId = "update `sales` SET sale_id ='$enId' WHERE `id`='$id'";
            $conn->query($updateUserId);
    // ================== NEW: Estimate  ==================
        if (isset($obj->from_estimate_id) && !empty($obj->from_estimate_id)) {
            $from_estimate_id = $conn->real_escape_string($obj->from_estimate_id);

            // Estimate-a update pannu
            $updateEstimate = "UPDATE `estimates` 
                               SET `converted_to_sale` = 1, 
                                   `sale_id` = '$enId'
                                   
                               WHERE `estimate_id` = '$from_estimate_id' 
                               AND `delete_at` = 0";

            // CRITICAL FIX: Add error checking for robust conversion
            if (!$conn->query($updateEstimate)) { 
                // Log error for silent failure, but do not exit as sale was successful.
                error_log("Estimate Conversion Error for ID: " . $from_estimate_id . " - " . $conn->error);
            }
        }
        // =====================================================================
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully sale Created";
            $output["body"]["invoice_no"] = $invoice_no;
            $output["body"]["sale_id"] = $enId;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "sale Invoice No Already Exist.";
    }
}

// <<<<<<<<<<===================== This is to Edit sale =====================>>>>>>>>>>
else if (isset($obj->edit_sales_id)) {
    $edit_id = $obj->edit_sales_id;
    if (empty($edit_id)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Edit ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $total           = floatval($obj->total ?? 0);
    $received_amount = floatval($obj->received_amount ?? 0);
    $balance_due     = $total - $received_amount;

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

    $parties_id         = $conn->real_escape_string($obj->parties_id ?? '');
    $name              = $conn->real_escape_string($obj->name ?? '');
    $phone            = $conn->real_escape_string($obj->phone ?? '');
    $billing_address   = $conn->real_escape_string($obj->billing_address ?? '');
    $shipping_address  = $conn->real_escape_string($obj->shipping_address ?? '');
    $invoice_no       = $conn->real_escape_string($obj->invoice_no ?? '');
    $invoice_date     = $obj->invoice_date ?? '';
    $state_of_supply   = $conn->real_escape_string($obj->state_of_supply ?? '');
    $products = $obj->products ?? '[]';
    $rount_off         = $obj->rount_off ?? 0;
    $round_off_amount  = $obj->round_off_amount ?? 0;
    $total            = $obj->total ?? 0;
    $received_amount    = isset($obj->received_amount) ? floatval($obj->received_amount) : 0;
    
    $payment_type      = $conn->real_escape_string($obj->payment_type ?? '');
    $description       = $conn->real_escape_string($obj->description ?? '');
    $add_image         = $conn->real_escape_string($obj->add_image ?? '');
    $documents         = $conn->real_escape_string($obj->documents ?? '');

    // FIX: Added missing quote and space before WHERE
    $updateUnit = "UPDATE `sales` SET 
        `parties_id`='$parties_id',
        `name`='$name',
        `phone`='$phone',
        `billing_address`='$billing_address',
        `shipping_address`='$shipping_address',
        `invoice_no`='$invoice_no',
        `invoice_date`='$invoice_date',
        `state_of_supply`='$state_of_supply',
        `products`='$products',
        `rount_off`='$rount_off',
        `round_off_amount`='$round_off_amount',
        `payment_type`='$payment_type',
        `description`='$description',
        `add_image`='$add_image',
        `documents`='$documents',
        `total`='$total',
        `received_amount`='$received_amount',
        `status`='$status'
        
        WHERE `sale_id`='$edit_id'";  // ← THIS WAS BROKEN BEFORE

    if ($conn->query($updateUnit)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Successfully sale Details Updated";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "SQL Error: " . $conn->error; // helpful for debugging
    }
}
// <<<<<<<<<<===================== This is to Delete the sale =====================>>>>>>>>>>
else if (isset($obj->delete_sales_id)) {
    $delete_sales_id = $obj->delete_sales_id;
    if (!empty($delete_sales_id)) {
        $deleteUnit = "UPDATE `sales` SET `delete_at`=1 WHERE `sale_id`='$delete_sales_id'";
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "sale Deleted Successfully.!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>