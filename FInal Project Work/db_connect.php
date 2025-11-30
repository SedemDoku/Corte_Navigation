<?php
// db_connect.php - Improved with basic error handling
$servername = "localhost"; // because PHP is running on the same server as MySQL
$username = "rose.mpawenayo";
$password = "4926202714"; // the one you set after the password reset
$dbname = "webtech_2025A_rose_mpawenayo";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Don't expose detailed errors in production
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Set charset to prevent SQL injection
$conn->set_charset("utf8mb4");
?>