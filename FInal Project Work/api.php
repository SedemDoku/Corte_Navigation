<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require "BusRouteFinder.php";

$input = json_decode(file_get_contents("php://input"), true);

// Ensure all required coordinates are present
if (!isset($input['startLat'], $input['startLon'], $input['endLat'], $input['endLon'])) {
    echo json_encode(["error" => "All coordinates (startLat, startLon, endLat, endLon) are required."]);
    exit;
}

$BUS_STOP_FILE = "Data/BusStopData_with_relations.json";
$ROUTE_FILE = "Data/RouteData.json";

// The path in your original routerfinder2.py uses backslashes, assuming the API is running 
// in a standard web environment where forward slashes are preferred for file paths.
// The data paths in the API file should be correct relative to the BusRouteFinder.php file.
// Assuming your data files are in a 'Data' subdirectory.
if (!file_exists($BUS_STOP_FILE) || !file_exists($ROUTE_FILE)) {
    echo json_encode(["error" => "Data files 'BusStopData_with_relations.json' or 'RouteData.json' are missing from the Data folder."]);
    exit;
}

$finder = new BusRouteFinder($BUS_STOP_FILE, $ROUTE_FILE);
$finder->buildData();

// Type casting coordinates to float for consistency
$result = $finder->findRoute(
    (float)$input['startLat'], (float)$input['startLon'],
    (float)$input['endLat'], (float)$input['endLon']
);

echo json_encode(["status" => "success", "result" => $result]);
?>