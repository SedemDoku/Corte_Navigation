<?php
class BusRouteFinder {
    private string $busStopFile;
    private string $routeFile;
    private array $busStops = [];
    private array $stopIdToStop = [];
    private array $routeIdToRoute = [];
    private array $routeIdToStops = [];
    private array $stopIdToRoutes = [];
    private array $routeAdjGraph = [];

    public function __construct(string $busStopFile, string $routeFile) {
        $this->busStopFile = $busStopFile;
        $this->routeFile = $routeFile;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earthRadius = 6371;
        $lat1 = deg2rad($lat1); $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2); $lon2 = deg2rad($lon2);
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $a = sin($dlat/2)**2 + cos($lat1) * cos($lat2) * sin($dlon/2)**2;
        return $earthRadius * 2 * asin(sqrt($a));
    }

    public function buildData() {
        // Load stops
        $busData = json_decode(file_get_contents($this->busStopFile), true);
        foreach ($busData["elements"] as $el) {
            if (($el["type"] ?? "") === "node") {
                $this->busStops[] = $el;
                $this->stopIdToStop[$el["id"]] = $el;
                // PHP arrays use numeric keys, so we convert the relations to a standard array for set operations later
                $this->stopIdToRoutes[$el["id"]] = array_values($el["relations"] ?? []);
            }
        }
        // Load routes
        $routeData = json_decode(file_get_contents($this->routeFile), true);
        foreach ($routeData["elements"] as $route) {
            if (($route["type"] ?? "") === "relation") {
                $routeId = $route["id"];
                $this->routeIdToRoute[$routeId] = $route;
                $ordered = [];
                foreach ($route["members"] as $m) {
                    if (($m["type"] ?? "") === "node") $ordered[] = $m["ref"];
                }
                $this->routeIdToStops[$routeId] = $ordered;
            }
        }
        // Build Graph
        foreach ($this->stopIdToRoutes as $routes) {
            $count = count($routes);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
    
                    $this->routeAdjGraph[$routes[$i]][$routes[$j]] = true;
                    $this->routeAdjGraph[$routes[$j]][$routes[$i]] = true;
                }
            }
        }
    }

    private function findClosestStop(float $lat, float $lon): array {
        $minDist = INF;
        $closest = null;
        foreach ($this->busStops as $stop) {
            $d = $this->haversine($lat, $lon, $stop["lat"], $stop["lon"]);
            if ($d < $minDist) {
                $minDist = $d;
                $closest = $stop;
            }
        }
        return [$closest, $minDist];
    }

    private function getStopsForRoute(int $routeId, int $startId, int $endId): array {
        $ordered = $this->routeIdToStops[$routeId] ?? [];
        $i1 = array_search($startId, $ordered);
        $i2 = array_search($endId, $ordered);
        if ($i1 === false || $i2 === false) return [];

        if ($i1 < $i2) $slice = array_slice($ordered, $i1, $i2 - $i1 + 1);
        else $slice = array_reverse(array_slice($ordered, $i2, $i1 - $i2 + 1));

        $stopsData = [];
        foreach ($slice as $id) {
            if(isset($this->stopIdToStop[$id])) {
                $s = $this->stopIdToStop[$id];
                $stopsData[] = [
                    "id" => $id,
                    "name" => $s["tags"]["name"] ?? "Stop ID $id",
                    "lat" => $s["lat"],
                    "lon" => $s["lon"]
                ];
            }
        }
        return $stopsData;
    }

    private function calculateRouteDistance(int $routeId, int $startStopId, int $endStopId): float {
        $orderedStopIds = $this->routeIdToStops[$routeId] ?? [];
        
        $start_index = array_search($startStopId, $orderedStopIds);
        $end_index = array_search($endStopId, $orderedStopIds);

        if ($start_index === false || $end_index === false) return INF;
        
        if ($start_index < $end_index) {
            $path_stop_ids = array_slice($orderedStopIds, $start_index, $end_index - $start_index + 1);
        } else {
            $path_stop_ids = array_reverse(array_slice($orderedStopIds, $end_index, $start_index - $end_index + 1));
        }
        
        $total_distance = 0.0;
        $count = count($path_stop_ids);
        for ($i = 0; $i < $count - 1; $i++) {
            $stop1 = $this->stopIdToStop[$path_stop_ids[$i]] ?? null;
            $stop2 = $this->stopIdToStop[$path_stop_ids[$i + 1]] ?? null;
            
            if ($stop1 && $stop2 && isset($stop1['lat'], $stop1['lon'], $stop2['lat'], $stop2['lon'])) {
                $total_distance += $this->haversine($stop1['lat'], $stop1['lon'], $stop2['lat'], $stop2['lon']);
            }
        }
        
        return $total_distance;
    }

    private function findBestDirectRoute(int $startStopId, int $endStopId, array $commonRoutes): array {
        $bestRouteId = null;
        $minDistance = INF;
        
        foreach ($commonRoutes as $routeId) {
            $distance = $this->calculateRouteDistance($routeId, $startStopId, $endStopId);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $bestRouteId = $routeId;
            }
        }
        
        return [$bestRouteId, $minDistance];
    }
    

    private function findShortestDistancePath(int $startStopId, int $endStopId, array $startRouteSet, array $endRouteSet): array {
  
        $queue = new SplQueue();
        $visited = [];
        $minTransfers = INF;
        $allPaths = [];

        // Start BFS from all routes connected to the start stop
        foreach ($startRouteSet as $startRoute) {
            $queue->enqueue([$startRoute, [$startRoute]]);
            $visited[$startRoute] = true;
        }

        while (!$queue->isEmpty()) {
            [$currentRoute, $path] = $queue->dequeue();
            $transfers = count($path) - 1;

            if ($transfers > $minTransfers) continue;
            
         
            if (in_array($currentRoute, $endRouteSet)) {
                if ($transfers < $minTransfers) {
                    $minTransfers = $transfers;
                    $allPaths = [$path];
                } elseif ($transfers === $minTransfers) {
                    $allPaths[] = $path;
                }
                continue;
            }
            
            // Explore neighbors (transfers)
            foreach ($this->routeAdjGraph[$currentRoute] ?? [] as $neighborRoute => $val) {
                if (!isset($visited[$neighborRoute])) {
                    $visited[$neighborRoute] = true;
                    $newPath = $path; $newPath[] = $neighborRoute;
                    $queue->enqueue([$neighborRoute, $newPath]);
                }
            }
        }

        if (empty($allPaths)) return [null, INF];
        
        // Calculate total distance for each path and pick the shortest
        $bestPath = null;
        $minTotalDistance = INF;
        
        foreach ($allPaths as $path) {
            $totalDistance = 0.0;
            $currentStopId = $startStopId;
            $pathCount = count($path);
            
            for ($i = 0; $i < $pathCount; $i++) {
                $routeId = $path[$i];
                
                if ($i === $pathCount - 1) {
                    // Last route: go to end stop
                    $nextStopId = $endStopId;
                } else {
                    // Find transfer point to next route
                    $nextRouteId = $path[$i + 1];
                    $currentRouteStops = $this->routeIdToStops[$routeId] ?? [];
                    $nextRouteStops = $this->routeIdToStops[$nextRouteId] ?? [];
                    $transferStops = array_intersect($currentRouteStops, $nextRouteStops);
                    
                    if (empty($transferStops)) {
                        $totalDistance = INF;
                        break;
                    }
                    
                    // Choose the transfer stop that minimizes distance from current position
                    $bestTransferStop = null;
                    $minSegmentDist = INF;
                    
                    foreach ($transferStops as $transferStopId) {
                        $segmentDist = $this->calculateRouteDistance($routeId, $currentStopId, $transferStopId);
                        if ($segmentDist < $minSegmentDist) {
                            $minSegmentDist = $segmentDist;
                            $bestTransferStop = $transferStopId;
                        }
                    }
                    
                    $nextStopId = $bestTransferStop;
                }
                
     
                $segmentDistance = $this->calculateRouteDistance($routeId, $currentStopId, $nextStopId);
                $totalDistance += $segmentDistance;
                $currentStopId = $nextStopId;
            }
            
            if ($totalDistance < $minTotalDistance) {
                $minTotalDistance = $totalDistance;
                $bestPath = $path;
            }
        }

        return [$bestPath, $minTotalDistance];
    }


    public function findRoute($startLat, $startLon, $endLat, $endLon): array {
        [$startStop, $distA] = $this->findClosestStop($startLat, $startLon);
        [$endStop, $distB] = $this->findClosestStop($endLat, $endLon);
        if (!$startStop || !$endStop) return ["error" => "Could not find valid start or end stops."];

        $startId = $startStop["id"];
        $endId = $endStop["id"];
        
        $startName = $startStop["tags"]["name"] ?? "Stop ID $startId";
        $endName = $endStop["tags"]["name"] ?? "Stop ID $endId";

        $result = [
            "start_stop" => [
                "name" => $startName, "id" => $startId, "lat" => $startStop["lat"], "lon" => $startStop["lon"], "distance_km" => round($distA, 2)
            ],
            "end_stop" => [
                "name" => $endName, "id" => $endId, "lat" => $endStop["lat"], "lon" => $endStop["lon"], "distance_km" => round($distB, 2)
            ]
        ];

        $startRoutes = $this->stopIdToRoutes[$startId] ?? [];
        $endRoutes = $this->stopIdToRoutes[$endId] ?? [];

        if (empty($startRoutes)) { $result['error'] = "Start stop '$startName' is not part of any known routes."; return $result; }
        if (empty($endRoutes)) { $result['error'] = "End stop '$endName' is not part of any known routes."; return $result; }

        $common = array_intersect($startRoutes, $endRoutes);

        // 1. Direct Route
        if (!empty($common)) {
            [$bestRouteId, $routeDistance] = $this->findBestDirectRoute($startId, $endId, array_values($common));
            
            $routeInfo = $this->routeIdToRoute[$bestRouteId] ?? [];
            $routeName = $routeInfo["tags"]["name"] ?? "Route ID $bestRouteId";

            $result["route_type"] = "Direct";
            $result["total_distance_km"] = round($routeDistance, 2);
            $result["route"] = [
                "name" => $routeName,
                "id" => $bestRouteId,
                "stops" => $this->getStopsForRoute($bestRouteId, $startId, $endId)
            ];
            return $result;
        }

        // 2. Indirect Route
        [$routePathIds, $totalDistance] = $this->findShortestDistancePath($startId, $endId, $startRoutes, $endRoutes);
        
        if ($routePathIds && $totalDistance < INF) {
            $journeyLegs = [];
            $currentStopId = $startId;
            $pathCount = count($routePathIds);

            for ($i = 0; $i < $pathCount; $i++) {
                $routeId = $routePathIds[$i];
                $routeInfo = $this->routeIdToRoute[$routeId] ?? [];
                $routeName = $routeInfo["tags"]["name"] ?? "Route ID $routeId";
                $leg = ["route_name" => $routeName, "route_id" => $routeId];

                // Determine the end stop for this leg (transfer point or final destination)
                $legEndStopId = $endId;
                if ($i < $pathCount - 1) {
                    $nextRouteId = $routePathIds[$i+1];
                    $currentRouteStops = $this->routeIdToStops[$routeId] ?? [];
                    $nextRouteStops = $this->routeIdToStops[$nextRouteId] ?? [];
                    $transferStopIds = array_intersect($currentRouteStops, $nextRouteStops);
                    
                    // The same logic from findShortestDistancePath to select the best transfer stop
                    $bestTransferStopId = null;
                    $minDist = INF;
                    foreach ($transferStopIds as $transferStopId) {
                        $dist = $this->calculateRouteDistance($routeId, $currentStopId, $transferStopId);
                        if ($dist < $minDist) {
                            $minDist = $dist;
                            $bestTransferStopId = $transferStopId;
                        }
                    }
                    $legEndStopId = $bestTransferStopId;
                }

                $legDistance = $this->calculateRouteDistance($routeId, $currentStopId, $legEndStopId);
                $leg['distance_km'] = round($legDistance, 2);
                $leg['stops'] = $this->getStopsForRoute($routeId, $currentStopId, $legEndStopId);
                
                // Add transfer information
                if ($i < $pathCount - 1 && $legEndStopId) {
                    $transferStopElem = $this->stopIdToStop[$legEndStopId] ?? [];
                    $transferStopName = $transferStopElem["tags"]["name"] ?? "Stop ID $legEndStopId";
                    $leg['transfer_at'] = [
                        "name" => $transferStopName,
                        "lat" => $transferStopElem["lat"] ?? null,
                        "lon" => $transferStopElem["lon"] ?? null
                    ];
                }
                
                $journeyLegs[] = $leg;
                $currentStopId = $legEndStopId;
            }

            $result['route_type'] = "Indirect";
            $result['transfers_needed'] = $pathCount - 1;
            $result['total_distance_km'] = round($totalDistance, 2);
            $result['journey'] = $journeyLegs;
            return $result;
        }

        return ["error" => "No route found, even with transfers."];
    }
}
?>