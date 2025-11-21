<?php

header('Content-Type: application/json');
require 'db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);
$user = trim($input['username'] ?? '');
$pass = trim($input['password'] ?? '');

if (!$user || !$pass) {
    echo json_encode(["status" => "error", "message" => "Missing credentials"]);
    exit;
}

$stmt = $conn->prepare("SELECT id, password FROM Corte_users WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    if (password_verify($pass, $row['password'])) {
        echo json_encode([
            "status" => "success", 
            "user_id" => $row['id'],
            "username" => $user
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid password"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
}
?>