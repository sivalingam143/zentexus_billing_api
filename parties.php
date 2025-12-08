<?php

include 'db/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit();
header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = ["head" => ["code" => 400, "msg" => ""], "body" => ["parties" => []]];
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

if (isset($obj->search_text)) {
    $search_text = trim($obj->search_text);
    $like = "%$search_text%";

    $output["head"]["code"] = 200;
    $output["head"]["msg"]   = "Success";
    $output["body"] = ["parties" => [], "sales" => []];

    // Parties
    $sql = "SELECT * FROM `parties` WHERE `delete_at` = 0 AND `name` LIKE ? ORDER BY `id` DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $output["body"]["parties"][] = $row;
    }

    // Sales – FIXED: use `name` column directly
    $sql = "SELECT * FROM `sales` WHERE `delete_at` = 0 AND `name` LIKE ? ORDER BY `id` DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $output["body"]["sales"][] = $row;
    }

    if (empty($output["body"]["parties"]) && empty($output["body"]["sales"])) {
        $output["head"]["msg"] = "No records found";
    }
}
// <<<<<<<<<<<<<<< CREATE NEW PARTY >>>>>>>>>>>>>>>
else if (isset($obj->name) && !isset($obj->edit_parties_id)) {
    $name             = $conn->real_escape_string($obj->name);
    $gstin            = $obj->gstin ?? '';
    $phone            = $obj->phone ?? '';
    $email            = $obj->email ?? '';
    $billing_address  = $obj->billing_address ?? '';
    $shipping_address = $obj->shipping_address ?? '';
    $amount           = floatval($obj->amount ?? 0);
    $creditlimit      = floatval($obj->creditlimit ?? 0);
    $state_of_supply  = $obj->state_of_supply ?? '';
    $gstin_type_id    = $obj->gstin_type_id ?? '';
    $gstin_type_name  = $obj->gstin_type_name ?? '';
    $additional_field = $obj->additional_field ?? '';

    // $transaction_type = ($obj->transactionType === 'pay') ? 'to pay' : 'to receive';
    $transaction_type = ($obj->transactionType === 'to pay') ? 'to pay' : 'to receive';

    $check = $conn->query("SELECT id FROM parties WHERE name='$name' AND delete_at = 0");
    if ($check->num_rows == 0) {
        $sql = "INSERT INTO parties (
                    name, gstin, phone, gstin_type_id, gstin_type_name, email,
                    state_of_supply, billing_address, shipping_address,
                    amount, transaction_type, additional_field, creditlimit, create_at, delete_at
                ) VALUES (
                    '$name','$gstin','$phone','$gstin_type_id','$gstin_type_name','$email',
                    '$state_of_supply','$billing_address','$shipping_address',
                    '$amount','$transaction_type','$additional_field','$creditlimit','$timestamp','0'
                )";

        if ($conn->query($sql)) {
            $id = $conn->insert_id;
            $enId = uniqueID('party', $id);
            $conn->query("UPDATE parties SET parties_id='$enId' WHERE id='$id'");

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Party Created Successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Error: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Party name already exists";
    }
}


// <<<<<<<<<<<<<<< UPDATE PARTY >>>>>>>>>>>>>>>
else if (isset($obj->edit_parties_id)) {
    $edit_id          = $conn->real_escape_string($obj->edit_parties_id);
    $name             = $conn->real_escape_string($obj->name);
    $gstin            = $obj->gstin ?? '';
    $phone            = $obj->phone ?? '';
    $email            = $obj->email ?? '';
    $billing_address  = $obj->billing_address ?? '';
    $shipping_address = $obj->shipping_address ?? '';
    $amount           = floatval($obj->amount ?? 0);
    $creditlimit      = floatval($obj->creditlimit ?? 0);
    $state_of_supply  = $obj->state_of_supply ?? '';
    $gstin_type_id    = $obj->gstin_type_id ?? '';
    $gstin_type_name  = $obj->gstin_type_name ?? '';
    $additional_field = $obj->additional_field ?? '';

    // FIXED: Works whether frontend sends "pay" or "to pay"
    // $transaction_type = (isset($obj->transactionType) && 
    //     ($obj->transactionType === 'pay' || $obj->transactionType === 'to pay')) 
    //     ? 'to pay' : 'to receive';
    $transaction_type = (isset($obj->transactionType) && 
    ($obj->transactionType === 'to pay')) 
    ? 'to pay' : 'to receive';

    $sql = "UPDATE parties SET
                name='$name',
                gstin='$gstin',
                phone='$phone',
                gstin_type_id='$gstin_type_id',
                gstin_type_name='$gstin_type_name',
                email='$email',
                state_of_supply='$state_of_supply',
                billing_address='$billing_address',
                shipping_address='$shipping_address',
                amount='$amount',
                transaction_type='$transaction_type',
                additional_field='$additional_field',
                creditlimit='$creditlimit'
            WHERE parties_id='$edit_id'";

    if ($conn->query($sql)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Party Updated Successfully";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Update failed: " . $conn->error;
    }
}

// <<<<<<<<<<<<<<< DELETE PARTY >>>>>>>>>>>>>>>
else if (isset($obj->delete_parties_id)) {
    $id = $conn->real_escape_string($obj->delete_parties_id);
$sql = "UPDATE parties SET delete_at=1 WHERE parties_id='$id'";

    $output["head"]["code"] = $conn->query($sql) ? 200 : 400;
    $output["head"]["msg"]   = $conn->query($sql) ? "Party Deleted" : "Delete failed";
}
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid request";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>