<?php
/**
 * Admin Management Page
 * Manages registered citizens and their data
 */

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Database connection
function getDatabaseConnection() {
    $conn = new mysqli('localhost', 'root', '', 'trashsmart');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Helper: check if a column exists in a table (used for replied status compatibility)
function columnExists($conn, $table, $column) {
    // Escape identifiers (table/column) for safety
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $result = $conn->query($sql);
    $exists = $result && $result->num_rows > 0;
    if ($result) $result->free();
    return $exists;
}

$success_message = '';
$error_message = '';

// Handle status update actions and contact message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $conn = getDatabaseConnection();
    
    if ($_POST['action'] === 'update_status' && isset($_POST['user_id']) && isset($_POST['status'])) {
        $user_id = intval($_POST['user_id']);
        $action_value = $_POST['status'];

        if ($action_value === 'active' || $action_value === 'suspended') {
            // Update status only for citizens
            $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ? AND user_type = 'citizen'");
            $stmt->bind_param("si", $action_value, $user_id);
            if ($stmt->execute()) {
                $success_message = "User status updated successfully!";
            } else {
                $error_message = "Failed to update user status.";
            }
            $stmt->close();
        } elseif ($action_value === 'make_admin') {
            // Promote citizen to admin
            $stmt = $conn->prepare("UPDATE users SET user_type = 'admin', updated_at = NOW() WHERE user_id = ? AND user_type = 'citizen'");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_message = "User promoted to admin successfully!";
                } else {
                    $error_message = "No changes made. User might already be an admin.";
                }
            } else {
                $error_message = "Failed to promote user to admin.";
            }
            $stmt->close();
        } else {
            $error_message = "Invalid action selected.";
        }
    }
    
    if ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        
        // Delete user (this will also delete related records due to foreign keys)
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND user_type = 'citizen'");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Failed to delete user.";
        }
        $stmt->close();
    }
    
    // Mark contact message as replied
    if ($_POST['action'] === 'mark_contact_replied' && isset($_POST['message_id'])) {
        $message_id = intval($_POST['message_id']);
        // Detect id field (id or message_id)
        $idField = 'id';
        $idCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'id'");
        if (!$idCheck || $idCheck->num_rows === 0) {
            $idField = 'message_id';
        }
        if ($idCheck) $idCheck->free();
        // Prefer boolean replied flag if exists, else fallback to status text
        if (columnExists($conn, 'contact_messages', 'replied')) {
            $stmt = $conn->prepare("UPDATE contact_messages SET replied = 1, replied_at = NOW() WHERE $idField = ?");
            $stmt->bind_param("i", $message_id);
        } elseif (columnExists($conn, 'contact_messages', 'status')) {
            $status = 'replied';
            // replied_at column may not exist; update only status if replied_at missing
            if (columnExists($conn, 'contact_messages', 'replied_at')) {
                $stmt = $conn->prepare("UPDATE contact_messages SET status = ?, replied_at = NOW() WHERE $idField = ?");
                $stmt->bind_param("si", $status, $message_id);
            } else {
                $stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE $idField = ?");
                $stmt->bind_param("si", $status, $message_id);
            }
        } else {
            // Add status column if missing, then update
            $alter = $conn->query("ALTER TABLE contact_messages ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'not_replied'");
            if ($alter) {
                $status = 'replied';
                $stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE $idField = ?");
                $stmt->bind_param("si", $status, $message_id);
            } else {
                $stmt = null;
                $error_message = "Could not add status column to contact messages table.";
            }
        }
        if ($stmt) {
            if ($stmt->execute()) {
                $success_message = "Message marked as replied.";
            } else {
                $error_message = "Failed to mark message as replied.";
            }
            $stmt->close();
        }
    }
    
    // Delete contact message
    if ($_POST['action'] === 'delete_contact_message' && isset($_POST['message_id'])) {
        $message_id = intval($_POST['message_id']);
        // Detect id field (id or message_id)
        $idField = 'id';
        $idCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'id'");
        if (!$idCheck || $idCheck->num_rows === 0) {
            $idField = 'message_id';
        }
        if ($idCheck) $idCheck->free();
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE $idField = ?");
        $stmt->bind_param("i", $message_id);
        if ($stmt->execute()) {
            $success_message = "Message deleted successfully.";
        } else {
            $error_message = "Failed to delete message.";
        }
        $stmt->close();
    }
    
    $conn->close();
}

// Get search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : '';
$district_filter = isset($_GET['district']) && $_GET['district'] !== '' ? $_GET['district'] : '';

// Get all registered citizens with their request counts
$conn = getDatabaseConnection();

// Build the WHERE clause based on filters
$where_conditions = ["u.user_type = 'citizen'"];
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($district_filter)) {
    $where_conditions[] = "u.district = ?";
    $params[] = $district_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get citizens with request counts
$citizens_query = "
    SELECT u.*, 
           COUNT(pr.request_id) as total_requests,
           SUM(CASE WHEN pr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
           SUM(CASE WHEN pr.status = 'collected' THEN 1 ELSE 0 END) as collected_requests,
           SUM(CASE WHEN pr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM users u 
    LEFT JOIN pending_requests pr ON u.user_id = pr.user_id 
    WHERE $where_clause 
    GROUP BY u.user_id 
    ORDER BY u.created_at DESC
";

$stmt = $conn->prepare($citizens_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$citizens = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all districts for filter dropdown
$districts_query = "SELECT DISTINCT u.district FROM users u WHERE u.user_type = 'citizen' AND u.district IS NOT NULL AND u.district != '' ORDER BY u.district";
$result = $conn->query($districts_query);
$districts = $result->fetch_all(MYSQLI_ASSOC);

// Get summary statistics - should reflect current filters
$stats_query = "
    SELECT 
        COUNT(*) as total_citizens,
        SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active_citizens,
        SUM(CASE WHEN u.status = 'inactive' THEN 1 ELSE 0 END) as inactive_citizens,
        SUM(CASE WHEN u.status = 'suspended' THEN 1 ELSE 0 END) as suspended_citizens
    FROM users u
    WHERE $where_clause
";

if (!empty($params)) {
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
} else {
    $result = $conn->query($stats_query);
    $stats = $result->fetch_assoc();
}

$conn->close();

// Get admin information from session
$adminName = $_SESSION['user_name'] ?? 'Admin';

// Fetch contact messages for admin view
$conn = getDatabaseConnection();
$messages = [];
$idField = 'id';
$query = "SELECT * FROM contact_messages ORDER BY created_at DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    // Try to detect id field gracefully
    if (!empty($messages)) {
        if (array_key_exists('id', $messages[0])) { $idField = 'id'; }
        elseif (array_key_exists('message_id', $messages[0])) { $idField = 'message_id'; }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - TrashSmart</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="font-poppins bg-gray-50 text-base md:text-lg">
    
    <!-- Header Section -->
    <header class="fixed top-0 left-0 right-0 bg-white shadow-md z-50">
    <nav class="container mx-auto px-8 lg:px-32 py-4 flex justify-between items-center">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center">
                    <img src="images/trash-smart-logo.jpg" alt="TrashSmart Logo" class="h-10 w-auto">
                    <span class="ml-3 text-2xl font-bold text-gray-800">Admin Panel</span>
                </div>
                <div class="hidden md:flex space-x-6">
                    <a href="admin-dashboard.php" class="nav-link text-base text-green-600 font-semibold">Dashboard</a>
                    <a href="admin-management.php" class="nav-link text-base text-gray-700 hover:text-green-600 transition-colors">User Management</a>
                    <a href="company-settings.php" class="nav-link text-base text-gray-700 hover:text-green-600 transition-colors">Company Settings</a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-base text-gray-700">Welcome, <?php echo htmlspecialchars($adminName); ?></span>
                    <a href="../../backend/logout.php" class="text-base text-amber-900 hover:text-amber-800 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="pt-36 pb-12 min-h-screen">
    <div class="container mx-auto px-8 lg:px-32">
            
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div id="successAlert" class="bg-green-50 border-l-4 border-green-400 text-green-800 px-6 py-4 rounded-lg mb-6 shadow-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
                        <div class="flex-1">
                            <h4 class="font-semibold text-lg">Success!</h4>
                            <p class="mt-1"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                        <button onclick="closeAlert('successAlert')" class="text-green-600 hover:text-green-800 ml-4">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div id="errorAlert" class="bg-red-50 border-l-4 border-red-400 text-red-800 px-6 py-4 rounded-lg mb-6 shadow-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-3"></i>
                        <div class="flex-1">
                            <h4 class="font-semibold text-lg">Error!</h4>
                            <p class="mt-1"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                        <button onclick="closeAlert('errorAlert')" class="text-red-600 hover:text-red-800 ml-4">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="bg-white rounded-2xl shadow-md p-6 mb-20">
                <div class="flex items-center justify-between">
                    <div>

                        <h1 class="text-5xl font-extrabold text-black mb-4">Citizen Management</h1>
                        <p class="text-xl text-gray-600">Manage registered citizens and their account information</p>

                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-users text-6xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->

            <div class="grid md:grid-cols-4 gap-8 mb-20">
                <div class="bg-green-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between mx-auto">
                    <div class="w-16 h-16 bg-blue-600 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Total Citizens</h3>
                    <p class="text-3xl font-bold text-blue-600 text-center"><?php echo $stats['total_citizens']; ?></p>
                </div>
                <div class="bg-green-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between mx-auto">
                    <div class="w-16 h-16 bg-green-600 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-user-check text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Active</h3>
                    <p class="text-3xl font-bold text-green-600 text-center"><?php echo $stats['active_citizens']; ?></p>
                </div>
                <div class="bg-yellow-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between mx-auto">
                    <div class="w-16 h-16 bg-yellow-600 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-user-times text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Inactive</h3>
                    <p class="text-3xl font-bold text-yellow-600 text-center"><?php echo $stats['inactive_citizens']; ?></p>
                </div>
                <div class="bg-amber-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between mx-auto">
                    <div class="w-16 h-16 bg-amber-900 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-user-slash text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Suspended</h3>
                    <p class="text-3xl font-bold text-amber-900 text-center"><?php echo $stats['suspended_citizens']; ?></p>

                </div>
            </div>

            <!-- Search and Filter Section -->

            <div class="bg-white rounded-xl shadow-md p-6 mb-20">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">

                    <i class="fas fa-search text-green-600 mr-2"></i>Search & Filter
                </h3>
                
                <form method="GET" class="grid md:grid-cols-4 gap-4">
                    <!-- Search Input -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Name, email, or phone..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="status" name="status" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    
                    <!-- District Filter -->
                    <div>
                        <label for="district" class="block text-sm font-medium text-gray-700 mb-2">District</label>
                        <select id="district" name="district" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="">All Districts</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo htmlspecialchars($district['district']); ?>" 
                                        <?php echo $district_filter === $district['district'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($district['district']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <a href="admin-management.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-refresh mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Citizens Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-2xl font-semibold text-gray-800">
                        <i class="fas fa-table text-green-600 mr-2"></i>Registered Citizens
                        <span class="text-lg text-gray-500 ml-2">(<?php echo count($citizens); ?> records)</span>
                    </h3>
                </div>
                
                <?php if (empty($citizens)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No citizens found</h4>
                        <p class="text-gray-500">No citizens match your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Requests</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Joined</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($citizens as $citizen): ?>
                                    <tr class="hover:bg-gray-50">
                                        <!-- User Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                                        <i class="fas fa-user text-green-600"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-base font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($citizen['first_name'] . ' ' . $citizen['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">ID: <?php echo $citizen['user_id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Contact Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-base text-gray-900"><?php echo htmlspecialchars($citizen['email']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($citizen['phone']); ?></div>
                                        </td>
                                        
                                        <!-- Location -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-base text-gray-900"><?php echo htmlspecialchars($citizen['district']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($citizen['nearest_town']); ?></div>
                                        </td>
                                        
                                        <!-- Request Statistics -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-base text-gray-900">Total: <?php echo $citizen['total_requests']; ?></div>
                                            <div class="text-sm text-gray-500">
                                                P:<?php echo $citizen['pending_requests']; ?> | 
                                                C:<?php echo $citizen['collected_requests']; ?> | 
                                                R:<?php echo $citizen['rejected_requests']; ?>
                                            </div>
                                        </td>
                                        
                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_class = '';
                                            switch ($citizen['status']) {
                                                case 'active':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'inactive':
                                                    $status_class = 'bg-gray-100 text-gray-800';
                                                    break;
                                                case 'suspended':
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                <?php echo ucfirst($citizen['status']); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Join Date -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('M j, Y', strtotime($citizen['created_at'])); ?>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <!-- Status/Role Action Dropdown -->
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $citizen['user_id']; ?>">
                                                    <select name="status" onchange="this.form.submit()"
                                                            class="text-xs px-2 py-1 border border-gray-300 rounded">
                                                        <option value="">Choose Action</option>
                                                        <option value="active" <?php echo $citizen['status'] === 'active' ? 'disabled' : ''; ?>>Active</option>
                                                        <option value="suspended" <?php echo $citizen['status'] === 'suspended' ? 'disabled' : ''; ?>>Suspend</option>
                                                        <option value="make_admin">Make Admin</option>
                                                    </select>
                                                </form>
                                                
                                                <!-- Delete Button -->
                                                <form method="POST" class="inline-block" 
                                                      onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $citizen['user_id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contact Messages Section -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden mb-12">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-2xl font-semibold text-gray-800">
                        <i class="fas fa-envelope text-green-600 mr-2"></i>Contact Messages
                        <span class="text-lg text-gray-500 ml-2">(<?php echo count($messages); ?> records)</span>
                    </h3>
                </div>
                <?php if (empty($messages)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No messages found</h4>
                        <p class="text-gray-500">Messages submitted via Contact Us will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Sender</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Message</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Submitted</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($messages as $m): ?>
                                    <?php
                                        $idVal = $m[$idField];
                                        $name = htmlspecialchars($m['name'] ?? '');
                                        $email = htmlspecialchars($m['email'] ?? '');
                                        $messageText = htmlspecialchars($m['message'] ?? '');
                                        $createdAt = isset($m['created_at']) ? date('M j, Y g:i A', strtotime($m['created_at'])) : '';
                                        $replied = false;
                                        if (array_key_exists('replied', $m)) { $replied = (bool)$m['replied']; }
                                        elseif (array_key_exists('status', $m)) { $replied = strtolower((string)$m['status']) === 'replied'; }
                                        $badgeClass = $replied ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                        $badgeText = $replied ? 'Replied' : 'Not Replied';
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#<?php echo $idVal; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-base font-medium text-gray-900"><?php echo $name; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $email; ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-700 max-w-xl break-words overflow-hidden" title="<?php echo $messageText; ?>"><?php echo $messageText; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $createdAt; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <?php if (!$replied): ?>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="mark_contact_replied">
                                                    <input type="hidden" name="message_id" value="<?php echo $idVal; ?>">
                                                    <button type="submit" class="flex items-center px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded-md shadow-sm transition text-base font-semibold" title="Mark as Replied">
                                                        <i class="fas fa-check-circle text-xl mr-2"></i>
                                                        <span>Mark as Replied</span>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <form method="POST" class="inline-block" onsubmit="return confirm('Delete this message? This cannot be undone.')">
                                                    <input type="hidden" name="action" value="delete_contact_message">
                                                    <input type="hidden" name="message_id" value="<?php echo $idVal; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800" title="Delete Message">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-6">
        <div class="container mx-auto px-6">
            <div class="text-center">
                <p class="text-gray-700 text-sm">&copy; 2025 TrashSmart Admin Panel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Custom JavaScript -->
    <script src="js/main.js"></script>
    
    <script>
        // Alert handling functions
        function closeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.transition = 'opacity 0.3s, transform 0.3s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            }
        }
        
        // Auto-hide success alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const successAlert = document.getElementById('successAlert');
                if (successAlert) {
                    closeAlert('successAlert');
                }
            }, 5000);
            
            // Also auto-hide error alerts after 7 seconds
            setTimeout(function() {
                const errorAlert = document.getElementById('errorAlert');
                if (errorAlert) {
                    closeAlert('errorAlert');
                }
            }, 7000);
        });

        // Confirm before status changes
        document.addEventListener('change', function(e) {
            if (e.target.name === 'status' && e.target.value) {
                if (!confirm('Are you sure you want to change this user\'s status to ' + e.target.value + '?')) {
                    e.target.value = '';
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
