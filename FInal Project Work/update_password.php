<?php
// update_password.php
header('Content-Type: application/json');
require 'db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);
$token = $input['token'] ?? '';
$password = $input['password'] ?? '';

if (!$token || !$password) {
    echo json_encode(["status" => "error", "message" => "Missing data"]);
    exit;
}

// 1. Hash the token to match what is in the DB
$token_hash = hash("sha256", $token);

// 2. Check if token matches AND is not expired
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $userId = $row['id'];
    
    // 3. Hash the NEW password
    $new_password_hash = password_hash($password, PASSWORD_DEFAULT);

    // 4. Update password and Clear the token so it can't be used again
    $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
    $updateStmt->bind_param("si", $new_password_hash, $userId);
    
    if ($updateStmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid or expired link"]);
}
?>