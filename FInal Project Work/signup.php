<?php
/*gacuti.kethia*/
header('Content-Type: application/json');
require 'db_connect.php';

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

$username = trim($input['username'] ?? '');
$email    = trim($input['email'] ?? '');
$pass     = trim($input['password'] ?? '');

/* Basic validation */
if (!$username || !$email || !$pass) {
    echo json_encode([
        "status" => "error",
        "message" => "Username, email, and password are required."
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email format."]);
    exit;
}

if (strlen($pass) < 8) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must be at least 8 characters long."
    ]);
    exit;
}

/* 2. Check if email already exists*/
$checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkEmail->bind_param("s", $email);
$checkEmail->execute();
$resultEmail = $checkEmail->get_result();

if ($resultEmail->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "An account with this email already exists."]);
    $checkEmail->close();
    exit;
}
$checkEmail->close();

/* 3. Check if username already exists */
$checkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
$checkUser->bind_param("s", $username);
$checkUser->execute();
$resultUser = $checkUser->get_result();

if ($resultUser->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username already taken."]);
    $checkUser->close();
    exit;
}
$checkUser->close();

/* 4. Insert a user(the password is hashed)*/
$hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $hashed_pass);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Account created successfully!"]);
} else {
    error_log("Database Error: " . $stmt->error);
    echo json_encode(["status" => "error", "message" => "Database error: Could not create account."]);
}

$stmt->close();
$conn->close();
?>
