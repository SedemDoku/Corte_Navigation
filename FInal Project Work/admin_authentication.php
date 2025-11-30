<?php
require 'authentication.php'; 


if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied.";
    exit();
}
?>