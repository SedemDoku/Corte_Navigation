<?php

/* rose.mpawenayo*/

require 'db_connect.php';
require 'authentication.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get route ID from URL
$route_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT json_data FROM route_results WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $route_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$route = $result->fetch_assoc();
$stmt->close();

if (!$route) {
    die("Route not found or access denied.");
}

$route_json = json_decode($route['json_data'], true);
$route_result = $route_json['result']; 
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Route - Corte Navigation</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
    body { margin:0; font-family:'Inter', sans-serif; display:flex; height:100vh; overflow:hidden; background:#0e1012; color:#fff;}
    #map { flex-grow:1; height:100%; }
    .sidebar { width:64px; background:#17191c; border-right:1px solid #2b2f36; display:flex; flex-direction:column; align-items:center; padding-top:24px; }
    .nav-item { width:40px; height:40px; margin-bottom:16px; cursor:pointer; color:#9ca3af; border:none; background:transparent; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; text-decoration:none;}
    .nav-item:hover { background:#23262b; color:#fff; }
    .nav-item.active { background: rgba(0,122,252,0.15); color:#007afc; }
    .nav-spacer { flex-grow:1; }
    .profile-icon { width:32px;height:32px;background:#007afc;border-radius:50%;margin-bottom:24px; display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:bold;}
    .instructions-panel { position:absolute; top:20px; left:84px; width:360px; background:#17191c; border:1px solid #2b2f36; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.4); padding:24px; max-height:90vh; overflow-y:auto; z-index:5;}
    .leg-card { background:#0e1012; border:1px solid #2b2f36; border-left:4px solid #007afc; border-radius:6px; padding:12px 16px; margin-bottom:10px;}
    .leg-header { font-weight:600; color:#fff; margin-bottom:4px; display:block;}
    .leg-details { color:#9ca3af; font-size:0.85rem; }
    .leg-meta { display:flex; align-items:center; gap:10px; margin-top:8px; font-size:0.8rem; color:#9ca3af; }
    .badge { background: rgba(0,122,252,0.2); color: #007afc; padding: 2px 6px; border-radius:4px; font-size:0.75rem; font-weight:600;}
    .badge.transfer { background: rgba(255,165,0,0.2); color:#ffa500;}
</style>
</head>
<body>

<aside class="sidebar">
    <button class="nav-item" onclick="window.location.href='my_routes.php'" title="Back to My Routes">
        <i class="fa-solid fa-arrow-left"></i>
    </button>
    <div class="nav-spacer"></div>
    <div class="profile-icon">CN</div>
</aside>

<div class="instructions-panel" id="instructions"></div>
<div id="map"></div>

<script>
let map, currentPolyline = null, markers = [];

const routeData = <?php echo json_encode($route_result, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

// Dark theme map
const mapStyles = [
    { elementType: "geometry", stylers: [{ color: "#242f3e" }] },
    { elementType: "labels.text.stroke", stylers: [{ color: "#242f3e" }] },
    { elementType: "labels.text.fill", stylers: [{ color: "#746855" }] },
    { featureType: "administrative.locality", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] },
    { featureType: "poi", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] },
    { featureType: "poi.park", elementType: "geometry", stylers: [{ color: "#263c3f" }] },
    { featureType: "poi.park", elementType: "labels.text.fill", stylers: [{ color: "#6b9a76" }] },
    { featureType: "road", elementType: "geometry", stylers: [{ color: "#38414e" }] },
    { featureType: "road", elementType: "geometry.stroke", stylers: [{ color: "#212a37" }] },
    { featureType: "road", elementType: "labels.text.fill", stylers: [{ color: "#9ca5b3" }] },
    { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#746855" }] },
    { featureType: "road.highway", elementType: "geometry.stroke", stylers: [{ color: "#1f2835" }] },
    { featureType: "road.highway", elementType: "labels.text.fill", stylers: [{ color: "#f3d19c" }] },
    { featureType: "transit", elementType: "geometry", stylers: [{ color: "#2f3948" }] },
    { featureType: "transit.station", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] },
    { featureType: "water", elementType: "geometry", stylers: [{ color: "#17263c" }] },
    { featureType: "water", elementType: "labels.text.fill", stylers: [{ color: "#515c6d" }] },
    { featureType: "water", elementType: "labels.text.stroke", stylers: [{ color: "#17263c" }] }
];

function initMap() {
    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 13,
        center: {lat: routeData.start_stop.lat, lng: routeData.start_stop.lon},
        styles: mapStyles,
        disableDefaultUI:false,
        mapTypeControl:false,
        streetViewControl:false
    });

    const bounds = new google.maps.LatLngBounds();

    function addMarker(lat,lng,title,icon,label){
        const marker = new google.maps.Marker({position:{lat,lng},map:map,title:title,icon:icon,label:label});
        markers.push(marker);
        bounds.extend({lat,lng});
    }

    // Add start and end markers
    addMarker(routeData.start_stop.lat, routeData.start_stop.lon, `Start: ${routeData.start_stop.name}`, 'http://maps.google.com/mapfiles/ms/icons/green-dot.png','A');
    addMarker(routeData.end_stop.lat, routeData.end_stop.lon, `End: ${routeData.end_stop.name}`, 'http://maps.google.com/mapfiles/ms/icons/red-dot.png','B');

    let instructDiv = document.getElementById("instructions");
    instructDiv.innerHTML = "";

    if(routeData.route_type === "Direct") {
        const leg = routeData.route;
        instructDiv.innerHTML = `
            <div class="leg-card">
                <span class="leg-header"><i class="fa-solid fa-bus"></i> Direct Route Found</span>
                <div class="leg-details">Take <strong>${leg.name}</strong> to your destination.</div>
                <div class="leg-meta">
                    <span class="badge">Direct</span>
                    <span><i class="fa-solid fa-stop"></i> ${leg.stops.length} stops</span>
                    <span><i class="fa-solid fa-route"></i> ${routeData.total_distance_km} km</span>
                </div>
            </div>
        `;
        const path = leg.stops.map(s => ({lat:s.lat,lng:s.lon}));
        currentPolyline = new google.maps.Polyline({path:path, geodesic:true, strokeColor:"#007afc", strokeOpacity:1.0, strokeWeight:6, map:map});
        path.forEach(p => bounds.extend(p));
    } else if(routeData.route_type === "Indirect" && Array.isArray(routeData.journey)) {
        const colors = ["#007afc","#4CAF50","#FFC107","#E91E63","#9C27B0"];
        routeData.journey.forEach((leg,idx)=>{
            const stops = leg.stops || [];
            const transferInfo = leg.transfer_at ? `<div class='leg-details'><i class="fa-solid fa-right-left"></i> Transfer at: <strong>${leg.transfer_at.name}</strong></div>` : '';
            instructDiv.innerHTML += `
                <div class="leg-card" style="border-left-color: ${colors[idx % colors.length]}80;">
                    <span class="leg-header"><i class="fa-solid fa-bus"></i> Leg ${idx+1}: Take <strong>${leg.route_name}</strong></span>
                    <div class="leg-meta">
                        <span class="badge" style="background:${colors[idx % colors.length]}33;color:${colors[idx % colors.length]};">Leg ${idx+1}</span>
                        <span><i class="fa-solid fa-route"></i> ${leg.distance_km} km</span>
                        <span><i class="fa-solid fa-stop"></i> ${stops.length} stops</span>
                    </div>
                    ${transferInfo}
                </div>
            `;
            const legPath = stops.map(s => ({lat:s.lat,lng:s.lon}));
            if(legPath.length>0) new google.maps.Polyline({path:legPath, geodesic:true, strokeColor:colors[idx%colors.length], strokeOpacity:1.0, strokeWeight:6, map:map});
            if(leg.transfer_at) addMarker(leg.transfer_at.lat,leg.transfer_at.lon,`Transfer: ${leg.transfer_at.name}`,'http://maps.google.com/mapfiles/ms/icons/orange-dot.png',`T${idx+1}`);
        });
    } else {
        instructDiv.innerHTML = "<div class='leg-card'>No valid route data available.</div>";
    }

    map.fitBounds(bounds);
}
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAmF3jeRV9rWZTUEWXtusYzm95WNJnBNZc&callback=initMap&loading=async" async defer></script>
</body>
</html>
