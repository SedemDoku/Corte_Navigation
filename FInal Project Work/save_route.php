<?php
/* rose.mpawenayo */
require 'db_connect.php';
require 'authentication.php';

// Only allow POST requests
session_start();
header("Content-Type: application/json");
require 'db_connect.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$route_result_id = intval($input["route_result_id"] ?? 0);

// Validate route_result_id
if ($route_result_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid route"]);
    exit;
}

// Insert into saved_routes
$stmt = $conn->prepare("
    INSERT INTO saved_routes (user_id, route_result_id)
    VALUES (?, ?)
");
$stmt->bind_param("ii", $_SESSION['user_id'], $route_result_id);
$stmt->execute();

echo json_encode([
    "status" => "success",
    "message" => "Route saved successfully!"
]);

?>