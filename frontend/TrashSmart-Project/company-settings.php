<?php
session_start();

// Check if user is logged in and is admin
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $company_name = trim($_POST['company_name']);
    $company_description = trim($_POST['company_description']);
    $mission_statement = trim($_POST['mission_statement']);
    $experience_years = (int)$_POST['experience_years'];
    $customers_served = trim($_POST['customers_served']);
    $cities_served = trim($_POST['cities_served']);
    $satisfaction_rate = trim($_POST['satisfaction_rate']);
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $contact_address = trim($_POST['contact_address']);
    $facebook_url = trim($_POST['facebook_url']);
    $instagram_url = trim($_POST['instagram_url']);
    $twitter_url = trim($_POST['twitter_url']);
    $linkedin_url = trim($_POST['linkedin_url']);
    
    // Handle company logo upload
    $company_logo_url = '';
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'images/';
        $file_extension = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Remove old logo files with different extensions
            $logo_patterns = ['company-logo.jpg', 'company-logo.jpeg', 'company-logo.png', 'company-logo.gif'];
            foreach ($logo_patterns as $pattern) {
                if (file_exists($upload_dir . $pattern)) {
                    unlink($upload_dir . $pattern);
                }
            }
            
            $new_filename = 'company-logo.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path)) {
                $company_logo_url = $upload_path;
            } else {
                $error_message = "Failed to upload company logo.";
            }
        } else {
            $error_message = "Invalid file type. Please upload JPG, JPEG, PNG, or GIF files only.";
        }
    }
    
    // Handle about us image upload
    $about_us_image_url = '';
    $about_upload_error = '';
    if (isset($_FILES['about_us_image']) && $_FILES['about_us_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'images/';
        $file_extension = strtolower(pathinfo($_FILES['about_us_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Remove old about us files with different extensions
            $about_patterns = ['about-us.jpg', 'about-us.jpeg', 'about-us.png', 'about-us.gif'];
            foreach ($about_patterns as $pattern) {
                if (file_exists($upload_dir . $pattern)) {
                    unlink($upload_dir . $pattern);
                }
            }
            
            $new_filename = 'about-us.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['about_us_image']['tmp_name'], $upload_path)) {
                $about_us_image_url = $upload_path;
            } else {
                $about_upload_error = "Failed to upload about us image.";
            }
        } else {
            $about_upload_error = "Invalid file type for about us image. Please upload JPG, JPEG, PNG, or GIF files only.";
        }
    }
    
    // Handle hero image upload
    $hero_image_url = '';
    $hero_upload_error = '';
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'images/';
        $file_extension = strtolower(pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Remove old hero files with different extensions
            $hero_patterns = ['hero-image.jpg', 'hero-image.jpeg', 'hero-image.png', 'hero-image.gif'];
            foreach ($hero_patterns as $pattern) {
                if (file_exists($upload_dir . $pattern)) {
                    unlink($upload_dir . $pattern);
                }
            }
            
            $new_filename = 'hero-image.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $upload_path)) {
                $hero_image_url = $upload_path;
            } else {
                $hero_upload_error = "Failed to upload hero image.";
            }
        } else {
            $hero_upload_error = "Invalid file type for hero image. Please upload JPG, JPEG, PNG, or GIF files only.";
        }
    }
    
    // Validation
    $validation_errors = [];
    if (empty($company_name) || empty($company_description)) {
        $validation_errors[] = "Company name and description are required.";
    }
    
    if (!empty($about_upload_error)) {
        $validation_errors[] = $about_upload_error;
    }
    
    if (!empty($hero_upload_error)) {
        $validation_errors[] = $hero_upload_error;
    }
    
    if (!empty($validation_errors)) {
        $error_message = implode(" ", $validation_errors);
    } else {
        $conn = getDatabaseConnection();
        
        // Get existing logo and images URLs before deleting records
        $existing_logo = '';
        $existing_about_image = '';
        $existing_hero_image = '';
        $existing_query = "SELECT company_logo_url, about_us_image_url, hero_image_url FROM about_us LIMIT 1";
        $existing_result = $conn->query($existing_query);
        if ($existing_result && $existing_row = $existing_result->fetch_assoc()) {
            $existing_logo = $existing_row['company_logo_url'] ?? '';
            $existing_about_image = $existing_row['about_us_image_url'] ?? '';
            $existing_hero_image = $existing_row['hero_image_url'] ?? '';
        }
        
        // Use uploaded images or keep existing ones
        $final_logo_url = $company_logo_url ? $company_logo_url : $existing_logo;
        $final_about_image_url = $about_us_image_url ? $about_us_image_url : $existing_about_image;
        $final_hero_image_url = $hero_image_url ? $hero_image_url : $existing_hero_image;
        
        // Ensure only one record exists - delete all and insert one
        $conn->query("DELETE FROM about_us");
        
        // Insert the single company settings record
        $stmt = $conn->prepare("INSERT INTO about_us (company_name, company_description, mission_statement, experience_years, customers_served, cities_served, satisfaction_rate, contact_phone, contact_email, contact_address, facebook_url, instagram_url, twitter_url, linkedin_url, company_logo_url, about_us_image_url, hero_image_url, updated_by_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisssssssssssssi", $company_name, $company_description, $mission_statement, $experience_years, $customers_served, $cities_served, $satisfaction_rate, $contact_phone, $contact_email, $contact_address, $facebook_url, $instagram_url, $twitter_url, $linkedin_url, $final_logo_url, $final_about_image_url, $final_hero_image_url, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success_message = "Company settings updated successfully!";
        } else {
            $error_message = "Failed to update company settings.";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Get current settings
$conn = getDatabaseConnection();
$settings_query = "SELECT * FROM about_us LIMIT 1";
$result = $conn->query($settings_query);

if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
} else {
    // If no settings exist, use defaults
    $settings = [
        'company_name' => 'TrashSmart',
        'company_description' => '',
        'mission_statement' => '',
        'experience_years' => 5,
        'customers_served' => '10K+',
        'cities_served' => '50+',
        'satisfaction_rate' => '95%',
        'contact_phone' => '',
        'contact_email' => '',
        'contact_address' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'twitter_url' => '',
        'linkedin_url' => '',
        'company_logo_url' => '',
        'about_us_image_url' => '',
        'hero_image_url' => ''
    ];
}

$conn->close();

// Get admin information from session
$adminName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Settings - TrashSmart Admin</title>
    
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
            <!-- Logo -->
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center">
                    <img src="images/trash-smart-logo.jpg" alt="TrashSmart Logo" class="h-10 w-auto">
                    <span class="ml-3 text-2xl font-bold text-gray-800">Admin Panel</span>
                </div>
                <div class="hidden md:flex space-x-6">
                    <a href="admin-dashboard.php" class="nav-link text-base text-gray-700 hover:text-green-600 font-semibold">Dashboard</a>
                    <a href="admin-management.php" class="nav-link text-base text-gray-700 hover:text-green-600 transition-colors">Citizen Management</a>
                    <a href="company-settings.php" class="nav-link text-base text-green-600 font-semibold">Company Settings</a>
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
    <main class="pt-36 pb-12">
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

                        <h1 class="text-5xl font-extrabold text-black mb-4">Company Settings</h1>
                        <p class="text-xl text-gray-600">Customize company information, about us content, and contact details</p>

                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-building text-6xl text-green-400"></i>
                    </div>
                </div>
            </div>

            <!-- Company Settings Form -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden mb-20">
                <div class="p-6 border-b border-gray-200">

                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-edit text-green-600 mr-2"></i>

                        Update Company Information
                    </h3>
                    <p class="text-sm text-gray-600 mt-2">
                        <span class="text-red-500">*</span> Required fields | 
                        <span class="text-gray-400">Optional</span> fields can be left empty
                    </p>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="p-6">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <!-- Images Management Section -->

                    <div class="bg-green-50 rounded-xl p-6 mb-8">
                        <h4 class="text-2xl font-bold text-green-600 mb-6 flex items-center">
                            <i class="fas fa-images text-green-600 mr-3"></i>

                            Image Management
                        </h4>
                        <p class="text-lg text-gray-600 mb-6">Upload and manage images for your website's visual content</p>
                        
                        <div class="grid lg:grid-cols-2 gap-8">
                            <!-- Company Logo Upload -->
                            <div class="bg-white rounded-lg p-6 shadow-sm">
                                <h5 class="text-xl font-bold text-gray-800 mb-4">Company Logo</h5>
                                
                                <div class="flex flex-col items-center space-y-4">
                                    <?php if (!empty($settings['company_logo_url']) && file_exists($settings['company_logo_url'])): ?>
                                        <div class="flex-shrink-0">
                                            <img src="<?php echo htmlspecialchars($settings['company_logo_url']) . '?v=' . time(); ?>" 
                                                 alt="Current Company Logo" 
                                                 class="h-20 w-20 object-cover rounded-lg border border-gray-300">
                                        </div>
                                        <small class="text-green-600">✓ Current: <?php echo htmlspecialchars($settings['company_logo_url']); ?></small>
                                    <?php else: ?>
                                        <div class="flex-shrink-0">
                                            <div class="h-20 w-20 bg-gray-200 rounded-lg flex items-center justify-center border border-gray-300">
                                                <i class="fas fa-building text-gray-500 text-2xl"></i>
                                            </div>
                                        </div>
                                        <small class="text-gray-500">No logo uploaded</small>
                                    <?php endif; ?>
                                    
                                    <div class="w-full">
                                        <label for="company_logo" class="block text-sm font-medium text-gray-700 mb-2">Upload New Logo</label>
                                        <input type="file" id="company_logo" name="company_logo" 
                                               accept=".jpg,.jpeg,.png,.gif"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <p class="text-xs text-gray-500 mt-1">Appears in header & footer</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hero Section Image Upload -->
                            <div class="bg-white rounded-lg p-6 shadow-sm">
                                <h5 class="text-xl font-bold text-gray-800 mb-4">Hero Section Image</h5>
                                
                                <div class="flex flex-col items-center space-y-4">
                                    <?php if (!empty($settings['hero_image_url']) && file_exists($settings['hero_image_url'])): ?>
                                        <div class="flex-shrink-0">
                                            <img src="<?php echo htmlspecialchars($settings['hero_image_url']) . '?v=' . time(); ?>" 
                                                 alt="Current Hero Image" 
                                                 class="h-32 w-48 object-cover rounded-lg border border-gray-300">
                                        </div>
                                        <small class="text-green-600">✓ Current: <?php echo htmlspecialchars($settings['hero_image_url']); ?></small>
                                    <?php else: ?>
                                        <div class="flex-shrink-0">
                                            <div class="h-32 w-48 bg-gray-200 rounded-lg flex items-center justify-center border border-gray-300">
                                                <i class="fas fa-image text-gray-500 text-3xl"></i>
                                            </div>
                                        </div>
                                        <small class="text-gray-500">No hero image uploaded</small>
                                    <?php endif; ?>
                                    
                                    <div class="w-full">
                                        <label for="hero_image" class="block text-sm font-medium text-gray-700 mb-2">Upload Hero Image</label>
                                        <input type="file" id="hero_image" name="hero_image" 
                                               accept=".jpg,.jpeg,.png,.gif"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <p class="text-xs text-gray-500 mt-1">Main homepage image (1200x800px)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- About Us Image Upload -->
                        <div class="bg-white rounded-lg p-6 shadow-sm mt-6">
                            <h5 class="text-xl font-bold text-gray-800 mb-4">About Us Section Image</h5>
                            
                            <div class="flex items-center space-x-6">
                                <?php if (!empty($settings['about_us_image_url']) && file_exists($settings['about_us_image_url'])): ?>
                                    <div class="flex-shrink-0">
                                        <img src="<?php echo htmlspecialchars($settings['about_us_image_url']) . '?v=' . time(); ?>" 
                                             alt="Current About Us Image" 
                                             class="h-32 w-48 object-cover rounded-lg border border-gray-300">
                                        <br><small class="text-green-600">✓ Current: <?php echo htmlspecialchars($settings['about_us_image_url']); ?></small>
                                    </div>
                                <?php else: ?>
                                    <div class="flex-shrink-0">
                                        <div class="h-32 w-48 bg-gray-200 rounded-lg flex items-center justify-center border border-gray-300">
                                            <i class="fas fa-image text-gray-500 text-3xl"></i>
                                        </div>
                                        <br><small class="text-gray-500">No about us image uploaded</small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex-1">
                                    <label for="about_us_image" class="block text-sm font-medium text-gray-700 mb-2">Upload About Us Image</label>
                                    <input type="file" id="about_us_image" name="about_us_image" 
                                           accept=".jpg,.jpeg,.png,.gif"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <p class="text-xs text-gray-500 mt-1">This image appears on the right side of the About section (800x600px recommended)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Company Basic Info -->
                    <div class="mb-8">
                        <div class="flex flex-col items-center space-y-6">
                            <div class="w-2/3 flex items-center">
                                <label for="company_name" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Company Name : <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($settings['company_name']); ?>"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       required>
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="experience_years" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Years of Experience : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="number" id="experience_years" name="experience_years" 
                                       value="<?php echo $settings['experience_years']; ?>"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       min="0">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="customers_served" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Customers Served : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="text" id="customers_served" name="customers_served" 
                                       value="<?php echo htmlspecialchars($settings['customers_served']); ?>"
                                       placeholder="e.g., 10K+"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="cities_served" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Cities Served : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="text" id="cities_served" name="cities_served" 
                                       value="<?php echo htmlspecialchars($settings['cities_served']); ?>"
                                       placeholder="e.g., 50+"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="satisfaction_rate" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Satisfaction Rate : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="text" id="satisfaction_rate" name="satisfaction_rate" 
                                       value="<?php echo htmlspecialchars($settings['satisfaction_rate']); ?>"
                                       placeholder="e.g., 95%"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent mb-12">
                            </div>
                        </div>
                    </div>
                    
                    <!-- About Us Content -->
                    <div class="mb-8">

                        <div class="w-full flex justify-center mb-4">
                            <h4 class="text-2xl font-bold text-green-600 text-center">About Us Content</h4>

                        </div>
                        <div class="flex flex-col items-center">
                            <div class="mb-6 w-2/3 flex items-center">
                                <label for="company_description" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Company Description : <span class="text-red-500">*</span>
                                </label>
                                <textarea id="company_description" name="company_description" rows="4"
                                          class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                          placeholder="Write about your company..." required><?php echo htmlspecialchars($settings['company_description']); ?></textarea>
                            </div>
                            <div class="mb-6 w-2/3 flex items-center">
                                <label for="mission_statement" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Mission Statement : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <textarea id="mission_statement" name="mission_statement" rows="3"
                                          class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                          placeholder="Your company's mission statement..."><?php echo htmlspecialchars($settings['mission_statement']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->

                    <div class="mt-16 mb-16">
                        <div class="w-full flex justify-center mb-4">
                            <h4 class="text-2xl font-bold text-green-600 text-center">Contact Information</h4>
                        </div>
                        <div class="flex flex-col items-center space-y-6">
                            <div class="w-2/3 flex items-center">
                                <label for="contact_phone" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Phone Number : <span class="text-gray-400 text-sm">(Optional)</span>

                                </label>
                                <input type="tel" id="contact_phone" name="contact_phone" 
                                       value="<?php echo htmlspecialchars($settings['contact_phone']); ?>"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="contact_email" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Email Address : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="email" id="contact_email" name="contact_email" 
                                       value="<?php echo htmlspecialchars($settings['contact_email']); ?>"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="contact_address" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    Business Address : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <textarea id="contact_address" name="contact_address" rows="3"
                                          class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                          placeholder="Your business address..."><?php echo htmlspecialchars($settings['contact_address']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Social Media Links -->

                    <div class="mt-16 mb-16">
                        <div class="w-full flex justify-center mb-4">
                            <h4 class="text-2xl font-bold text-green-600 text-center">Social Media Links</h4>

                        </div>
                        <div class="flex flex-col items-center space-y-6">
                            <div class="w-2/3 flex items-center">
                                <label for="facebook_url" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    <i class="fab fa-facebook text-green-600 mr-2"></i>Facebook URL : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="url" id="facebook_url" name="facebook_url" 
                                       value="<?php echo htmlspecialchars($settings['facebook_url']); ?>"
                                       placeholder="https://facebook.com/yourcompany"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="instagram_url" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    <i class="fab fa-instagram text-pink-600 mr-2"></i>Instagram URL : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="url" id="instagram_url" name="instagram_url" 
                                       value="<?php echo htmlspecialchars($settings['instagram_url']); ?>"
                                       placeholder="https://instagram.com/yourcompany"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="twitter_url" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    <i class="fab fa-twitter text-green-400 mr-2"></i>Twitter URL : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="url" id="twitter_url" name="twitter_url" 
                                       value="<?php echo htmlspecialchars($settings['twitter_url']); ?>"
                                       placeholder="https://twitter.com/yourcompany"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="w-2/3 flex items-center">
                                <label for="linkedin_url" class="w-1/3 text-sm font-medium text-gray-700 mr-4 text-left">
                                    <i class="fab fa-linkedin text-green-700 mr-2"></i>LinkedIn URL : <span class="text-gray-400 text-sm">(Optional)</span>
                                </label>
                                <input type="url" id="linkedin_url" name="linkedin_url" 
                                       value="<?php echo htmlspecialchars($settings['linkedin_url']); ?>"
                                       placeholder="https://linkedin.com/company/yourcompany"
                                       class="w-2/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex justify-center space-x-4 mt-8">
                        <a href="admin-management.php" 
                           class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i><span class="text-sm">Back to Citizen Management</span>
                        </a>
                        <button type="submit" 
                                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm font-medium flex items-center">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
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
    <script>
        function closeAlert(alertId) {
            document.getElementById(alertId).style.display = 'none';
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('[id$="Alert"]');
            alerts.forEach(alert => {
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(() => alert.style.display = 'none', 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>
