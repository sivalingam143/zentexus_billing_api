<?php
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

// Database connection
$conn = new mysqli("localhost", "root", "", "billing");
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => $conn->connect_error]));
}

// Fetch all parties
$sql = "SELECT * FROM parties";
$result = $conn->query($sql);

$parties = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // optional: convert numeric fields properly
        $row['amount'] = (float)$row['amount'];
        $row['creditlimit'] = (float)$row['creditlimit'];
        $parties[] = $row;
    }
}

echo json_encode($parties);

$conn->close();
?>
