<?php
require 'db_connect.php';

// Start session securely
if (session_status() === PHP_SESSION_NONE) {

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');


    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}


if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $token_hash = hash("sha256", $token);
    $current_time = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE remember_token = ? AND remember_token_expires > ?");
    $stmt->bind_param("ss", $token_hash, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];


        $_SESSION['role'] = $row['role']; // 'user' or 'admin'
        $_SESSION['is_admin'] = ($row['role'] === 'admin');


        $_SESSION['login_time'] = time();
    } else {

        $secureFlag = isset($isSecure) ? $isSecure : false;
        setcookie("remember_token", "", time() - 3600, "/", "", $secureFlag, true);
    }
    $stmt->close();
}


function is_logged_in() {
    return isset($_SESSION['user_id']);
}


function is_admin() {
    if (isset($_SESSION['is_admin'])) {
        return !empty($_SESSION['is_admin']);
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

?>