<?php
/**
 * Admin Dashboard Page
 * Displays adm            if ($stmt->execute()) {
                $success_message = "Request #$request_id status updated to: " . ucwords($new_status);
            } else {
                $error_message = "Failed to update request status.";
            }board with request management functionality
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

$success_message = '';
$error_message = '';

// Handle request status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $conn = getDatabaseConnection();
    
    if ($_POST['action'] === 'update_request_status' && isset($_POST['request_id']) && isset($_POST['status'])) {
        $request_id = intval($_POST['request_id']);
        $new_status = $_POST['status'];
        $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
        
        // Validate status
        if (in_array($new_status, ['pending', 'accepted', 'collected', 'rejected', 'cancelled'])) {
            if ($new_status === 'collected') {
            // For collected status, delete the record entirely
            $stmt = $conn->prepare("DELETE FROM pending_requests WHERE request_id = ?");
            $stmt->bind_param("i", $request_id);
            
            if ($stmt->execute()) {
                $success_message = "Request #$request_id has been collected and record deleted successfully!";
            } else {
                $error_message = "Failed to delete collected request.";
            }
        } else {
            // Normal status update for all other statuses (pending, accepted, rejected)
            $stmt = $conn->prepare("UPDATE pending_requests SET status = ?, admin_notes = ?, handled_by_admin = ?, updated_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("ssii", $new_status, $admin_notes, $_SESSION['user_id'], $request_id);
            
            if ($stmt->execute()) {
                $success_message = "Request #$request_id status updated to '$new_status' successfully!";
            } else {
                $error_message = "Failed to update request status.";
            }
        }
            $stmt->close();
        } else {
            $error_message = "Invalid status value.";
        }
    }
    
    $conn->close();
}

// Handle delete request action
if (isset($_POST['action']) && $_POST['action'] === 'delete_request') {
    $request_id = intval($_POST['request_id']);
    
    if ($request_id > 0) {
        $conn = getDatabaseConnection();
        
        // First, check if the request exists and is rejected
        $check_query = "SELECT status FROM pending_requests WHERE request_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $request = $check_result->fetch_assoc();
            
            if ($request['status'] === 'rejected') {
                // Delete the rejected request
                $delete_query = "DELETE FROM pending_requests WHERE request_id = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("i", $request_id);
                
                if ($delete_stmt->execute()) {
                    $success_message = "Rejected request deleted successfully.";
                } else {
                    $error_message = "Failed to delete rejected request.";
                }
                $delete_stmt->close();
            } else {
                $error_message = "Only rejected requests can be deleted.";
            }
        } else {
            $error_message = "Request not found.";
        }
        
        $check_stmt->close();
        $conn->close();
    } else {
        $error_message = "Invalid request ID.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$district_filter = isset($_GET['district']) ? $_GET['district'] : '';
$pending_limit = isset($_GET['show_all_pending']) ? 1000 : 10; // Show 10 by default, 1000 when "View More" is clicked

// Get all requests with user information
$conn = getDatabaseConnection();

// Build the WHERE clause based on filters
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($status_filter)) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($district_filter)) {
    $where_conditions[] = "u.district = ?";
    $params[] = $district_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Build WHERE clause for pending requests (pending and accepted)
$pending_where_conditions = $where_conditions;
$pending_where_conditions[] = "pr.status IN ('pending', 'accepted')";
$pending_where_clause = implode(" AND ", $pending_where_conditions);

// Get pending requests with limit
$pending_requests_query = "
    SELECT pr.*, u.first_name, u.last_name, u.email, u.phone, u.district, u.nearest_town
    FROM pending_requests pr 
    LEFT JOIN users u ON pr.user_id = u.user_id 
    WHERE $pending_where_clause 
    ORDER BY pr.created_at DESC
    LIMIT $pending_limit
";

$stmt = $conn->prepare($pending_requests_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$pending_requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build WHERE clause for rejected requests
$rejected_where_conditions = $where_conditions;
$rejected_where_conditions[] = "pr.status = 'rejected'";
$rejected_where_clause = implode(" AND ", $rejected_where_conditions);

// Get rejected requests
$rejected_requests_query = "
    SELECT pr.*, u.first_name, u.last_name, u.email, u.phone, u.district, u.nearest_town
    FROM pending_requests pr 
    LEFT JOIN users u ON pr.user_id = u.user_id 
    WHERE $rejected_where_clause
    ORDER BY pr.updated_at DESC
";

$stmt = $conn->prepare($rejected_requests_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rejected_requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get count of total pending requests for "View More" button
$total_pending_count = 0;
$count_query = "SELECT COUNT(*) as total FROM pending_requests pr LEFT JOIN users u ON pr.user_id = u.user_id WHERE $pending_where_clause";
if (!empty($params)) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $count_result = $result->fetch_assoc();
    $total_pending_count = $count_result['total'];
    $stmt->close();
} else {
    $result = $conn->query($count_query);
    $count_result = $result->fetch_assoc();
    $total_pending_count = $count_result['total'];
}

// Get all districts for filter dropdown
$districts_query = "SELECT DISTINCT district FROM users WHERE district IS NOT NULL ORDER BY district";
$result = $conn->query($districts_query);
$districts = $result->fetch_all(MYSQLI_ASSOC);

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'collected' THEN 1 ELSE 0 END) as collected_requests,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_requests
    FROM pending_requests
";
$result = $conn->query($stats_query);
$stats = $result->fetch_assoc();

$conn->close();

// Get admin information from session
$adminName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TrashSmart</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="font-poppins bg-gray-50">
    
    <!-- Header Section -->
    <header class="fixed top-0 left-0 right-0 bg-white shadow-md z-50">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center">
                <img src="images/trash-smart-logo.jpg" alt="TrashSmart Logo" class="h-10 w-auto">
                <span class="ml-3 text-2xl font-bold text-gray-800">Admin Panel</span>
            </div>
            
            <!-- Navigation Menu -->
            <div class="hidden md:flex space-x-6">
                <a href="admin-dashboard.php" class="nav-link text-lg text-green-600 font-semibold">Dashboard</a>
                <a href="admin-management.php" class="nav-link text-lg text-gray-700 hover:text-green-600 transition-colors">User Management</a>
                <a href="company-settings.php" class="nav-link text-lg text-gray-700 hover:text-green-600 transition-colors">Company Settings</a>
            </div>
            
            <!-- Admin Info and Logout -->
            <div class="flex items-center space-x-4">
                <span class="text-lg text-gray-700">Welcome, <?php echo htmlspecialchars($adminName); ?></span>
                <a href="../../backend/logout.php" class="text-lg text-red-600 hover:text-red-700 transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="pt-24 pb-12 min-h-screen">
        <div class="container mx-auto px-6">
            
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
            <div class="bg-white rounded-2xl shadow-md p-6 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 mb-2">Admin Dashboard</h1>
                        <p class="text-lg text-gray-600">Manage waste collection requests and system overview</p>
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-tachometer-alt text-6xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 text-center">
                    <i class="fas fa-list text-3xl text-blue-600 mb-3"></i>
                    <h3 class="text-xl font-semibold text-gray-800">Total Requests</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $stats['total_requests']; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6 text-center">
                    <i class="fas fa-clock text-3xl text-yellow-600 mb-3"></i>
                    <h3 class="text-xl font-semibold text-gray-800">Pending</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['pending_requests']; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6 text-center">
                    <i class="fas fa-check-circle text-3xl text-green-600 mb-3"></i>
                    <h3 class="text-xl font-semibold text-gray-800">Collected</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo $stats['collected_requests']; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6 text-center">
                    <i class="fas fa-times-circle text-3xl text-red-600 mb-3"></i>
                    <h3 class="text-xl font-semibold text-gray-800">Rejected</h3>
                    <p class="text-3xl font-bold text-red-600"><?php echo $stats['rejected_requests']; ?></p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-filter text-green-600 mr-2"></i>Filter Requests
                </h3>
                
                <form method="GET" class="grid md:grid-cols-3 gap-4">
                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="status" name="status" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="collected" <?php echo $status_filter === 'collected' ? 'selected' : ''; ?>>Collected</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="admin-dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-refresh mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Pending Requests Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-clock text-yellow-600 mr-2"></i>
                        Pending Requests
                        <span class="text-sm text-gray-500 ml-2">(<?php echo count($pending_requests); ?> records)</span>
                        <?php if ($total_pending_count > count($pending_requests)): ?>
                            <span class="text-sm text-blue-500 ml-2">- Showing <?php echo count($pending_requests); ?> of <?php echo $total_pending_count; ?></span>
                        <?php endif; ?>
                    </h3>
                </div>
                
                <?php if (empty($pending_requests)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No pending requests found</h4>
                        <p class="text-gray-500">No pending requests match your filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Citizen</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pickup Info</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pending_requests as $index => $request): ?>
                                    <tr class="hover:bg-gray-50">
                                        <!-- Request Number -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">#<?php echo $request['request_id']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></div>
                                        </td>
                                        
                                        <!-- Citizen Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['email']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['phone']); ?></div>
                                        </td>
                                        
                                        <!-- Request Details -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <strong>Type:</strong> <?php echo ucfirst($request['waste_type']); ?>
                                            </div>
                                            <?php if ($request['weight_category']): ?>
                                                <div class="text-xs text-gray-500">
                                                    <strong>Weight:</strong> <?php echo htmlspecialchars($request['weight_category']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-xs text-gray-500">
                                                <strong>Address:</strong> <?php echo htmlspecialchars(substr($request['pickup_address'], 0, 50)) . (strlen($request['pickup_address']) > 50 ? '...' : ''); ?>
                                            </div>
                                        </td>
                                        
                                        <!-- Pickup Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($request['preferred_pickup_date'])); ?>
                                            </div>
                                            <?php if ($request['pickup_time']): ?>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['pickup_time']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Current Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_class = '';
                                            switch ($request['status']) {
                                                case 'pending':
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'accepted':
                                                    $status_class = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'collected':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Action Buttons -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex flex-col space-y-1">
                                                <!-- Accept Button -->
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="update_request_status">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="status" value="accepted">
                                                    <button type="submit" <?php echo $request['status'] === 'accepted' || $request['status'] === 'collected' ? 'disabled' : ''; ?>
                                                            class="w-full px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors <?php echo $request['status'] === 'accepted' || $request['status'] === 'collected' ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                                        <i class="fas fa-check mr-1"></i>Accept
                                                    </button>
                                                </form>
                                                
                                                <!-- Collect Button - Only available if status is 'accepted' -->
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="update_request_status">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="status" value="collected">
                                                    <button type="submit" <?php echo $request['status'] !== 'accepted' ? 'disabled' : ''; ?>
                                                            onclick="return confirm('Are you sure this request has been collected? This will delete the record permanently.')"
                                                            class="w-full px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition-colors <?php echo $request['status'] !== 'accepted' ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                                        <i class="fas fa-truck mr-1"></i>Collect
                                                    </button>
                                                </form>
                                                
                                                <!-- Reject Button -->
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="update_request_status">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" <?php echo $request['status'] === 'rejected' || $request['status'] === 'collected' ? 'disabled' : ''; ?>
                                                            onclick="return confirm('Are you sure you want to reject this request?')"
                                                            class="w-full px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition-colors <?php echo $request['status'] === 'rejected' || $request['status'] === 'collected' ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                                        <i class="fas fa-times mr-1"></i>Reject
                                                    </button>
                                                </form>
                                                
                                                <!-- Delete Button - Only available for rejected requests -->
                                                <?php if ($request['status'] === 'rejected'): ?>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="delete_request">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <button type="submit"
                                                            onclick="return confirm('Are you sure you want to permanently delete this rejected request? This action cannot be undone.')"
                                                            class="w-full px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
                                                        <i class="fas fa-trash mr-1"></i>Delete
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- View More Button for Pending Requests -->
                    <?php if ($total_pending_count > count($pending_requests)): ?>
                        <div class="p-6 border-t border-gray-200 text-center">
                            <a href="admin-dashboard.php?show_all_pending=1" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-eye mr-2"></i>
                                View More (<?php echo $total_pending_count - count($pending_requests); ?> remaining)
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Rejected Requests Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-times-circle text-red-600 mr-2"></i>
                        Rejected Requests
                        <span class="text-sm text-gray-500 ml-2">(<?php echo count($rejected_requests); ?> records)</span>
                    </h3>
                </div>
                
                <?php if (empty($rejected_requests)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No rejected requests found</h4>
                        <p class="text-gray-500">No requests have been rejected yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Citizen</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pickup Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($rejected_requests as $index => $request): ?>
                                    <tr class="hover:bg-gray-50">
                                        <!-- Request Number -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">#<?php echo $request['request_id']; ?></div>
                                        </td>
                                        
                                        <!-- Citizen Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['email']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['phone']); ?></div>
                                        </td>
                                        
                                        <!-- Request Details -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <strong>Type:</strong> <?php echo ucfirst($request['waste_type']); ?>
                                            </div>
                                            <?php if ($request['weight_category']): ?>
                                                <div class="text-xs text-gray-500">
                                                    <strong>Weight:</strong> <?php echo htmlspecialchars($request['weight_category']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-xs text-gray-500">
                                                <strong>Address:</strong> <?php echo htmlspecialchars(substr($request['pickup_address'], 0, 50)) . (strlen($request['pickup_address']) > 50 ? '...' : ''); ?>
                                            </div>
                                            <?php if ($request['admin_notes']): ?>
                                                <div class="text-xs text-red-600 mt-1">
                                                    <strong>Reason:</strong> <?php echo htmlspecialchars($request['admin_notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Original Pickup Date -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($request['preferred_pickup_date'])); ?>
                                            </div>
                                        </td>
                                        
                                        <!-- Rejected Date -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($request['updated_at'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('g:i A', strtotime($request['updated_at'])); ?>
                                            </div>
                                        </td>
                                        
                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                Rejected
                                            </span>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="delete_request">
                                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                <button type="submit"
                                                        onclick="return confirm('Are you sure you want to permanently delete this rejected request? This action cannot be undone.')"
                                                        class="bg-red-600 text-white px-3 py-1.5 rounded-md hover:bg-red-700 transition-colors text-xs font-medium">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </form>
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

        // Confirm status updates
        document.addEventListener('submit', function(e) {
            if (e.target.querySelector('input[name="action"][value="update_request_status"]')) {
                const status = e.target.querySelector('input[name="status"]').value;
                if (status === 'rejected') {
                    // Already handled by onclick in the button
                    return true;
                } else if (status === 'collected') {
                    if (!confirm('Are you sure this waste has been collected?')) {
                        e.preventDefault();
                        return false;
                    }
                } else if (status === 'accepted') {
                    if (!confirm('Are you sure you want to accept this request?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
    </script>
</body>
</html>
