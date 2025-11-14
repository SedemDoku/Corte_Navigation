<?php
// Step 1: read file

// Step 1: Connect to your database
$servername = "localhost"; // because PHP is running on the same server as MySQL
$username = "rose.mpawenayo";
$password = "4926202714"; // the one you set after the password reset
$dbname = "webtech_2025A_rose_mpawenayo";

$conn = new mysqli($servername, $username, $password, $dbname);



// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$jsonFile = '/home/rose.mpawenayo/public_html/TeamProject/data.json';

if (!file_exists($jsonFile)) {
    die("JSON file not found at $jsonFile");
}

$data = json_decode(file_get_contents('data.json'), true);

if (!$data) {
    die("Could not read JSON file!");
}


// Loop through elements
foreach ($data as $element) {

    $id   = $element["id"];
    $type = $element["type"];
    $lat  = $element["lat"];
    $lon  = $element["lon"];

    // Insert into nodes table
    $stmt = $conn->prepare("INSERT INTO corte_nodes (id, type, lat, lon) VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE type=VALUES(type), lat=VALUES(lat), lon=VALUES(lon)");
    $stmt->bind_param("isdd", $id, $type, $lat, $lon);
    $stmt->execute();

    // Insert tags
    if (isset($element["tags"])) {
        foreach ($element["tags"] as $key => $value) {

            $stmt2 = $conn->prepare("INSERT INTO corte_node_tags (node_id, tag_key, tag_value) VALUES (?, ?, ?)
                                     ON DUPLICATE KEY UPDATE tag_value=VALUES(tag_value)");
            $stmt2->bind_param("iss", $id, $key, $value);
            $stmt2->execute();
        }
    }
}

echo "Data inserted successfully!";
$conn->close();
?>

