<?php
// --- CORS HEADERS START ---

// 1. Allow the specific origin where your frontend is running (localhost:3000)
header("Access-Control-Allow-Origin: http://localhost:3000"); 

// 2. Allow the necessary methods (POST for adding a party)
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// 3. Allow the Content-Type header needed for sending JSON data
header("Access-Control-Allow-Headers: Content-Type");

// 4. Handle pre-flight OPTIONS requests (sent automatically by the browser/axios)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}
// --- CORS HEADERS END ---

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No input received']);
    exit;
}

$name = $input['name'] ?? '';
$gstin = $input['gstin'] ?? '';
$phone = $input['phone'] ?? '';
$email = $input['email'] ?? '';
$billingAddress = $input['billingAddress'] ?? '';
$shippingAddress = $input['shippingAddress'] ?? '';
$amount = $input['amount'] ?? 0;
$creditLimit = $input['creditLimit'] ?? 0;
$limitType = $input['limitType'] ?? 'no';
// $date = $input['date'] ?? date('Y-m-d');

// Database connection
$conn = new mysqli("localhost", "root", "", "billing");
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => $conn->connect_error]));
}

// Correct SQL
$sql = "INSERT INTO parties 
(name, gstin, phone, email, billingaddress, shippingaddress, amount, creditlimit, limittype)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

// Correct bind_param order: strings and doubles
$stmt->bind_param(
    "ssssssdds",
    $name,
    $gstin,
    $phone,
    $email,
    $billingAddress,
    $shippingAddress,
    $amount,
    $creditLimit,
    $limitType
);

if ($stmt->execute()) {
    // Return the inserted ID so frontend can use it
    echo json_encode(['success' => true, 'message' => 'Party added successfully', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
