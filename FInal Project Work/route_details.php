<?php
require 'authentication.php';
require 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if route ID is provided
if (!isset($_GET['id'])) {
    header('Location: my_routes.php');
    exit();
}

$route_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch the route details
$stmt = $conn->prepare("SELECT * FROM saved_routes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $route_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$route = $result->fetch_assoc();
$stmt->close();

// If route not found or doesn't belong to user, redirect
if (!$route) {
    header('Location: my_routes.php');
    exit();
}

$route_data = json_decode($route['route_data'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($route['route_name']); ?> | Saved Route</title>

<!-- Fonts & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />

<style>
:root {
    --bg-dark: #0e1012;
    --bg-panel: #17191c;
    --bg-input: #23262b;
    --text-main: #ffffff;
    --text-muted: #9ca3af;
    --primary: #007afc;
    --primary-hover: #0062ca;
    --border: #2b2f36;
    --shadow: 0 4px 20px rgba(0,0,0,0.4);
}

/* Reset & layout */
body { margin:0; font-family:'Inter',sans-serif; display:flex; height:100vh; overflow:hidden; background-color: var(--bg-dark); color: var(--text-main); }
.sidebar { width:64px; background: var(--bg-panel); border-right:1px solid var(--border); display:flex; flex-direction:column; align-items:center; padding-top:24px; z-index:10; }
.nav-item { width:40px; height:40px; margin-bottom:16px; cursor:pointer; color: var(--text-muted); border:none; background:transparent; border-radius:10px; transition: all 0.2s ease; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.nav-item:hover { background: var(--bg-input); color: var(--text-main); }
.nav-item.active { background: rgba(0,122,252,0.15); color: var(--primary); }
.nav-spacer { flex-grow:1; }
.profile-icon { width:32px; height:32px; background: var(--primary); border-radius:50%; margin-bottom:24px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:bold; }

/* Floating panel */
.search-panel { position:absolute; top:20px; left:84px; width:360px; background: var(--bg-panel); border:1px solid var(--border); border-radius:12px; box-shadow: var(--shadow); z-index:5; padding:24px; display:flex; flex-direction:column; gap:12px; overflow-y:auto; max-height:90vh; }
.logo { color: var(--text-main); font-weight:700; font-size:1.1rem; margin-bottom:8px; display:flex; align-items:center; gap:8px; }
.logo span { color: var(--primary); }
.input-group { position:relative; }
.input-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color: var(--text-muted); font-size:0.8rem; }
input { width:100%; padding:12px 12px 12px 36px; background: var(--bg-input); border:1px solid var(--border); border-radius:8px; box-sizing:border-box; color: var(--text-main); font-family:inherit; font-size:0.9rem; transition:border-color 0.2s; }
input:focus { outline:none; border-color: var(--primary); }

/* Map */
#map { flex-grow:1; height:100%; }

/* Instructions */
#instructions { margin-top:10px; max-height:500px; overflow-y:auto; font-size:0.9rem; padding-right:5px; }
#instructions::-webkit-scrollbar { width:6px; }
#instructions::-webkit-scrollbar-track { background: var(--bg-dark); }
#instructions::-webkit-scrollbar-thumb { background: var(--border); border-radius:3px; }

/* Route cards */
.leg-card { background: var(--bg-dark); border:1px solid var(--border); border-left:4px solid var(--primary); border-radius:6px; padding:12px 16px; margin-bottom:10px; }
.leg-header { font-weight:600; color: var(--text-main); margin-bottom:4px; display:block; }
.leg-details { color: var(--text-muted); font-size:0.85rem; }
.leg-meta { display:flex; align-items:center; gap:10px; margin-top:8px; font-size:0.8rem; color: var(--text-muted); }
.badge { background: rgba(0,122,252,0.2); color: var(--primary); padding:2px 6px; border-radius:4px; font-size:0.75rem; font-weight:600; }
.badge.transfer { background: rgba(255,165,0,0.2); color:#ffa500; }

</style>
</head>
<body>

<aside class="sidebar">
    <button class="nav-item" onclick="window.location.href='dashobard.html'" title="Back to Home">
        <i class="fa-solid fa-arrow-left"></i>
    </button>
    <button class="nav-item active" title="Route Details">
        <i class="fa-solid fa-location-arrow"></i>
    </button>
    <a href="my_routes.php" class="nav-item" title="My Routes">
        <i class="fa-regular fa-bookmark"></i>
    </a>
    <div class="nav-spacer"></div>
    <div class="profile-icon">CN</div>
</aside>

<div class="search-panel">
    <div class="logo"><i class="fa-solid fa-layer-group"></i> <?php echo htmlspecialchars($route['route_name']); ?><span>Navigation</span></div>
    <div id="instructions"></div>
</div>

<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
    const map = L.map('map').setView([<?php echo $route['start_lat']; ?>, <?php echo $route['start_lng']; ?>], 13);

    // Dark tiles
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);

    const bounds = [];

    function addMarker(lat, lng, color, label) {
        const marker = L.circleMarker([lat, lng], {
            radius: 8,
            color: color,
            fillColor: color,
            fillOpacity: 1,
            weight: 2
        }).addTo(map);
        if(label) marker.bindTooltip(label, {permanent:true,direction:'top'});
        bounds.push([lat,lng]);
    }

    <?php if(!empty($route_data['geometry'])): ?>
        const routeCoords = <?php echo json_encode($route_data['geometry']['coordinates']); ?>;
        const latLngs = routeCoords.map(c=>[c[1],c[0]]);

        // Draw main route polyline
        L.polyline(latLngs, {color:'#007afc', weight:6, opacity:0.8}).addTo(map);

        // Start & End markers
        addMarker(latLngs[0][0], latLngs[0][1],'#28a745','Start');
        addMarker(latLngs[latLngs.length-1][0], latLngs[latLngs.length-1][1],'#dc3545','End');

        // Add intermediate stops if available
        <?php if(!empty($route_data['steps'])): ?>
            <?php foreach($route_data['steps'] as $idx => $step): ?>
                <?php if(!empty($step['lat']) && !empty($step['lng'])): ?>
                    addMarker(<?php echo $step['lat']; ?>, <?php echo $step['lng']; ?>, '#ffc107','<?php echo $idx+1; ?>');
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        map.fitBounds(bounds);
    <?php endif; ?>

    // Display steps in side panel
    const instructionsDiv = document.getElementById('instructions');
    instructionsDiv.innerHTML = '';
    <?php if(!empty($route_data['steps'])): ?>
        <?php foreach($route_data['steps'] as $idx => $step): ?>
            const stepCard = document.createElement('div');
            stepCard.className = 'leg-card';
            stepCard.innerHTML = `
                <span class="leg-header">
                    <i class="fa-solid fa-bus"></i> Step <?php echo $idx+1; ?>
                </span>
                <div class="leg-details"><?php echo htmlspecialchars($step['instruction']); ?></div>
                <div class="leg-meta">
                    <span class="badge"><?php echo round($step['distance']); ?> m</span>
                    <span class="badge"><?php echo round($step['duration']/60); ?> min</span>
                </div>
            `;
            instructionsDiv.appendChild(stepCard);
        <?php endforeach; ?>
    <?php else: ?>
        instructionsDiv.innerHTML = '<p>No detailed steps available.</p>';
    <?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>
