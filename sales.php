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

if (isset($obj->search_text)) {
    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `sales` WHERE `delete_at` = 0 AND `name` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["sales"] = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["sales"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "sales records not found";
    }
}
// <<<<<<<<<<===================== This is to Create sale =====================>>>>>>>>>>
else if (isset($obj->invoice_no) && !isset($obj->edit_sales_id)) {
    $invoice_no = $obj->invoice_no;
    $parties_id = isset($obj->parties_id) ? $obj->parties_id : '';
    $name = $obj->name;
    $phone = isset($obj->phone) ? $obj->phone : '';
    $billing_address = isset($obj->billing_address) ? $obj->billing_address : '';
    $shipping_address = isset($obj->shipping_address) ? $obj->shipping_address : '';
    $invoice_date = isset($obj->invoice_date) ? $obj->invoice_date : '';
    $state_of_supply = isset($obj->state_of_supply) ? $obj->state_of_supply : '';
    $products = isset($obj->products) ? $obj->products : '';
    $rount_off = isset($obj->rount_off) ? $obj->rount_off : 0;
    $round_off_amount = isset($obj->round_off_amount) ? $obj->round_off_amount : 0;
    $total = isset($obj->total) ? $obj->total : 0;
    $payment_type = isset($obj->payment_type) ? $obj->payment_type : '';
    $description = isset($obj->description) ? $obj->description : '';
    $add_image = $conn->real_escape_string($obj->add_image ?? '');
    $documents = isset($obj->documents) ? $obj->documents : '[]';
    $unitCheck = $conn->query("SELECT `id` FROM `sales` WHERE `invoice_no`='$invoice_no' AND delete_at = 0");
    if ($unitCheck->num_rows == 0) {
        $createUnit = "INSERT INTO `sales`(`sale_id`, `parties_id`, `name`, `phone`, `billing_address`, `shipping_address`, `invoice_no`, `invoice_date`, `state_of_supply`, `products`, `rount_off`, `round_off_amount`, `payment_type`,`description`,`add_image`,`documents`,`total`, `create_at`, `delete_at`) VALUES (NULL, '$parties_id', '$name', '$phone', '$billing_address', '$shipping_address', '$invoice_no', '$invoice_date', '$state_of_supply', '$products', '$rount_off', '$round_off_amount', '$payment_type','$description','$add_image','$documents','$total', '$timestamp', '0')";
        if ($conn->query($createUnit)) {
            $id = $conn->insert_id;
            $enId = uniqueID('sale', $id);
            $updateUserId = "update `sales` SET sale_id ='$enId' WHERE `id`='$id'";
            $conn->query($updateUserId);
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully sale Created";
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

    $parties_id         = $conn->real_escape_string($obj->parties_id ?? '');
    $name              = $conn->real_escape_string($obj->name ?? '');
    $phone            = $conn->real_escape_string($obj->phone ?? '');
    $billing_address   = $conn->real_escape_string($obj->billing_address ?? '');
    $shipping_address  = $conn->real_escape_string($obj->shipping_address ?? '');
    $invoice_no       = $conn->real_escape_string($obj->invoice_no ?? '');
    $invoice_date     = $obj->invoice_date ?? '';
    $state_of_supply   = $conn->real_escape_string($obj->state_of_supply ?? '');
    $products          = $obj->products ?? '';
    $rount_off         = $obj->rount_off ?? 0;
    $round_off_amount  = $obj->round_off_amount ?? 0;
    $total            = $obj->total ?? 0;
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
        `total`='$total' 
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
