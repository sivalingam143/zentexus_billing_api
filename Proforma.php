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

if ($obj === null) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Invalid JSON"]], JSON_NUMERIC_CHECK);
    exit;
}

$output = ["head" => ["code" => 400, "msg" => "Parameter mismatch"]];
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');


// SEARCH BLOCK
if (isset($obj->search_text)) {
    $search_text = '%' . $conn->real_escape_string($obj->search_text) . '%';
    $sql = "SELECT *, (`total` - `received_amount`) AS balance_due 
            FROM proforma 
            WHERE delete_at = 0 
            AND (name LIKE ? OR reference_no LIKE ?)
            ORDER BY proforma_id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $search_text, $search_text);
    $stmt->execute();
    $result = $stmt->get_result();

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["proforma"] = [];

    while ($row = $result->fetch_assoc()) {
        $row['total'] = number_format((float)$row['total'], 2, '.', '');
        $row['received_amount'] = number_format((float)$row['received_amount'], 2, '.', '');
        $row['balance_due'] = number_format((float)$row['balance_due'], 2, '.', '');

        $balance = (float)$row['balance_due'];
        $received = (float)$row['received_amount'];

        if ($row['delete_at'] == 1) {
            $row['status'] = 'Cancelled';
        } elseif ($balance == 0) {
            $row['status'] = 'Paid';
        } elseif ($received == 0) {
            $row['status'] = 'Unpaid';
        } else {
            $row['status'] = 'Partially Paid';
        }
        $output["body"]["proforma"][] = $row;
    }
    $stmt->close();
}

// CREATE PROFORMA
else if (!isset($obj->edit_proforma_id)) {
    $parties_id        = $obj->parties_id ?? null;
    $name              = $obj->name ?? '';
    $phone             = $obj->phone ?? '';
    $billing_address   = $obj->billing_address ?? '';
    $shipping_address  = $obj->shipping_address ?? '';
    $invoice_date      = $obj->invoice_date ?? date('Y-m-d');
    $state_of_supply   = $obj->state_of_supply ?? '';
    $payment_type      = $obj->payment_type ?? '';
    $description       = $obj->description ?? '';
    $add_image         = $obj->add_image ?? '';
    $documents         = $obj->documents ?? '[]';
    $products          = $obj->products ?? '[]';
    $rount_off         = (int)($obj->rount_off ?? 0);
    $round_off_amount  = (float)($obj->round_off_amount ?? 0);
    $total             = (float)($obj->total ?? 0);
    $received_amount   = (float)($obj->received_amount ?? 0);

    $balance_due = $total - $received_amount;
    $status = ($balance_due == 0) ? 'Paid' : (($received_amount == 0) ? 'Unpaid' : 'Partially Paid');
   $reference_no = $obj->reference_no ?? generateReferenceNo($conn);

    $sql = "INSERT INTO proforma (
        parties_id, name, phone, billing_address, shipping_address,
        reference_no, invoice_date, state_of_supply, payment_type, description,
        add_image, documents, products, rount_off, round_off_amount,
        total, received_amount, status, created_at, delete_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssssssssidddss",
        $parties_id, $name, $phone, $billing_address, $shipping_address,
        $reference_no, $invoice_date, $state_of_supply, $payment_type, $description,
        $add_image, $documents, $products, $rount_off, $round_off_amount,
        $total, $received_amount, $status, $timestamp
    );

    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Proforma created successfully";
        $output["body"]["reference_no"] = $reference_no;
        $output["body"]["proforma_id"] = $insert_id; 
    } else {
        $output["head"]["msg"] = "Failed to create: " . $stmt->error;
    }
    $stmt->close();
}

// UPDATE PROFORMA
else if (isset($obj->edit_proforma_id)) {
    $edit_id           = $obj->edit_proforma_id;
    $parties_id        = $obj->parties_id ?? null;
    $name              = $obj->name ?? '';
    $phone             = $obj->phone ?? '';
    $billing_address   = $obj->billing_address ?? '';
    $shipping_address  = $obj->shipping_address ?? '';
    $reference_no      = $obj->reference_no ?? ''; 
    $invoice_date      = $obj->invoice_date ?? date('Y-m-d');
    $state_of_supply   = $obj->state_of_supply ?? '';
    $payment_type      = $obj->payment_type ?? '';
    $description       = $obj->description ?? '';
    $add_image         = $obj->add_image ?? '';
    $documents         = $obj->documents ?? '[]';
    $products          = $obj->products ?? '[]';
    $rount_off         = (int)($obj->rount_off ?? 0);
    $round_off_amount  = (float)($obj->round_off_amount ?? 0);
    $total             = (float)($obj->total ?? 0);
    $received_amount   = (float)($obj->received_amount ?? 0);

    $balance_due = $total - $received_amount;
    $status = ($balance_due == 0) ? 'Paid' : (($received_amount == 0) ? 'Unpaid' : 'Partially Paid');

    $sql = "UPDATE proforma SET 
        parties_id=?, name=?, phone=?, billing_address=?, shipping_address=?,
        reference_no=?, invoice_date=?, state_of_supply=?, payment_type=?, description=?,
        add_image=?, documents=?, products=?, rount_off=?, round_off_amount=?,
        total=?, received_amount=?, status=?, updated_at=?
        WHERE proforma_id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssssssssidddssi",
        $parties_id, $name, $phone, $billing_address, $shipping_address,
        $reference_no, $invoice_date, $state_of_supply, $payment_type, $description,
        $add_image, $documents, $products, $rount_off, $round_off_amount,
        $total, $received_amount, $status, $timestamp, $edit_id
    );

    if ($stmt->execute()) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Proforma updated successfully";
    } else {
        $output["head"]["msg"] = "Update failed: " . $stmt->error;
    }
    $stmt->close();
}

// DELETE PROFORMA
else if (isset($obj->delete_proforma_id)) {
    $delete_id = $obj->delete_proforma_id;
    $sql = "UPDATE proforma SET delete_at = 1 WHERE proforma_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Proforma deleted successfully";
    } else {
        $output["head"]["msg"] = "Delete failed: " . $stmt->error;
    }
    $stmt->close();
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>