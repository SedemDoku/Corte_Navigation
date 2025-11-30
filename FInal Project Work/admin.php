<?php
session_start();
require 'db_connect.php';
/* winifred.arthur */
// Check if user is admin - verify credentials against database
require 'authentication.php'; // ensures session started and auto-login handled

// Check if user is logged in
if (!is_logged_in() || !is_admin()) {
    $_SESSION['error'] = "Admin access required";
    header('Location: login.html');
    exit;
}


// Handle user deletion
if (isset($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);
    
    // *Prevent admin from deleting themselves*
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Cannot delete your own admin account";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "User deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting user";
        }
    }
    header('Location: admin.php');
    exit;
}

// Handle admin promotion/demotion
if (isset($_GET['toggle_admin'])) {
    $user_id = intval($_GET['toggle_admin']);
    
    // *Prevent admin from demoting themselves*
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Cannot remove admin rights from yourself";
    } else {
        // Toggle boolean role (1 -> 0 or 0 -> 1)
        $stmt = $conn->prepare("UPDATE users SET role = CASE WHEN role = 1 THEN 0 ELSE 1 END WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $res = $conn->query("SELECT role FROM users WHERE id = $user_id");
            $roleAfter = ($res && $r = $res->fetch_assoc()) ? ($r['role'] ? 'admin' : 'user') : 'user';
            $_SESSION['message'] = "User admin status updated ({$roleAfter})";
        } else {
            $_SESSION['error'] = "Error updating user admin status";
        }
    }
    header('Location: admin.php');
    exit;
}

// Get statistics from database
$total_users = 0;
$total_routes = 0;
$recent_users = 0;
$admin_count = 0;

// *Count total users*
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    $total_users = $row['count'];
}

// *Count saved routes - assuming saved_routes table exists*
$result = $conn->query("SELECT COUNT(*) as count FROM saved_routes");
if ($result) {
    $row = $result->fetch_assoc();
    $total_routes = $row['count'];
}

// *Count recent users (last 7 days)*
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($result) {
    $row = $result->fetch_assoc();
    $recent_users = $row['count'];
}

// *Count admin users*
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 1");
if ($result) {
    $row = $result->fetch_assoc();
    $admin_count = $row['count'];
}

// Get all users for management table (include role)
$users = [];
$result = $conn->query("SELECT id, username, created_at, role FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin Panel | Corte Navigation</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

  <style>
    :root {
        --bg-dark: #0e1012;
        --bg-card: #17191c;
        --bg-input: #23262b;
        --text-main: #ffffff;
        --text-muted: #9ca3af;
        --primary: #007afc;
        --primary-hover: #0062ca;
        --border: #2b2f36;
        --error: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--bg-dark);
        color: var(--text-main);
        min-height: 100vh;
    }

    /* Header */
    .admin-header {
        background: var(--bg-card);
        padding: 20px 40px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 1.2rem;
    }
    .logo i { color: var(--primary); }
    .logo span { color: var(--primary); }

    .admin-actions {
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .user-info {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }

    .btn-outline {
        background: transparent;
        color: var(--text-muted);
        border: 1px solid var(--border);
    }

    .btn-outline:hover {
        border-color: var(--text-main);
        color: var(--text-main);
    }

    .btn-danger {
        background: var(--error);
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
    }

    .btn-warning {
        background: var(--warning);
        color: white;
    }

    .btn-warning:hover {
        background: #d97706;
    }

    .btn-success {
        background: var(--success);
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
    }

    /* Main Content */
    .admin-container {
        padding: 40px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--bg-card);
        padding: 24px;
        border-radius: 12px;
        border: 1px solid var(--border);
        text-align: center;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 8px;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    /* Content Sections */
    .content-section {
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .section-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-content {
        padding: 24px;
    }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }

    .data-table th {
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table tr:hover {
        background: rgba(255,255,255,0.02);
    }

    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-success {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
    }

    .badge-warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
    }

    .badge-primary {
        background: rgba(0, 122, 252, 0.2);
        color: var(--primary);
    }

    /* Status Messages */
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.2);
        color: var(--error);
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .action-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
  </style>
</head>
<body>

  <header class="admin-header">
    <div class="logo">
        <i class="fa-solid fa-layer-group"></i> Corte<span>Navigation</span> Admin
    </div>
    <div class="admin-actions">
        <span class="user-info">
            <i class="fa-solid fa-user-shield"></i> 
            <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
        </span>
        <a href="dashboard.html" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to App
        </a>
        <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
  </header>

  <div class="admin-container">
    <?php
    // Handle logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: login.html');
        exit;
    }

    // Show messages
    if (isset($_SESSION['message'])) {
        echo '<div class="alert alert-success">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_users; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_routes; ?></div>
            <div class="stat-label">Saved Routes</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $recent_users; ?></div>
            <div class="stat-label">New Users (7 days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $admin_count; ?></div>
            <div class="stat-label">Admin Users</div>
        </div>
    </div>

    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fa-solid fa-users"></i> User Management
            </h2>
            <div class="admin-actions">
                <span class="stat-label"><?php echo count($users); ?> users total</span>
            </div>
        </div>
        <div class="section-content">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-users-slash"></i>
                    <h3>No Users Found</h3>
                    <p>No users have registered yet.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Joined Date</th>
                            <th>Account Age</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $is_current_user = ($user['id'] == $_SESSION['user_id']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if ($is_current_user): ?>
                                    <span class="badge badge-primary">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php
                                $join_date = new DateTime($user['created_at']);
                                $now = new DateTime();
                                $interval = $now->diff($join_date);
                                echo $interval->days . ' days';
                                ?>
                            </td>
                            <td>
                                <?php if ($user['role']): ?>
                                    <span class="badge badge-success">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-group">
                                    <?php if (!$is_current_user): ?>
                                        <?php if ($user['role']): ?>
                                            <a href="?toggle_admin=<?php echo $user['id']; ?>" 
                                               class="btn btn-warning btn-sm"
                                               onclick="return confirm('Remove admin rights from <?php echo htmlspecialchars($user['username']); ?>?')">
                                                <i class="fa-solid fa-user-minus"></i> Demote
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle_admin=<?php echo $user['id']; ?>" 
                                               class="btn btn-success btn-sm"
                                               onclick="return confirm('Grant admin rights to <?php echo htmlspecialchars($user['username']); ?>?')">
                                                <i class="fa-solid fa-user-plus"></i> Promote
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?delete_user=<?php echo $user['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Permanently delete user <?php echo htmlspecialchars($user['username']); ?>? This cannot be undone.')">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </a>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--border); color: var(--text-muted);">Current User</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fa-solid fa-database"></i> Database Status
            </h2>
        </div>
        <div class="section-content">
            <div style="background: var(--bg-input); padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.9rem;">
                <?php
                // *Check if data files exist*
                $busStopFile = "Data/BusStopData_with_relations.json";
                $routeFile = "Data/RouteData.json";
                
                echo "Database: " . ($conn->connect_error ? "Disconnected" : "Connected") . "<br>";
                echo "Users Table: " . ($total_users >= 0 ? "OK ($total_users users)" : "Error") . "<br>";
                echo "Admins: $admin_count users<br>";
                echo "Routes Table: " . ($total_routes >= 0 ? "OK ($total_routes routes)" : "Error") . "<br>";
                echo "BusStop Data: " . (file_exists($busStopFile) ? "Loaded" : "Missing") . "<br>";
                echo "Route Data: " . (file_exists($routeFile) ? "Loaded" : "Missing") . "<br>";
                echo "Last Check: " . date('Y-m-d H:i:s');
                ?>
            </div>
            
            <div class="form-actions" style="margin-top: 20px;">
                <button class="btn btn-outline" onclick="location.reload()">
                    <i class="fa-solid fa-rotate"></i> Refresh Data
                </button>
                <?php if ($conn->connect_error): ?>
                <a href="db_connect.php" class="btn btn-primary">
                    <i class="fa-solid fa-plug"></i> Reconnect DB
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>

  <script>
    setTimeout(() => {
        location.reload();
    }, 30000);
  </script>

</body>
</html>
