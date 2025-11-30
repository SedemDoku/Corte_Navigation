<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "corte_db";

$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
  
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}


$conn->set_charset("utf8mb4");
?>