<?php

/* rose.mpawenayo */
require 'authentication.php';
require 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle route deletion 
if (isset($_POST['delete_route'])) {
    $route_id = (int)$_POST['route_id'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE route_results SET user_id = NULL WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $route_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    
    header('Location: my_routes.php?deleted=1');
    exit();
}

// Fetch user's saved routes
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT id, json_data, request_time
    FROM route_results
    WHERE user_id = ?
    ORDER BY request_time DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$saved_routes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Saved Routes - Bus Route Finder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .route-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .route-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .route-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Saved Routes</h1>
        <a href="dashboard.html" class="btn btn-primary">Find New Route</a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Route deleted successfully.</div>
    <?php endif; ?>

    <div class="row">
        <?php if (empty($saved_routes)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-route fa-4x mb-3"></i>
                    <h3>No saved routes yet</h3>
                    <p>Your saved routes will appear here.</p>
                    <a href="dashboard.html" class="btn btn-primary mt-3">Find a Route</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($saved_routes as $route): 
                // Decode JSON from route_results
                $route_data = json_decode($route['json_data'], true)['result'] ?? [];

                $start_name = $route_data['start_stop']['name'] ?? 'Unknown';
                $end_name = $route_data['end_stop']['name'] ?? 'Unknown';
                $route_type = $route_data['route_type'] ?? 'Unknown';
                $distance = isset($route_data['total_distance_km']) ? round($route_data['total_distance_km'], 2) . ' km' : 'N/A';
                $transfers = $route_data['transfers_needed'] ?? 0;

            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card route-card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($route['route_name']); ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted">
                            Saved on <?php echo date('M j, Y', strtotime($route['request_time'])); ?>
                        </h6>
                        <p class="card-text">
                            <strong>From:</strong> <?php echo htmlspecialchars($start_name); ?><br>
                            <strong>To:</strong> <?php echo htmlspecialchars($end_name); ?><br>
                            <strong>Type:</strong> <?php echo htmlspecialchars($route_type); ?><br>
                            <strong>Transfers:</strong> <?php echo htmlspecialchars($transfers); ?>
                        </p>
                        <div class="d-flex justify-content-between text-muted small mb-3">
                            <span><i class="fas fa-route"></i> <?php echo $distance; ?></span>
                        </div>
                        <div class="route-actions">
                            <a href="route_details.php?id=<?php echo $route['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this route?');">
                                <input type="hidden" name="route_id" value="<?php echo $route['id']; ?>">
                                <button type="submit" name="delete_route" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</body>
</html>
<?php $conn->close(); ?>
