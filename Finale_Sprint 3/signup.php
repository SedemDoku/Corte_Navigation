<?php
// signup.php - Registers a new user
header('Content-Type: application/json');
require 'db_connect.php'; //

$input = json_decode(file_get_contents("php://input"), true);
$user = trim($input['username'] ?? '');
$pass = trim($input['password'] ?? '');

if (!$user || !$pass) {
    echo json_encode(["status" => "error", "message" => "Username and password required"]);
    exit;
}

// Check if user exists
$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $user);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username already taken"]);
    exit;
}

// Insert new user (Password Hashed)
$hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $user, $hashed_pass);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Account created!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>