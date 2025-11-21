<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require "BusRouteFinder.php";

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['startLat']) || !isset($input['endLat'])) {
    echo json_encode(["error" => "Coordinates required"]);
    exit;
}

$BUS_STOP_FILE = "Data/BusStopData_with_relations.json";
$ROUTE_FILE = "Data/RouteData.json";

if (!file_exists($BUS_STOP_FILE)) {
    echo json_encode(["error" => "Data files missing"]);
    exit;
}

$finder = new BusRouteFinder($BUS_STOP_FILE, $ROUTE_FILE);
$finder->buildData();

$result = $finder->findRoute(
    $input['startLat'], $input['startLon'],
    $input['endLat'], $input['endLon']
);

echo json_encode(["status" => "success", "result" => $result]);
?>