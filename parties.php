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

// <<<<<<<<<<<<<<< LIST PARTIES - WITH OPENING BALANCE AS FIRST TRANSACTION >>>>>>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $obj->search_text ?? '';
    $search_text = $conn->real_escape_string($search_text);

    $sql = "SELECT *, amount, balance_type, create_at FROM parties 
            WHERE delete_at = 0 AND name LIKE '%$search_text%' 
            ORDER BY id DESC";

    $result = $conn->query($sql); // ← This line was missing in your broken version!

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["parties"] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $amount      = floatval($row['amount']);
            $balanceType = $row['balance_type']; // 'to pay' or 'to receive'

            // For list display
            if ($amount > 0) {
                $row['balance_type'] = $balanceType === 'to pay' ? 'To Pay' : 'To Receive';
            } else {
                $row['balance_type'] = 'Nil';
            }

            // For edit modal toggle
            $row['transactionType'] = ($balanceType === 'to pay') ? 'pay' : 'receive';

            $row['display_amount'] = $amount;

            // === ADD OPENING BALANCE AS FIRST TRANSACTION ===
            $opening = null;
            if ($amount > 0) {
                $label = $balanceType === 'to pay' ? ' Payable Balance' : 'Receivable Balance';
                $color = $balanceType === 'to pay' ? 'red' : 'green';

                $opening = [
                    "type"          => "Opening Balance",
                    "number"        => "-",
                    "date"          => date('d-m-Y', strtotime($row['create_at'] ?? $timestamp)),
                    "total"         => $amount,
                    "balance"       => $amount,
                    "balance_label" => $label,
                    "color"         => $color
                ];
            }

            // Initialize transactions array with opening balance
            $row['transactions'] = $opening ? [$opening] : [];

            $output["body"]["parties"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "No parties found";
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

    $balance_type = ($obj->transactionType === 'pay') ? 'to pay' : 'to receive';

    $check = $conn->query("SELECT id FROM parties WHERE name='$name' AND delete_at = 0");
    if ($check->num_rows == 0) {
        $sql = "INSERT INTO parties (
                    name, gstin, phone, gstin_type_id, gstin_type_name, email,
                    state_of_supply, billing_address, shipping_address,
                    amount, balance_type, additional_field, creditlimit, create_at, delete_at
                ) VALUES (
                    '$name','$gstin','$phone','$gstin_type_id','$gstin_type_name','$email',
                    '$state_of_supply','$billing_address','$shipping_address',
                    '$amount','$balance_type','$additional_field','$creditlimit','$timestamp','0'
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

    $balance_type = ($obj->transactionType === 'pay') ? 'to pay' : 'to receive';

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
                balance_type='$balance_type',
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