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
                $this->stopIdToRoutes[$el["id"]] = $el["relations"] ?? [];
            }
        }
        // Load routes
        $routeData = json_decode(file_get_contents($this->routeFile), true);
        foreach ($routeData["elements"] as $route) {
            if (($route["type"] ?? "") === "relation") {
                $this->routeIdToRoute[$route["id"]] = $route;
                $ordered = [];
                foreach ($route["members"] as $m) {
                    if (($m["type"] ?? "") === "node") $ordered[] = $m["ref"];
                }
                $this->routeIdToStops[$route["id"]] = $ordered;
            }
        }
        // Build Graph
        foreach ($this->stopIdToRoutes as $routes) {
            $routes = array_values($routes);
            $count = count($routes);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $this->routeAdjGraph[$routes[$i]][] = $routes[$j];
                    $this->routeAdjGraph[$routes[$j]][] = $routes[$i];
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


    private function getStopsForRoute($routeId, $startId, $endId): array {
        $ordered = $this->routeIdToStops[$routeId];
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
                    "name" => $s["tags"]["name"] ?? "Stop $id",
                    "lat" => $s["lat"],
                    "lon" => $s["lon"]
                ];
            }
        }
        return $stopsData;
    }

    private function bfsTransfers(array $startRoutes, array $endRoutes): ?array {
        $queue = new SplQueue();
        $visited = [];
        foreach ($startRoutes as $r) {
            $queue->enqueue([$r, [$r]]);
            $visited[$r] = true;
        }
        while (!$queue->isEmpty()) {
            [$route, $path] = $queue->dequeue();
            if (in_array($route, $endRoutes)) return $path;
            foreach ($this->routeAdjGraph[$route] ?? [] as $nbr) {
                if (!isset($visited[$nbr])) {
                    $visited[$nbr] = true;
                    $newPath = $path; $newPath[] = $nbr;
                    $queue->enqueue([$nbr, $newPath]);
                }
            }
        }
        return null;
    }

    public function findRoute($startLat, $startLon, $endLat, $endLon): array {
        [$startStop, $distA] = $this->findClosestStop($startLat, $startLon);
        [$endStop, $distB] = $this->findClosestStop($endLat, $endLon);
        if (!$startStop || !$endStop) return ["error" => "Stops not found nearby"];

        $startId = $startStop["id"];
        $endId = $endStop["id"];
        $result = [
            "start_stop" => $startStop["tags"]["name"] ?? "Stop $startId",
            "end_stop" => $endStop["tags"]["name"] ?? "Stop $endId"
        ];

        $startRoutes = $this->stopIdToRoutes[$startId];
        $endRoutes = $this->stopIdToRoutes[$endId];
        $common = array_intersect($startRoutes, $endRoutes);

        // 1. Direct Route
        if (!empty($common)) {
            $routeId = array_values($common)[0];
            $result["type"] = "Direct";
            $result["legs"][] = [
                "route_name" => $this->routeIdToRoute[$routeId]["tags"]["name"] ?? "Route $routeId",
                "stops" => $this->getStopsForRoute($routeId, $startId, $endId)
            ];
            return $result;
        }

        // 2. Indirect Route
        $path = $this->bfsTransfers($startRoutes, $endRoutes);
        if ($path) {
            $result["type"] = "Indirect";
            // Logic to stitch legs together
            // Note: Simplified logic. In production, you must find the specific transfer stop ID between route A and B.
            // For now, we will return the route names so the frontend can at least show instructions.
             $legs = [];
             // NOTE: Implementing full geometry stitching for indirect routes requires 
             // finding the specific intersection node between Route A and Route B. 
             // For this prototype, we return the route metadata.
             foreach($path as $rid) {
                 $legs[] = [
                     "route_name" => $this->routeIdToRoute[$rid]["tags"]["name"] ?? "Route $rid",
                     "route_id" => $rid
                     // To draw lines here, we would need the transfer Node IDs. 
                 ];
             }
             $result["legs"] = $legs;
             return $result;
        }

        return ["error" => "No route found"];
    }
}
?>