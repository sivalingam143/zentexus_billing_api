<?php
// transaction.php

include 'db/config.php';
// Assuming uniqueID() function is defined in config.php or a similar included file
// If uniqueID() is not defined, you'll need to define it or remove the parts that use it.

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


/ Function to calculate the running balance (Crucial for accurate party ledger)
function calculate_running_balance($transactions) {
    // 1. Sort by date, then by 'created_at' for transactions on the same day
    usort($transactions, function($a, $b) {
        if ($a['date'] === $b['date']) {
            return $a['created_at'] <=> $b['created_at'];
        }
        return $a['date'] <=> $b['date'];
    });

    $running_balance = 0.0;
    foreach ($transactions as $key => $t) {
        $amount = (float) $t['total'];
        
        // 'to receive' (Sale or Receivable OB) increases the money you are owed (positive balance)
        if ($t['balance_type'] === 'to receive') {
            $running_balance += $amount;
        } 
        // 'to pay' (Payable OB) decreases the money you are owed (negative balance/liability)
        else if ($t['balance_type'] === 'to pay') {
            $running_balance -= $amount;
        }

        // Apply final running balance and formatting
        $transactions[$key]['balance'] = $running_balance;
        $transactions[$key]['color'] = $running_balance < 0 ? 'red' : ($running_balance > 0 ? 'green' : 'black');
        $transactions[$key]['balance_label'] = $running_balance < 0 ? 'You Pay' : ($running_balance > 0 ? 'You Receive' : 'Settled');
    }
    
    // Reverse the order for display (latest transaction on top in the UI)
    return array_reverse($transactions);
}


// <<<<<<<<<<===================== NEW: Fetch and Group Transactions =====================>>>>>>>>>>
// This block is called by parties.php via cURL (Req 1 & 3)
if (isset($obj->parties_id_fk) && $obj->action === 'fetch_party_transactions') {
    $party_unique_id = $conn->real_escape_string($obj->parties_id_fk);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $transactions = [];

    // --- 1. FETCH OPENING BALANCE TRANSACTION ---
    $ob_sql = "SELECT 
        'Opening Balance' AS type, 
        'OB' AS number, 
        DATE_FORMAT(`date`, '%Y-%m-%d') AS date, 
        `total`, 
        `balance_type`,
        `created_at`
        FROM `transactions` 
        WHERE `parties_id_fk` = '$party_unique_id' 
        AND `type` = 'Opening Balance'
        ORDER BY `date` ASC, `created_at` ASC";
        
    $ob_result = $conn->query($ob_sql);
    if ($ob_result && $ob_result->num_rows > 0) {
        while ($row = $ob_result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }

    // --- 2. FETCH SALES TRANSACTIONS (Sale records link to the party) ---
    $sales_sql = "SELECT 
        'Sale' AS type,
        `sale_id` AS reference_id,
        `invoice_no` AS number, 
        DATE_FORMAT(`invoice_date`, '%Y-%m-%d') AS date, 
        `total`,
        'to receive' AS balance_type, 
        `created_at` 
        FROM `sales` 
        WHERE `delete_at` = 0 
        AND `parties_id_fk` = '$party_unique_id' 
        ORDER BY `invoice_date` ASC, `created_at` ASC";

    $sales_result = $conn->query($sales_sql);
    if ($sales_result && $sales_result->num_rows > 0) {
        while ($row = $sales_result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
    
    // --- 3. CALCULATE RUNNING BALANCE and Format Output ---
    $output["body"]["transactions"] = calculate_running_balance($transactions);

    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit;
}

// <<<<<<<<<<===================== Fallback Listing (If needed, otherwise remove) =====================>>>>>>>>>>
// This block is for general listing of sales or parties, if your frontend uses transaction.php for that.
if (isset($obj->search_text) && isset($obj->entity_type)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $entity_type = strtolower($obj->entity_type); // Expect 'sales' or 'parties'

    $sql = "";
    $entity_name = "";

    if ($entity_type === 'sales') {
        $sql = "SELECT * FROM `sales` WHERE `delete_at` = 0 AND `name` LIKE '%$search_text%' ORDER BY `id` DESC";
        $entity_name = "sales";
    } else if ($entity_type === 'parties') {
        $sql = "SELECT * FROM `parties` WHERE `delete_at` = 0 AND `name` LIKE '%$search_text%' ORDER BY `id` DESC";
        $entity_name = "parties";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid entity_type. Must be 'sales' or 'parties'.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"][$entity_name] = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"][$entity_name][] = $row;
        }
    } else {
        $output["head"]["msg"] = $entity_name . " records not found";
    }
    
}
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid request";
}


echo json_encode($output, JSON_NUMERIC_CHECK);
?>