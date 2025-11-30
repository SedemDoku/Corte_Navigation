<?php
// send_reset.php
header('Content-Type: application/json');
require 'db_connect.php';

// Simple rate limiting
session_start();
$current_time = time();
if (isset($_SESSION['last_reset_request']) && 
    ($current_time - $_SESSION['last_reset_request']) < 60) { // 1 minute cooldown
    echo json_encode(["status" => "error", "message" => "Please wait before requesting another reset"]);
    exit;
}

$_SESSION['last_reset_request'] = $current_time;

$input = json_decode(file_get_contents("php://input"), true);
$email = $input['email'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email"]);
    exit;
}

// 1. Generate a random token
$token = bin2hex(random_bytes(16));
// 2. Hash it for storage (security best practice)
$token_hash = hash("sha256", $token);
// 3. Set expiry (e.g., 30 minutes from now)
$expiry = date("Y-m-d H:i:s", time() + 60 * 30);

// 4. Update User
$stmt = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE email = ?");
$stmt->bind_param("sss", $token_hash, $expiry, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Construct the link
    // CHANGE 'http://localhost/' to your actual website domain
    $resetLink = "http://localhost/finale/reset_password.html?token=" . $token;

    echo json_encode([
        "status" => "success", 
        "message" => "Reset link sent",
        "debug_link" => $resetLink 
    ]);
} else {
    // Don't reveal if email exists or not for security, just say sent
    echo json_encode(["status" => "success", "message" => "If that email exists, a link was sent."]);
}

$stmt->close();
$conn->close();
?>