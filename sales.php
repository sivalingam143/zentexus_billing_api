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
    $unitCheck = $conn->query("SELECT `id` FROM `sales` WHERE `invoice_no`='$invoice_no' AND delete_at = 0");
    if ($unitCheck->num_rows == 0) {
        $createUnit = "INSERT INTO `sales`(`sale_id`, `parties_id`, `name`, `phone`, `billing_address`, `shipping_address`, `invoice_no`, `invoice_date`, `state_of_supply`, `products`, `rount_off`, `round_off_amount`, `total`, `create_at`, `delete_at`) VALUES (NULL, '$parties_id', '$name', '$phone', '$billing_address', '$shipping_address', '$invoice_no', '$invoice_date', '$state_of_supply', '$products', '$rount_off', '$round_off_amount', '$total', '$timestamp', '0')";
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
    $parties_id = isset($obj->parties_id) ? $obj->parties_id : '';
    $name = $obj->name;
    $phone = isset($obj->phone) ? $obj->phone : '';
    $billing_address = isset($obj->billing_address) ? $obj->billing_address : '';
    $shipping_address = isset($obj->shipping_address) ? $obj->shipping_address : '';
    $invoice_no = $obj->invoice_no;
    $invoice_date = isset($obj->invoice_date) ? $obj->invoice_date : '';
    $state_of_supply = isset($obj->state_of_supply) ? $obj->state_of_supply : '';
    $products = isset($obj->products) ? $obj->products : '';
    $rount_off = isset($obj->rount_off) ? $obj->rount_off : 0;
    $round_off_amount = isset($obj->round_off_amount) ? $obj->round_off_amount : 0;
    $total = isset($obj->total) ? $obj->total : 0;
    $updateUnit = "UPDATE `sales` SET `parties_id`='$parties_id', `name`='$name', `phone`='$phone', `billing_address`='$billing_address', `shipping_address`='$shipping_address', `invoice_no`='$invoice_no', `invoice_date`='$invoice_date', `state_of_supply`='$state_of_supply', `products`='$products', `rount_off`='$rount_off', `round_off_amount`='$round_off_amount', `total`='$total' WHERE `sale_id`='$edit_id'";
    if ($conn->query($updateUnit)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Successfully sale Details Updated";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to connect. Please try again.";
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

// <<<<<<<<<<===================== This is to list all sales =====================>>>>>>>>>>

if (isset($obj->list_sales)) { 
    // ⭐️ FIX 1: Query the 'sales' table for all active sales records
    $sql = "SELECT * FROM `sales` WHERE `delete_at` = 0 ORDER BY `sale_id` DESC";
    $result = $conn->query($sql);
    
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    // ⭐️ FIX 2: Use a clear key for the sales array
    $output["body"]["sales"] = []; 
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["sales"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "Sales records not found";
    }
}
echo json_encode($output, JSON_NUMERIC_CHECK);