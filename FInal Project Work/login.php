<?php
header('Content-Type: application/json');


require 'authentication.php';


require_once 'db_connect.php';


$input = json_decode(file_get_contents("php://input"), true);

$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');
$remember = $input['remember'] ?? false;


if (!$email || !$password) {
    echo json_encode([
        "status" => "error",
        "message" => "Email and password are required"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email format"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, username, email, password, role 
    FROM users 
    WHERE email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();


if (!$user = $result->fetch_assoc()) {
    echo json_encode([
        "status" => "error",
        "message" => "Account not found"
    ]);
    exit;
}

  
if (!password_verify($password, $user['password'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid password"
    ]);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['login_time'] = time();
$_SESSION['role'] = $user['role'];  // 'user' or 'admin'
$_SESSION['is_admin'] = ($user['role'] === 'admin');


$response = [
    "status"   => "success",
    "user_id"  => $user['id'],
    "username" => $user['username'],
    "email"    => $user['email'],
    "role"     => $user['role'],
    "is_admin" => ($user['role'] === 'admin')
];



$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if ($remember) {

    // Generate a strong random token
    $token = bin2hex(random_bytes(32)); 

    // Hash to store in database
    $token_hash = hash("sha256", $token);
    $expiry = date("Y-m-d H:i:s", time() + (30 * 24 * 60 * 60)); // 30 days

    // Save hashed token + expiry in DB
    $stmt2 = $conn->prepare("
        UPDATE users 
        SET remember_token = ?, remember_token_expires = ? 
        WHERE id = ?
    ");
    $stmt2->bind_param("ssi", $token_hash, $expiry, $user['id']);
    $stmt2->execute();
    $stmt2->close();

    // Set cookie with raw token
    setcookie(
        "remember_token",
        $token,
        time() + (30 * 24 * 60 * 60), // 30 days
        "/",
        "",
        $isSecure,  // secure flag based on HTTPS
        true   // HttpOnly
    );

    $response["remember"] = true;
}

// Cleanup
$stmt->close();
$conn->close();

// Output success response
echo json_encode($response);
?>
