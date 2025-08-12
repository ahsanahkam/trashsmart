<?php
/**
 * Citizen Profile Page
 * Displays citizen dashboard with session management and request creation
 */

session_start();

// Check if user is logged in and is a citizen
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'citizen') {
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

// Check for session success message (from redirect after form submission)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it so it doesn't show again
}

// Check for session error message (from redirect after profile actions)
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Help: default profile image shown until user uploads one
$defaultProfileImageRel = 'images/profile_picture.jpg'; // change or replace this file to change default
$defaultProfileImageAbs = __DIR__ . '/images/profile_picture.jpg';

// Optional guidance via query param: ?guide=profile_photo
if (isset($_GET['guide']) && $_GET['guide'] === 'profile_photo') {
    $success_message = 'Tip: Click "Update Profile" and choose a file under Profile Photo, then Save. Until you upload, the default image at ' . $defaultProfileImageRel . ' is shown.';
}

// Handle profile actions: update, delete, and remove photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    // Collect inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $nearest_town = trim($_POST['nearest_town'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');

    // Basic validation
    if ($first_name === '' || $last_name === '' || $email === '') {
        $_SESSION['success_message'] = '';
        $_SESSION['error_message'] = 'First name, last name, and email are required.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['success_message'] = '';
        $_SESSION['error_message'] = 'Please enter a valid email address.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $conn = getDatabaseConnection();

    // Ensure email is unique to this user
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
    $stmt->bind_param('si', $email, $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $stmt->close();
        $conn->close();
        $_SESSION['success_message'] = '';
        $_SESSION['error_message'] = 'This email is already in use by another account.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $stmt->close();

    // Update profile details
    $update_sql = "UPDATE users 
                   SET first_name = ?, last_name = ?, email = ?, phone = ?, district = ?, nearest_town = ?, address = ?, date_of_birth = ?
                   WHERE user_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('ssssssssi', $first_name, $last_name, $email, $phone, $district, $nearest_town, $address, $date_of_birth, $_SESSION['user_id']);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        $conn->close();
        $_SESSION['success_message'] = '';
        $_SESSION['error_message'] = 'Failed to update profile. Please try again.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Handle profile photo upload (optional)
    if (isset($_FILES['profile_photo']) && is_array($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileError = $_FILES['profile_photo']['error'];
        if ($fileError === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['profile_photo']['tmp_name'];
            $fileName = $_FILES['profile_photo']['name'];
            $fileSize = $_FILES['profile_photo']['size'];

            // Validate size (2MB max)
            if ($fileSize > 2 * 1024 * 1024) {
                // Too large
                $conn->close();
                $_SESSION['success_message'] = '';
                $_SESSION['error_message'] = 'Profile photo must be 2MB or smaller.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            // Validate image type
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts, true)) {
                $conn->close();
                $_SESSION['success_message'] = '';
                $_SESSION['error_message'] = 'Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            // Extra validation: ensure it's an image
            $imgInfo = @getimagesize($tmpPath);
            if ($imgInfo === false) {
                $conn->close();
                $_SESSION['success_message'] = '';
                $_SESSION['error_message'] = 'Uploaded file is not a valid image.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            $uploadDirRel = 'uploads/profile_photos';
            $uploadDirAbs = __DIR__ . '/uploads/profile_photos';
            if (!is_dir($uploadDirAbs)) {
                @mkdir($uploadDirAbs, 0777, true);
            }
            // Remove any existing user photo
            foreach (glob($uploadDirAbs . '/user_' . $_SESSION['user_id'] . '.*') as $oldFile) {
                @unlink($oldFile);
            }
            $targetAbs = $uploadDirAbs . '/user_' . $_SESSION['user_id'] . '.' . $ext;
            if (!@move_uploaded_file($tmpPath, $targetAbs)) {
                $conn->close();
                $_SESSION['success_message'] = '';
                $_SESSION['error_message'] = 'Failed to save profile photo. Please try again.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        } else {
            // Handle common upload errors
            $errorMap = [
                UPLOAD_ERR_INI_SIZE => 'Uploaded image exceeds server size limit.',
                UPLOAD_ERR_FORM_SIZE => 'Uploaded image exceeds form size limit.',
                UPLOAD_ERR_PARTIAL => 'Image was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder for uploads.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write image to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            $msg = $errorMap[$fileError] ?? 'Unexpected upload error.';
            $conn->close();
            $_SESSION['success_message'] = '';
            $_SESSION['error_message'] = $msg;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Update session display info
    $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
    $_SESSION['user_email'] = $email;

    $conn->close();
    $_SESSION['error_message'] = '';
    $_SESSION['success_message'] = 'Profile updated successfully!';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_profile_photo') {
    // Delete any existing profile photo files for the user
    $uploadDirAbs = __DIR__ . '/uploads/profile_photos';
    $removed = false;
    if (is_dir($uploadDirAbs)) {
        foreach (glob($uploadDirAbs . '/user_' . $_SESSION['user_id'] . '.*') as $oldFile) {
            if (@unlink($oldFile)) { $removed = true; }
        }
    }
    $_SESSION['success_message'] = $removed ? 'Profile photo removed.' : 'No profile photo to remove.';
    $_SESSION['error_message'] = '';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_profile') {
    $userId = (int)$_SESSION['user_id'];
    $conn = getDatabaseConnection();

    // Delete dependent records first to avoid FK issues
    $stmt = $conn->prepare('DELETE FROM pending_requests WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    // Delete the user account
    $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    $conn->close();

    // Remove profile photo files
    $uploadDirAbs = __DIR__ . '/uploads/profile_photos';
    if (is_dir($uploadDirAbs)) {
        foreach (glob($uploadDirAbs . '/user_' . $userId . '.*') as $oldFile) {
            @unlink($oldFile);
        }
    }

    // Destroy session and redirect
    session_unset();
    session_destroy();
    header('Location: index.php?account_deleted=1');
    exit;
}

// Handle request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $waste_type = trim($_POST['waste_type']);
    $pickup_address = trim($_POST['pickup_address']);
    $pickup_date = $_POST['pickup_date'];
    $pickup_time = $_POST['pickup_time'];
    $special_instructions = trim($_POST['special_instructions']);
    $quantity_estimate = trim($_POST['quantity_estimate']);
    
    // Validation
    if (empty($waste_type) || empty($pickup_address) || empty($pickup_date) || empty($pickup_time)) {
        $error_message = "Please fill in all required fields.";
    } elseif (strtotime($pickup_date) <= time()) {
        $error_message = "Pickup date must be in the future.";
    } else {
        $conn = getDatabaseConnection();
        
        // Insert new request
        $stmt = $conn->prepare("INSERT INTO pending_requests (user_id, waste_type, pickup_address, preferred_pickup_date, pickup_time, special_instructions, weight_category, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("issssss", $_SESSION['user_id'], $waste_type, $pickup_address, $pickup_date, $pickup_time, $special_instructions, $quantity_estimate);
        
        if ($stmt->execute()) {
            $request_id = $conn->insert_id;
            $stmt->close();
            $conn->close();
            
            // Store success message in session and redirect to avoid form resubmission
            $_SESSION['success_message'] = "🎉 Request Submitted Successfully! Your waste collection request has been submitted with Request ID: #" . $request_id . ". You will be contacted within 24 hours.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error_message = "Failed to submit request. Please try again.";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_request') {
    $request_id = (int)$_POST['request_id'];
    
    $conn = getDatabaseConnection();
    
    // First check if the request belongs to the user and can be deleted (pending or rejected)
    $check_stmt = $conn->prepare("SELECT status FROM pending_requests WHERE request_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        if ($request['status'] === 'pending' || $request['status'] === 'rejected') {
            // Delete the request (both pending and rejected can be deleted)
            $delete_stmt = $conn->prepare("DELETE FROM pending_requests WHERE request_id = ? AND user_id = ?");
            $delete_stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Request #$request_id has been deleted successfully!";
            } else {
                $error_message = "Failed to delete request. Please try again.";
            }
            $delete_stmt->close();
        } else {
            $error_message = "Cannot delete request. Only pending and rejected requests can be deleted.";
        }
    } else {
        $error_message = "Request not found or access denied.";
    }
    
    $check_stmt->close();
    $conn->close();
    
    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_request') {
    $request_id = (int)$_POST['request_id'];
    $waste_type = trim($_POST['waste_type']);
    $pickup_address = trim($_POST['pickup_address']);
    $pickup_date = $_POST['pickup_date'];
    $pickup_time = $_POST['pickup_time'];
    $special_instructions = trim($_POST['special_instructions']);
    $quantity_estimate = trim($_POST['quantity_estimate']);
    
    // Validation
    if (empty($waste_type) || empty($pickup_address) || empty($pickup_date) || empty($pickup_time)) {
        $error_message = "Please fill in all required fields.";
    } elseif (strtotime($pickup_date) <= time()) {
        $error_message = "Pickup date must be in the future.";
    } else {
        $conn = getDatabaseConnection();
        
        // First check if the request belongs to the user and is still pending
        $check_stmt = $conn->prepare("SELECT status FROM pending_requests WHERE request_id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $request = $result->fetch_assoc();
            if ($request['status'] === 'pending') {
                // Update the request
                $update_stmt = $conn->prepare("UPDATE pending_requests SET waste_type = ?, pickup_address = ?, preferred_pickup_date = ?, pickup_time = ?, special_instructions = ?, weight_category = ?, updated_at = NOW() WHERE request_id = ? AND user_id = ?");
                $update_stmt->bind_param("ssssssii", $waste_type, $pickup_address, $pickup_date, $pickup_time, $special_instructions, $quantity_estimate, $request_id, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Request #$request_id has been updated successfully!";
                } else {
                    $error_message = "Failed to update request. Please try again.";
                }
                $update_stmt->close();
            } else {
                $error_message = "Cannot update request. Only pending requests can be updated.";
            }
        } else {
            $error_message = "Request not found or access denied.";
        }
        
        $check_stmt->close();
        $conn->close();
        
        // Redirect to avoid form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get user information from session
$userName = $_SESSION['user_name'] ?? 'Citizen';
$userEmail = $_SESSION['user_email'] ?? '';

// Get full user profile information
$conn = getDatabaseConnection();

$user_profile = null;
$profile_query = "SELECT first_name, last_name, email, phone, district, nearest_town, address, date_of_birth FROM users WHERE user_id = ?";
$stmt = $conn->prepare($profile_query);
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_profile = $result->fetch_assoc();
    $stmt->close();
} else {
    // Failed to prepare profile query
}
$conn->close();

// Resolve profile photo URL (filesystem based, no DB needed)
$uploadDirRel = 'uploads/profile_photos';
$uploadDirAbs = __DIR__ . '/uploads/profile_photos';
$profilePhotoUrl = 'images/profile_picture.jpg';
$allowedExtsView = ['jpg','jpeg','png','webp'];
foreach ($allowedExtsView as $e) {
    $candidate = $uploadDirAbs . '/user_' . $_SESSION['user_id'] . '.' . $e;
    if (file_exists($candidate)) {
        $profilePhotoUrl = $uploadDirRel . '/user_' . $_SESSION['user_id'] . '.' . $e . '?v=' . filemtime($candidate);
        break;
    }
}

// Get request statistics and recent requests
$conn = getDatabaseConnection();

// Get counts for different statuses
$pending_count = 0;
$accepted_count = 0;
$collected_count = 0;
// Get counts for different statuses from pending_requests table only
$pending_count = 0;
$accepted_count = 0;
$collected_count = 0; // Always 0 since collected records are deleted
$rejected_count = 0;

$stats_query = "SELECT status, COUNT(*) as count FROM pending_requests WHERE user_id = ? GROUP BY status";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    switch ($row['status']) {
        case 'pending':
            $pending_count = $row['count'];
            break;
        case 'accepted':
            $accepted_count = $row['count'];
            break;
        case 'rejected':
            $rejected_count = $row['count'];
            break;
    }
}
$stmt->close();

// Get recent requests (last 10)
$recent_requests = [];
$recent_query = "SELECT request_id, waste_type, pickup_address, preferred_pickup_date, pickup_time, status, created_at, updated_at 
                 FROM pending_requests 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT 10";
$stmt = $conn->prepare($recent_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent_requests[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Profile - TrashSmart</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="font-poppins bg-white text-base md:text-lg">
    
    <!-- Header Section -->
    <header class="fixed top-0 left-0 right-0 bg-white shadow-md z-50">
        <nav class="container mx-auto px-12 py-4 flex justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center">
                <img src="images/trash-smart-logo.jpg" alt="TrashSmart Logo" class="h-10 w-auto">
            </div>
            
            <!-- Navigation Menu -->
            <div class="hidden md:flex space-x-8">
                <a href="index.php" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Home</a>
                <a href="index.php#tips" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Tips</a>
                <a href="index.php#about" class="nav-link text-gray-700 hover:text-green-600 transition-colors">About Us</a>
                <a href="index.php#contact" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Contact Us</a>
            </div>
            
            <!-- User Info and Logout -->
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($userName); ?></span>
                <a href="../../backend/logout.php" class="text-brown-600 hover:text-brown-700 transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
            
            <!-- Mobile Menu Button -->
            <button id="mobileMenuBtn" class="md:hidden text-gray-700">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="pt-24 pb-12 bg-gray-50 min-h-screen">
        <div class="container mx-auto px-12">
            
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
            
            <!-- Welcome Section -->
            <div class="bg-white rounded-2xl shadow-md p-8 mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <h2 class="text-2xl md:text-4xl font-bold text-gray-800 mb-4">Welcome to Your Dashboard</h2>
                        <p class="text-base md:text-lg text-gray-700 mb-6">Hello <?php 
                            if ($user_profile && !empty($user_profile['first_name'])) {
                                echo htmlspecialchars($user_profile['first_name'] . ' ' . $user_profile['last_name']);
                            } else {
                                echo htmlspecialchars($userName);
                            }
                        ?>! Manage your waste collection requests here.</p>
                        
                        <!-- User Profile Information -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-2xl font-bold text-gray-800 mb-6">
                                <i class="fas fa-user text-green-600 mr-2"></i>Profile Information
                            </h3>
                            
                            <!-- Photo + Actions -->
                            <div class="flex items-center justify-between mb-6 gap-4 flex-col sm:flex-row">
                                <div class="flex items-center gap-4">
                                    <img src="<?php echo htmlspecialchars($profilePhotoUrl); ?>" alt="Profile Photo" class="w-20 h-20 rounded-full object-cover border border-gray-200">
                                    <div>
                                        <p class="text-sm text-gray-500">Profile Photo</p>
                                        <p class="text-xs text-gray-400">JPG/PNG/WEBP up to 2MB</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="openProfileModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                                        <i class="fas fa-user-edit mr-2"></i>Update Profile
                                    </button>
                                    <form method="POST" onsubmit="return confirmRemovePhoto();">
                                        <input type="hidden" name="action" value="remove_profile_photo">
                                        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm font-medium">
                                            <i class="fas fa-image mr-2"></i>Remove Photo
                                        </button>
                                    </form>
                                    <form method="POST" onsubmit="return confirmDeleteProfile();">
                                        <input type="hidden" name="action" value="delete_profile">
                                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
                                            <i class="fas fa-user-times mr-2"></i>Delete Account
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <?php if ($user_profile): ?>
                                    <div class="flex items-center text-lg">
                                        <i class="fas fa-user-circle text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Full Name</p>
                                            <p class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($user_profile['first_name'] . ' ' . $user_profile['last_name']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center text-lg">
                                        <i class="fas fa-envelope text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Email</p>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user_profile['email']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center text-lg">
                                        <i class="fas fa-phone text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Contact</p>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user_profile['phone']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center text-lg">
                                        <i class="fas fa-map-marker-alt text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">District</p>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user_profile['district']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-lg">
                                        <i class="fas fa-city text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Nearest Town</p>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user_profile['nearest_town'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-lg">
                                        <i class="fas fa-home text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Address</p>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user_profile['address'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="md:col-span-2 text-center text-gray-500 text-lg">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Profile information not available
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-clock text-4xl text-yellow-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Pending</h3>
                    <p class="text-3xl font-extrabold text-yellow-600"><?php echo $pending_count; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-thumbs-up text-4xl text-blue-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Accepted</h3>
                    <p class="text-3xl font-extrabold text-blue-600"><?php echo $accepted_count; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-check-circle text-4xl text-green-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Collected</h3>
                    <p class="text-3xl font-extrabold text-green-600"><?php echo $collected_count; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-times-circle text-4xl text-red-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rejected</h3>
                    <p class="text-3xl font-extrabold text-red-600"><?php echo $rejected_count; ?></p>
                </div>
            </div>

            <!-- Create New Request Form -->
            <div class="bg-white rounded-xl shadow-md p-10 mb-12">
                <h3 class="text-3xl font-extrabold text-gray-800 mb-8">
                    <i class="fas fa-plus-circle text-green-600 mr-2"></i>Create New Request
                </h3>
                
                <form method="POST" class="space-y-8">
                    <input type="hidden" name="action" value="create_request">
                    
                    <!-- Waste Type -->
                    <div>
                        <label for="waste_type" class="block text-lg font-semibold text-gray-700 mb-2">
                            <i class="fas fa-trash mr-2 text-green-600"></i>Waste Type *
                        </label>
                        <select id="waste_type" name="waste_type" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="">Select waste type</option>
                            <option value="food">Food Waste</option>
                            <option value="glass">Glass</option>
                            <option value="plastic">Plastic</option>
                            <option value="polythene">Polythene</option>
                            <option value="organic">Organic Waste</option>
                            <option value="paper">Paper</option>
                            <option value="electronic">Electronic Waste</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Pickup Address -->
                    <div>
                        <label for="pickup_address" class="block text-lg font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>Pickup Address *
                        </label>
                        <textarea id="pickup_address" name="pickup_address" rows="3" required 
                                  placeholder="Enter complete pickup address including street, city, and postal code"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all resize-none"></textarea>
                    </div>

                    <!-- Date and Time -->
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label for="pickup_date" class="block text-lg font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2 text-green-600"></i>Preferred Date *
                            </label>
                            <input type="date" id="pickup_date" name="pickup_date" required 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        </div>
                        
                        <div>
                            <label for="pickup_time" class="block text-lg font-semibold text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2 text-green-600"></i>Preferred Time *
                            </label>
                            <select id="pickup_time" name="pickup_time" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                                <option value="">Select time slot</option>
                                <option value="08:00-10:00">8:00 AM - 10:00 AM</option>
                                <option value="10:00-12:00">10:00 AM - 12:00 PM</option>
                                <option value="12:00-14:00">12:00 PM - 2:00 PM</option>
                                <option value="14:00-16:00">2:00 PM - 4:00 PM</option>
                                <option value="16:00-18:00">4:00 PM - 6:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <!-- Quantity Estimate -->
                    <div>
                        <label for="quantity_estimate" class="block text-lg font-semibold text-gray-700 mb-2">
                            <i class="fas fa-weight mr-2 text-green-600"></i>Weight Category
                        </label>
                        <select id="quantity_estimate" name="quantity_estimate" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="">Select weight category</option>
                            <option value="below-1kg">Below 1kg</option>
                            <option value="1-3kg">1-3kg</option>
                            <option value="2-3kg">2-3kg</option>
                            <option value="3-4kg">3-4kg</option>
                            <option value="3-5kg">3-5kg</option>
                            <option value="1-5kg">1-5kg</option>
                            <option value="5-10kg">5-10kg</option>
                            <option value="more-10kg">More than 10kg</option>
                        </select>
                    </div>

                    <!-- Special Instructions -->
                    <div>
                        <label for="special_instructions" class="block text-lg font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-2 text-green-600"></i>Special Instructions
                        </label>
                        <textarea id="special_instructions" name="special_instructions" rows="3" 
                                  placeholder="Any special instructions, access information, or additional details"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all resize-none"></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row gap-6 pt-6">
                        <button type="submit" class="flex-1 bg-green-600 text-white py-4 px-8 rounded-lg hover:bg-green-700 transition-colors font-semibold text-lg">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Request
                        </button>
                        <button type="reset" class="flex-1 bg-gray-600 text-white py-4 px-8 rounded-lg hover:bg-gray-700 transition-colors font-semibold text-lg">
                            <i class="fas fa-times mr-2"></i>Clear Form
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Requests -->
            <div class="bg-white rounded-xl shadow-md p-10">
                <h3 class="text-3xl font-extrabold text-gray-800 mb-8">
                    <i class="fas fa-list text-gray-600 mr-2"></i>Recent Requests
                </h3>
                
                <?php if (empty($recent_requests)): ?>
                    <!-- Empty State -->
                    <div class="text-center py-16">
                        <i class="fas fa-inbox text-5xl text-gray-400 mb-6"></i>
                        <h4 class="text-2xl font-bold text-gray-600 mb-4">No requests yet</h4>
                        <p class="text-lg text-gray-500">Your submitted requests will appear here. Use the form above to create your first request.</p>
                    </div>
                <?php else: ?>
                    <!-- Requests List -->
                    <div class="space-y-8">
                        <?php foreach ($recent_requests as $request): ?>
                            <div class="border border-gray-200 rounded-lg p-8 hover:shadow-lg transition-shadow text-lg"
                                 data-request-id="<?php echo $request['request_id']; ?>"
                                 data-waste-type="<?php echo htmlspecialchars($request['waste_type']); ?>"
                                 data-pickup-address="<?php echo htmlspecialchars($request['pickup_address']); ?>"
                                 data-pickup-date="<?php echo $request['preferred_pickup_date']; ?>"
                                 data-pickup-time="<?php echo htmlspecialchars($request['pickup_time']); ?>"
                                 data-quantity-estimate="<?php echo htmlspecialchars($request['weight_category'] ?? ''); ?>"
                                 data-special-instructions="<?php echo htmlspecialchars($request['special_instructions'] ?? ''); ?>">
                                <div class="flex items-start justify-between mb-6">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 mr-3">Request #<?php echo $request['request_id']; ?></span>
                                        <?php
                                        $status_class = '';
                                        $status_icon = '';
                                        switch ($request['status']) {
                                            case 'pending':
                                                $status_class = 'bg-yellow-100 text-yellow-800';
                                                $status_icon = 'fas fa-clock';
                                                break;
                                            case 'collected':
                                                $status_class = 'bg-green-100 text-green-800';
                                                $status_icon = 'fas fa-check-circle';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-red-100 text-red-800';
                                                $status_icon = 'fas fa-times-circle';
                                                break;
                                            case 'accepted':
                                                $status_class = 'bg-blue-100 text-blue-800';
                                                $status_icon = 'fas fa-thumbs-up';
                                                break;
                                        }
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?> mr-1"></i>
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                        </span>
                                        
                                        <!-- Action Buttons -->
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <!-- Update Button (only for pending) -->
                                            <button onclick="openUpdateModal(<?php echo $request['request_id']; ?>)" 
                                                    class="bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700 transition-colors text-xs font-medium">
                                                <i class="fas fa-edit mr-1"></i>Update
                                            </button>
                                            
                                            <!-- Delete Button (for pending) -->
                                            <button onclick="confirmDelete(<?php echo $request['request_id']; ?>)" 
                                                    class="bg-red-600 text-white px-3 py-1.5 rounded-md hover:bg-red-700 transition-colors text-xs font-medium">
                                                <i class="fas fa-trash mr-1"></i>Delete
                                            </button>
                                        <?php elseif ($request['status'] === 'rejected'): ?>
                                            <!-- Delete Button (for rejected) -->
                                            <button onclick="confirmDelete(<?php echo $request['request_id']; ?>)" 
                                                    class="bg-red-600 text-white px-3 py-1.5 rounded-md hover:bg-red-700 transition-colors text-xs font-medium">
                                                <i class="fas fa-trash mr-1"></i>Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div>
                                        <p class="text-sm text-gray-600 mb-1">
                                            <i class="fas fa-trash text-green-600 mr-2"></i>
                                            <strong>Waste Type:</strong> <?php echo ucfirst($request['waste_type']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                                            <strong>Address:</strong> <?php echo htmlspecialchars(substr($request['pickup_address'], 0, 50)) . (strlen($request['pickup_address']) > 50 ? '...' : ''); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 mb-1">
                                            <i class="fas fa-calendar text-green-600 mr-2"></i>
                                            <strong>Pickup Date:</strong> <?php echo date('M j, Y', strtotime($request['preferred_pickup_date'])); ?>
                                        </p>
                                        <?php if ($request['pickup_time']): ?>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-clock text-green-600 mr-2"></i>
                                                <strong>Time:</strong> <?php echo htmlspecialchars($request['pickup_time']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Update Request Modal -->
    <div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden text-lg">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-edit text-blue-600 mr-2"></i>Update Request
                        </h2>
                        <button onclick="closeUpdateModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Update Form -->
                    <form id="updateForm" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_request">
                        <input type="hidden" id="update_request_id" name="request_id">
                        
                        <!-- Waste Type -->
                        <div>
                            <label for="update_waste_type" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-trash mr-2 text-green-600"></i>Waste Type *
                            </label>
                            <select id="update_waste_type" name="waste_type" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="">Select waste type</option>
                                <option value="food">Food Waste</option>
                                <option value="glass">Glass</option>
                                <option value="plastic">Plastic</option>
                                <option value="polythene">Polythene</option>
                                <option value="organic">Organic Waste</option>
                                <option value="paper">Paper</option>
                                <option value="electronic">Electronic Waste</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Pickup Address -->
                        <div>
                            <label for="update_pickup_address" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>Pickup Address *
                            </label>
                            <textarea id="update_pickup_address" name="pickup_address" rows="3" required 
                                      placeholder="Enter complete pickup address"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"></textarea>
                        </div>

                        <!-- Date and Time -->
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="update_pickup_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar mr-2 text-green-600"></i>Preferred Date *
                                </label>
                                <input type="date" id="update_pickup_date" name="pickup_date" required 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                            
                            <div>
                                <label for="update_pickup_time" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-clock mr-2 text-green-600"></i>Preferred Time *
                                </label>
                                <select id="update_pickup_time" name="pickup_time" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="">Select time slot</option>
                                    <option value="08:00-10:00">8:00 AM - 10:00 AM</option>
                                    <option value="10:00-12:00">10:00 AM - 12:00 PM</option>
                                    <option value="12:00-14:00">12:00 PM - 2:00 PM</option>
                                    <option value="14:00-16:00">2:00 PM - 4:00 PM</option>
                                    <option value="16:00-18:00">4:00 PM - 6:00 PM</option>
                                </select>
                            </div>
                        </div>

                        <!-- Quantity Estimate -->
                        <div>
                            <label for="update_quantity_estimate" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-weight mr-2 text-green-600"></i>Weight Category
                            </label>
                            <select id="update_quantity_estimate" name="quantity_estimate" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="">Select weight category</option>
                                <option value="below-1kg">Below 1kg</option>
                                <option value="1-3kg">1-3kg</option>
                                <option value="2-3kg">2-3kg</option>
                                <option value="3-4kg">3-4kg</option>
                                <option value="3-5kg">3-5kg</option>
                                <option value="1-5kg">1-5kg</option>
                                <option value="5-10kg">5-10kg</option>
                                <option value="more-10kg">More than 10kg</option>
                            </select>
                        </div>

                        <!-- Special Instructions -->
                        <div>
                            <label for="update_special_instructions" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-2 text-green-600"></i>Special Instructions
                            </label>
                            <textarea id="update_special_instructions" name="special_instructions" rows="3" 
                                      placeholder="Any special instructions"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"></textarea>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-4">
                            <button type="submit" class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i>Update Request
                            </button>
                            <button type="button" onclick="closeUpdateModal()" class="flex-1 bg-gray-600 text-white py-3 px-6 rounded-lg hover:bg-gray-700 transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div id="profileModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden text-lg">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-user-cog text-green-600 mr-2"></i>Update Profile
                        </h2>
                        <button onclick="closeProfileModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <form id="profileForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                <input type="text" name="first_name" required value="<?php echo htmlspecialchars($user_profile['first_name'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                <input type="text" name="last_name" required value="<?php echo htmlspecialchars($user_profile['last_name'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" />
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" required value="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Contact</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user_profile['phone'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" />
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">District</label>
                                <input type="text" name="district" value="<?php echo htmlspecialchars($user_profile['district'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nearest Town</label>
                                <input type="text" name="nearest_town" value="<?php echo htmlspecialchars($user_profile['nearest_town'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                            <textarea name="address" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all resize-none"><?php echo htmlspecialchars($user_profile['address'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user_profile['date_of_birth'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label>
                            <input type="file" name="profile_photo" accept="image/*"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all bg-white" />
                            <p class="text-xs text-gray-500 mt-1">Optional. JPG/PNG/WEBP up to 2MB.</p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 pt-4">
                            <button type="submit" class="flex-1 bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                            <button type="button" onclick="closeProfileModal()" class="flex-1 bg-gray-600 text-white py-3 px-6 rounded-lg hover:bg-gray-700 transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-green-50 to-green-100 py-6">
        <div class="container mx-auto px-12">
            <div class="text-center">
                <p class="text-gray-700 text-sm">&copy; 2025 TrashSmart. All rights reserved.</p>
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
        
        // Auto-hide success alerts after 8 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const successAlert = document.getElementById('successAlert');
                if (successAlert) {
                    closeAlert('successAlert');
                }
            }, 8000);
            
            // Also auto-hide error alerts after 10 seconds
            setTimeout(function() {
                const errorAlert = document.getElementById('errorAlert');
                if (errorAlert) {
                    closeAlert('errorAlert');
                }
            }, 10000);
        });
        
        // Request management functions
        function confirmDelete(requestId) {
            if (confirm('Are you sure you want to delete this request? This action cannot be undone.')) {
                // Create and submit delete form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_request">
                    <input type="hidden" name="request_id" value="${requestId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function openUpdateModal(requestId) {
            // Get request data from the page
            const requestElement = document.querySelector(`[data-request-id="${requestId}"]`);
            
            if (requestElement) {
                // Populate form with current data
                document.getElementById('update_request_id').value = requestId;
                document.getElementById('update_waste_type').value = requestElement.dataset.wasteType || '';
                document.getElementById('update_pickup_address').value = requestElement.dataset.pickupAddress || '';
                document.getElementById('update_pickup_date').value = requestElement.dataset.pickupDate || '';
                document.getElementById('update_pickup_time').value = requestElement.dataset.pickupTime || '';
                document.getElementById('update_quantity_estimate').value = requestElement.dataset.quantityEstimate || '';
                document.getElementById('update_special_instructions').value = requestElement.dataset.specialInstructions || '';
            } else {
                // Fallback: just set request ID and let user fill manually
                document.getElementById('update_request_id').value = requestId;
            }
            
            // Show modal
            document.getElementById('updateModal').classList.remove('hidden');
        }
        
        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
            // Reset form
            document.getElementById('updateForm').reset();
        }
        
        // Close modal when clicking outside
        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUpdateModal();
            }
        });

        // Profile modal handlers
        function openProfileModal() {
            document.getElementById('profileModal').classList.remove('hidden');
        }
        function closeProfileModal() {
            document.getElementById('profileModal').classList.add('hidden');
            const form = document.getElementById('profileForm');
            if (form) form.reset();
        }
        // Confirmations
        function confirmDeleteProfile() {
            return confirm('Are you sure you want to permanently delete your account? This action cannot be undone. All your requests will be removed.');
        }
        function confirmRemovePhoto() {
            return confirm('Remove your profile photo?');
        }
    </script>
</body>
</html>
