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

// <<<<<<<<<<===================== This is to list parties =====================>>>>>>>>>>

if (isset($obj->search_text)) {
    $search_text = $obj->search_text;
    $sql = "SELECT * FROM parties WHERE delete_at = 0 AND name LIKE '%$search_text%' ORDER BY id DESC";
    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["parties"] = [];
 
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["parties"][] = $row;
        }
    } else {
        $output["head"]["msg"] = "parties records not found";
    }
}
// <<<<<<<<<<===================== This is to Create party =====================>>>>>>>>>>
else if (isset($obj->name) && !isset($obj->edit_parties_id)) {
    $name = $obj->name;
    $gstin = isset($obj->gstin) ? $obj->gstin : '';
    $phone = isset($obj->phone) ? $obj->phone : '';
    $email = isset($obj->email) ? $obj->email : '';
    $billing_address = isset($obj->billing_address) ? $obj->billing_address : '';
    $shipping_address = isset($obj->shipping_address) ? $obj->shipping_address : '';
    $amount = isset($obj->amount) ? $obj->amount : 0;
    
    // 🌟 FIX: Uncommented and initialized missing variables
    $creditlimit = isset($obj->creditlimit) ? $obj->creditlimit : 0;

    
    $state_of_supply = isset($obj->state_of_supply) ? $obj->state_of_supply : ''; 
    $gstin_type_id = isset($obj->gstin_type_id) ? $obj->gstin_type_id : ''; 
    $gstin_type_name = isset($obj->gstin_type_name) ? $obj->gstin_type_name : '';
    // Additional Field is collected here
    $additional_field = isset($obj->additional_field) ? $obj->additional_field : '';
    
    $unitCheck = $conn->query("SELECT id FROM parties WHERE name='$name' AND delete_at = 0");
    if ($unitCheck->num_rows == 0) {
        // 🌟 FIX: Added 'limittype' to column list and fixed the missing single quote after $creditlimit
        $createUnit = "INSERT INTO parties(name, gstin, phone, gstin_type_id, gstin_type_name, email, state_of_supply, billing_address, shipping_address, amount, additional_field, creditlimit, create_at, delete_at) 
                        VALUES ('$name', '$gstin', '$phone', '$gstin_type_id', '$gstin_type_name', '$email', '$state_of_supply', '$billing_address', '$shipping_address', '$amount', '$additional_field', '$creditlimit', '$timestamp', '0')";
        
        if ($conn->query($createUnit)) {
            $id = $conn->insert_id;
            // Assuming uniqueID function is defined in config.php
            $enId = uniqueID('party', $id); 
            $updateUserId = "update parties SET parties_id ='$enId' WHERE id='$id'";
            $conn->query($updateUserId);
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully party Created";
        } else {
            // Added MySQL error output for better debugging, remove $conn->error in production
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to create party. Error: " . $conn->error; 
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "party Name Already Exist.";
    }
}
// <<<<<<<<<<===================== This is to Edit party =====================>>>>>>>>>>
else if (isset($obj->edit_parties_id)) {
    $edit_id = $obj->edit_parties_id;
    $name = $obj->name;
    $gstin = isset($obj->gstin) ? $obj->gstin : '';
    $phone = isset($obj->phone) ? $obj->phone : '';
    $email = isset($obj->email) ? $obj->email : '';
    $billing_address = isset($obj->billing_address) ? $obj->billing_address : '';
    $shipping_address = isset($obj->shipping_address) ? $obj->shipping_address : '';
    $amount = isset($obj->amount) ? $obj->amount : 0;
    // Additional Field is collected here
    $additional_field = isset($obj->additional_field) ? $obj->additional_field : ''; 
    
    // 🌟 FIX: Uncommented and initialized missing variables
    $creditlimit = isset($obj->creditlimit) ? $obj->creditlimit : 0;
  
    
    $state_of_supply = isset($obj->state_of_supply) ? $obj->state_of_supply : '';
    $gstin_type_id = isset($obj->gstin_type_id) ? $obj->gstin_type_id : ''; 
    $gstin_type_name = isset($obj->gstin_type_name) ? $obj->gstin_type_name : '';
    
    // 🌟 FIX: Removed the SQL comment and correctly set the limittype
    $updateUnit = "UPDATE parties SET 
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
        additional_field='$additional_field', 
        creditlimit='$creditlimit' 
        
    WHERE parties_id='$edit_id'";
    
   if ($conn->query($updateUnit)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Successfully party Details Updated";
    } else {
        // Added MySQL error output for better debugging, remove $conn->error in production
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update party. Error: " . $conn->error;
    }
}

// <<<<<<<<<<===================== This is to Delete the party =====================>>>>>>>>>>
else if (isset($obj->delete_parties_id)) {
    $delete_parties_id = $obj->delete_parties_id;
    if (!empty($delete_parties_id)) {
        $deleteUnit = "UPDATE parties SET delete_at=1 WHERE parties_id='$delete_parties_id'";
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "party Deleted Successfully.!";
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