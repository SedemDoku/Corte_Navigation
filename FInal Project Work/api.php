<?php
/* sedem.doku */

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require "BusRouteFinder.php";
require 'db_connect.php';

session_start();


$input = json_decode(file_get_contents("php://input"), true);

// Ensure all required coordinates are present
if (!isset($input['startLat'], $input['startLon'], $input['endLat'], $input['endLon'])) {
    echo json_encode(["error" => "All coordinates (startLat, startLon, endLat, endLon) are required."]);
    exit;
}

$BUS_STOP_FILE = "Data/BusStopData_with_relations.json";
$ROUTE_FILE = "Data/RouteData.json";


if (!file_exists($BUS_STOP_FILE) || !file_exists($ROUTE_FILE)) {
    echo json_encode(["error" => "Data files 'BusStopData_with_relations.json' or 'RouteData.json' are missing from the Data folder."]);
    exit;
}

$finder = new BusRouteFinder($BUS_STOP_FILE, $ROUTE_FILE);
$finder->buildData();


$result = $finder->findRoute(
    (float)$input['startLat'], (float)$input['startLon'],
    (float)$input['endLat'], (float)$input['endLon']
);

$output = ["status" => "success", "result" => $result];
$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Save API result tied to the user
$stmt = $conn->prepare("
    INSERT INTO route_results (user_id, json_data)
    VALUES (?, CAST(? AS JSON))
");
$stmt->bind_param("is", $_SESSION['user_id'], $json);
$stmt->execute();

$route_result_id = $stmt->insert_id;

echo json_encode([
    "status" => "success",
    "route_result_id" => $route_result_id,
    "result" => $result
]);

?>