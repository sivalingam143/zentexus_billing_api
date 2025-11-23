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

//////listing

if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text ?? '');

    $sql = "SELECT p.*, p.amount AS opening_amount, p.balance_type, p.create_at,
                   COALESCE(SUM(s.total), 0) AS total_sales_amount
            FROM parties p
            LEFT JOIN sales s ON p.parties_id = s.parties_id AND s.delete_at = 0
            WHERE p.delete_at = 0 AND p.name LIKE '%$search_text%'
            GROUP BY p.id ORDER BY p.id DESC";

    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $opening = floatval($row['opening_amount']);
            $total_sales_db = floatval($row['total_sales_amount']);
            $final_balance = $opening + $total_sales_db;

            $bt = $row['balance_type'];
            $row['display_amount'] = $final_balance > 0 ? $final_balance : 0;
            $row['balance_type'] = $final_balance > 0 ? ($bt === 'to pay' ? 'To Pay' : 'To Receive') : 'Nil';
            $row['transactionType'] = ($bt === 'to pay') ? 'pay' : 'receive';

            $transactions = [];

            // Opening Balance
            if ($opening > 0) {
                $label = $bt === 'to pay' ? 'Opening Payable' : 'Opening Receivable';
                $color = $bt === 'to pay' ? 'red' : 'green';
                $transactions[] = [
                    "type"          => "Opening Balance",
                    "number"        => "-",
                    "date"          => date('d-m-Y', strtotime($row['create_at'])),
                    "total"         => $opening,
                    "balance"       => $opening,
                    "balance_label" => $label,
                    "color"         => $color
                ];
            }

            // SALES LOOP - FIXED 100%
            $sales_sql = "SELECT invoice_no, invoice_date, total, id FROM sales 
                          WHERE parties_id = ? AND delete_at = 0 
                          ORDER BY invoice_date ASC";

            $stmt = $conn->prepare($sales_sql);
            $stmt->bind_param("s", $row['parties_id']);
            $stmt->execute();
            $sales_result = $stmt->get_result();

            $balance = $opening;  // MUST BE INITIALIZED BEFORE LOOP

            while ($sale = $sales_result->fetch_assoc()) {
                $sale_amount = floatval($sale['total']);
                $balance += $sale_amount;

                $transactions[] = [
                    "type"          => "Sale",
                    "number"        => $sale['invoice_no'] ?: "INV-" . $sale['id'],
                    "date"          => date('d-m-Y', strtotime($sale['invoice_date'])),
                    "total"         => $sale_amount,     // SHOW RUNNING TOTAL HERE (960)
                    "balance"       => $sale_amount,         // SHOW ACTUAL SALE AMOUNT HERE (725)
                    "balance_label" => "Sale",
                    "color"         => "blue"
                ];
            }
            $stmt->close();

            $row['transactions'] = $transactions;
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