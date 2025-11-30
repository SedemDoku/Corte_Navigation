<?php
require 'db_connect.php';
require 'authentication.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to save routes']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['start_lat', 'start_lng', 'end_lat', 'end_lng', 'route_data', 'route_name'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize inputs
$user_id = $_SESSION['user_id'];
$route_name = $conn->real_escape_string(trim($input['route_name']));
$start_lat = (float)$input['start_lat'];
$start_lng = (float)$input['start_lng'];
$end_lat = (float)$input['end_lat'];
$end_lng = (float)$input['end_lng'];
$route_data = $conn->real_escape_string(json_encode($input['route_data']));

// Insert into database
$sql = "INSERT INTO saved_routes 
        (user_id, route_name, start_lat, start_lng, end_lat, end_lng, route_data, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isdddds", $user_id, $route_name, $start_lat, $start_lng, $end_lat, $end_lng, $route_data);

if ($stmt->execute()) {
    $route_id = $conn->insert_id;
    echo json_encode([
        'status' => 'success', 
        'message' => 'Route saved successfully',
        'route_id' => $route_id
    ]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to save route',
        'error' => $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>