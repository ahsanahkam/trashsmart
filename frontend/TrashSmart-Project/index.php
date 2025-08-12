<?php
/**
 * TrashSmart Home Page with Authentication Handling
 * Processes login and registration forms from the homepage
 */

session_start();

// Initialize variables for messages
$login_error = '';
$register_error = '';
$register_success = '';

// Database connection
function getDatabaseConnection() {
    $conn = new mysqli('localhost', 'root', '', 'trashsmart');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Get company settings
$conn = getDatabaseConnection();
$settings_query = "SELECT * FROM about_us LIMIT 1";
$result = $conn->query($settings_query);

if ($result && $result->num_rows > 0) {
    $db_settings = $result->fetch_assoc();
} else {
    $db_settings = null;
}

// Map database columns to expected array keys
if ($db_settings) {
    $company_settings = [
        'company_name' => $db_settings['company_name'] ?? 'TrashSmart',
        'about_us' => $db_settings['company_description'] ?? 'Leading waste management solutions for a cleaner future.',
        'mission' => $db_settings['mission_statement'] ?? '',
        'address' => $db_settings['contact_address'] ?? '123 Main Street, Colombo, Sri Lanka',
        'phone' => $db_settings['contact_phone'] ?? '+94 11 234 5678',
        'email' => $db_settings['contact_email'] ?? 'info@trashsmart.lk',
        'social_facebook' => $db_settings['facebook_url'] ?? '',
        'social_twitter' => $db_settings['twitter_url'] ?? '',
        'social_instagram' => $db_settings['instagram_url'] ?? '',
        'social_linkedin' => $db_settings['linkedin_url'] ?? '',
        'stat_citizens' => $db_settings['customers_served'] ?? '10K+',
        'stat_pickups' => '25K+', // This can be a calculated field later
        'stat_partners' => $db_settings['cities_served'] ?? '50+',
        'stat_satisfaction' => $db_settings['satisfaction_rate'] ?? '95%',
        'company_logo' => $db_settings['company_logo_url'] ?? '',
        'about_image' => $db_settings['about_us_image_url'] ?? '',
        'hero_image' => $db_settings['hero_image_url'] ?? ''
    ];
} else {
    // Default values if no settings exist
    $company_settings = [
        'company_name' => 'TrashSmart',
        'about_us' => 'Leading waste management solutions for a cleaner future.',
        'mission' => 'To revolutionize waste management through innovation.',
        'address' => '123 Main Street, Colombo, Sri Lanka',
        'phone' => '+94 11 234 5678',
        'email' => 'info@trashsmart.lk',
        'social_facebook' => '',
        'social_twitter' => '',
        'social_instagram' => '',
        'social_linkedin' => '',
        'stat_citizens' => '10K+',
        'stat_pickups' => '25K+',
        'stat_partners' => '50+',
        'stat_satisfaction' => '95%',
        'company_logo' => '',
        'about_image' => '',
        'hero_image' => ''
    ];
}
$conn->close();

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $login_error = "Please fill in all fields.";
    } else {
        $conn = getDatabaseConnection();
        
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, user_type FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            $storedHashOrPlain = $user['password'];
            $isValid = false;

            // Preferred: verify against a hash
            if (!empty($storedHashOrPlain) && password_verify($password, $storedHashOrPlain)) {
                $isValid = true;
            } elseif ($storedHashOrPlain === $password) {
                // Legacy plaintext password: migrate to hash on successful login
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                if ($newHash) {
                    $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $update->bind_param("si", $newHash, $user['user_id']);
                    $update->execute();
                    $update->close();
                }
                $isValid = true;
            }

            if ($isValid) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];

                // Redirect based on user type
                if ($user['user_type'] === 'admin') {
                    header('Location: admin-dashboard.php');
                } else {
                    header('Location: citizen-profile.php');
                }
                exit;
            } else {
                $login_error = "Invalid email or password.";
            }
        } else {
            $login_error = "Invalid email or password.";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Handle Registration Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $dateOfBirth = $_POST['dateOfBirth'];
    $district = trim($_POST['district']);
    $address = trim($_POST['address']);
    $nearestTown = trim($_POST['nearestTown']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($dateOfBirth) || 
        empty($district) || empty($address) || empty($nearestTown) || 
        empty($phone) || empty($email) || empty($password) || empty($confirmPassword)) {
        $register_error = "Please fill in all fields.";
    } elseif ($password !== $confirmPassword) {
        $register_error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters long.";
    } elseif (!preg_match('/[a-zA-Z]/', $password)) {
        $register_error = "Password must contain at least one letter.";
    } elseif (!preg_match('/\\d/', $password)) {
        $register_error = "Password must contain at least one number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Please enter a valid email address.";
    } else {
        $conn = getDatabaseConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $register_error = "An account with this email already exists.";
        } else {
            // Secure: hash password before storing
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userType = 'citizen';

            // Normalize phone (digits and + only)
            $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

            // Align with backend schema: store first_name and last_name; set status and created_at
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, phone, date_of_birth, district, nearest_town, address, user_type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'citizen', 'active', NOW())");
            $stmt->bind_param("sssssssss", $firstName, $lastName, $email, $hashedPassword, $cleanPhone, $dateOfBirth, $district, $nearestTown, $address);
            
            if ($stmt->execute()) {
                $register_success = "Registration successful! You can now login.";
                
                // Auto-login the user
                $userId = $conn->insert_id;
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
                $_SESSION['user_email'] = $email;
                $_SESSION['user_type'] = $userType;
                
                // Redirect to citizen profile
                header('Location: citizen-profile.php');
                exit;
            } else {
                $register_error = "Registration failed. Please try again.";
            }
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);
    if (!empty($name) && !empty($email) && !empty($message)) {
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $name, $email, $message);
        if ($stmt->execute()) {
            $_SESSION['contact_success'] = "Thank you for your message! We'll get back to you soon.";
        } else {
            $_SESSION['contact_error'] = "Failed to send message. Please try again.";
        }
        $stmt->close();
        $conn->close();
    }
    // Redirect to clear POST and show message only once
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrashSmart - Smart Waste Management</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="font-poppins bg-white">
    
    <!-- Header Section -->
    <header class="fixed top-0 left-0 right-0 bg-white shadow-md z-50">
        <nav class="container mx-auto px-4 py-4 flex justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center">
                <?php if (!empty($company_settings['company_logo']) && file_exists($company_settings['company_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($company_settings['company_logo']) . '?v=' . time(); ?>" alt="<?php echo htmlspecialchars($company_settings['company_name']); ?> Logo" class="h-10 w-auto">
                <?php else: ?>
                    <img src="images/trash-smart-logo.jpg" alt="<?php echo htmlspecialchars($company_settings['company_name']); ?> Logo" class="h-10 w-auto">
                <?php endif; ?>
            </div>
            
            <!-- Navigation Menu -->
            <div class="hidden md:flex space-x-8">
                <a href="#home" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Home</a>
                <a href="#tips" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Tips</a>
                <a href="#about" class="nav-link text-gray-700 hover:text-green-600 transition-colors">About Us</a>
                <a href="#contact" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Contact Us</a>
            </div>
            
            <!-- Auth Buttons -->
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="<?php echo $_SESSION['user_type'] === 'admin' ? 'admin-dashboard.php' : 'citizen-profile.php'; ?>" class="text-green-600 hover:text-green-700 transition-colors">
                        <i class="fas fa-user mr-2"></i>Dashboard
                    </a>
                    <a href="../../backend/logout.php" class="text-brown-600 hover:text-brown-700 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                <?php else: ?>
                    <button id="loginBtn" class="text-green-600 hover:text-green-700 transition-colors">
                        <i class="fas fa-user mr-2"></i>Login
                    </button>
                    <button id="signupBtn" class="text-brown-600 hover:text-brown-700 transition-colors">
                        Sign Up
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Button -->
            <button id="mobileMenuBtn" class="md:hidden text-gray-700">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </nav>

        <!-- Mobile Menu (Hidden by default) -->
        <div id="mobileMenu" class="md:hidden hidden bg-white border-t border-gray-200">
            <div class="container mx-auto px-4 py-4">
                <div class="flex flex-col space-y-4">
                    <a href="#home" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Home</a>
                    <a href="#tips" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Tips</a>
                    <a href="#about" class="nav-link text-gray-700 hover:text-green-600 transition-colors">About Us</a>
                    <a href="#contact" class="nav-link text-gray-700 hover:text-green-600 transition-colors">Contact Us</a>
                    
                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <button id="mobileLoginBtn" class="block w-full text-left text-green-600 hover:text-green-700 transition-colors mb-2">
                        <i class="fas fa-user mr-2"></i>Login
                    </button>
                    <button id="mobileSignupBtn" class="block w-full text-left text-brown-600 hover:text-brown-700 transition-colors">
                        Sign Up
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="pt-12 pb-16 bg-gradient-to-br from-green-50 to-brown-50">
        <div class="container mx-auto px-4 py-12">
            <div class="flex flex-col lg:flex-row gap-12 items-center bg-white rounded-2xl shadow-lg p-6 h-[40rem] w-full mx-auto">
                <div class="flex-1 px-8 lg:px-32 text-left">
                    <h1 class="text-5xl font-extrabold text-black mb-4">Join TrashSmart</h1>
                    <h2 class="text-3xl font-semibold text-green-700 mb-6">Keep Your Environment Clean!</h2>
                    <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                        Smart waste management for a sustainable future.
                        Connect with local waste collection services &
                        Learn proper recycling techniques.
                        Join our community and make a difference today.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-start">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <button id="heroLoginBtn" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition-all transform hover:scale-105">
                            Get Started
                        </button>
                        <button id="heroSignupBtn" class="bg-brown-600 text-white px-8 py-3 rounded-lg hover:bg-brown-700 transition-all transform hover:scale-105">
                            Sign Up Now
                        </button>
                        <?php else: ?>
                        <a href="<?php echo $_SESSION['user_type'] === 'admin' ? 'admin-dashboard.php' : 'citizen-profile.php'; ?>" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition-all transform hover:scale-105 inline-block text-left">
                            Go to Dashboard
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-1 relative w-full h-[28rem] lg:h-[34rem]">
                    <?php if (!empty($company_settings['hero_image']) && file_exists($company_settings['hero_image'])): ?>
                        <img src="<?php echo htmlspecialchars($company_settings['hero_image']) . '?v=' . time(); ?>" alt="<?php echo htmlspecialchars($company_settings['company_name']); ?> Hero Image" class="shadow-2xl w-full h-full object-cover">
                    <?php else: ?>
                        <!-- Fallback to existing image -->
                        <?php if (file_exists('images/about-waste-management (2).jpg')): ?>
                            <img src="images/about-waste-management (2).jpg" alt="<?php echo htmlspecialchars($company_settings['company_name']); ?> Hero Image" class="shadow-2xl w-full h-full object-cover">
                        <?php else: ?>
                            <div class="bg-gray-200 shadow-2xl w-full h-full flex items-center justify-center">
                                <div class="text-center">
                                    <i class="fas fa-image text-gray-400 text-6xl mb-4"></i>
                                    <p class="text-gray-500">Upload a Hero image in Company Settings</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="absolute -bottom-6 -right-6 bg-green-600 text-white p-4 rounded-xl shadow-lg">
                        <i class="fas fa-recycle text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="my-4 flex justify-center">
        <div class="w-3/4 border-t-4 border-green-300"></div>
    </div>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="container mx-auto px-8 lg:px-32">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-extrabold text-gray-800 mb-4">Why Choose TrashSmart?</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Our platform brings together advanced technology and environmental consciousness to create the most efficient waste management solution.
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="bg-green-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <div class="w-16 h-16 bg-green-600 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-clock text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">24/7 Service</h3>
                    <p class="text-gray-600 text-center">Schedule pickups anytime, anywhere with our round-the-clock service availability.</p>
                </div>
                
                <div class="bg-blue-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <div class="w-16 h-16 bg-blue-600 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-map-marker-alt text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Real-time Tracking</h3>
                    <p class="text-gray-600 text-center">Track your waste collection requests in real-time and stay updated on pickup status.</p>
                </div>
                
                <div class="bg-yellow-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <div class="w-16 h-16 bg-yellow-600 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-leaf text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Eco-Friendly</h3>
                    <p class="text-gray-600 text-center">Contribute to environmental sustainability with our green waste management practices.</p>
                </div>
                
                <div class="bg-purple-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <div class="w-16 h-16 bg-purple-600 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Community Driven</h3>
                    <p class="text-gray-600 text-center">Join a community of environmentally conscious citizens working towards a cleaner future.</p>
                </div>
            </div>
        </div>
    </section>
    <div class="my-4 flex justify-center">
        <div class="w-3/4 border-t-4 border-green-300"></div>
    </div>

    <!-- Tips Section -->
    <!-- Tips Section -->
    <section id="tips" class="py-20 bg-gray-50">
        <div class="container mx-auto px-8 lg:px-32">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-extrabold text-gray-800 mb-4">Learn to Sort &amp; Recycle</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Discover the different types of waste and learn how to properly sort and recycle them for a cleaner environment.
                </p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="bg-green-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <!-- Icon removed -->
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Organic Waste</h3>
                    <p class="text-gray-600 text-center">Food scraps, garden waste, and biodegradable materials</p>
                    <div class="bg-green-100 rounded-lg p-3 mt-2 flex items-center">
                        <span class="text-2xl mr-2">💡</span>
                        <span class="text-gray-700 text-sm">Compost your kitchen waste to create nutrient-rich soil for your garden.</span>
                    </div>
                </div>
                <div class="bg-blue-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <!-- Icon removed -->
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Plastic Waste</h3>
                    <p class="text-gray-600 text-center">Bottles, containers, and plastic packaging</p>
                    <div class="bg-blue-100 rounded-lg p-3 mt-2 flex items-center">
                        <span class="text-2xl mr-2">💡</span>
                        <span class="text-gray-700 text-sm">Rinse plastic bottles before recycling to prevent contamination.</span>
                    </div>
                </div>
                <div class="bg-yellow-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <!-- Icon removed -->
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Paper Waste</h3>
                    <p class="text-gray-600 text-center">Newspapers, cardboard, and paper products</p>
                    <div class="bg-yellow-100 rounded-lg p-3 mt-2 flex items-center">
                        <span class="text-2xl mr-2">💡</span>
                        <span class="text-gray-700 text-sm">Flatten cardboard boxes to save space in recycling bins.</span>
                    </div>
                </div>
                <div class="bg-purple-50 rounded-xl p-6 hover:shadow-lg transition-all transform hover:-translate-y-2 h-72 aspect-square flex flex-col justify-between">
                    <!-- Icon removed -->
                    <h3 class="text-xl font-semibold text-gray-800 text-center mb-3">Electronic Waste</h3>
                    <p class="text-gray-600 text-center">Old electronics, batteries, and electrical items</p>
                    <div class="bg-purple-100 rounded-lg p-3 mt-2 flex items-center">
                        <span class="text-2xl mr-2">💡</span>
                        <span class="text-gray-700 text-sm">Donate working electronics or use designated e-waste collection points.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="my-4 flex justify-center">
        <div class="w-3/4 border-t-4 border-green-300"></div>
    </div>

    <!-- About Section -->
    <!-- About Section -->
<section id="about" class="py-20 bg-white">
    <div class="container mx-auto px-8 lg:px-32">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-extrabold text-gray-800 mb-4">About TrashSmart</h2>
        </div>
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <p class="text-lg text-gray-600 mb-6 text-justify leading-relaxed">
                    TrashSmart is dedicated to revolutionizing waste management for a cleaner, greener future. Our mission is to empower communities with smart solutions that make recycling and waste disposal easy, efficient, and environmentally friendly.
                </p>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Our Mission</h3>
                    <p class="text-gray-600 text-justify">To provide innovative waste management services that promote sustainability and community well-being.</p>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600 mb-2">10K+</div>
                        <div class="text-gray-600">Happy Citizens</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600 mb-2">25K+</div>
                        <div class="text-gray-600">Pickups Completed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600 mb-2">50+</div>
                        <div class="text-gray-600">Partner Organizations</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600 mb-2">95%</div>
                        <div class="text-gray-600">Satisfaction Rate</div>
                    </div>
                </div>
            </div>
            <div class="text-center">
                <img src="images/about-us.jpg" alt="About TrashSmart" class="shadow-lg w-full max-w-xl h-[24rem] object-cover mx-auto">
            </div>
        </div>
    </div>
</section>
    <div class="my-4 flex justify-center">
        <div class="w-3/4 border-t-4 border-green-300"></div>
    </div>

    <!-- Contact Section -->
    <!-- Contact Section -->
    <section id="contact" class="pt-8 pb-20 bg-gray-50">
        <div class="container mx-auto px-4 py-12">
            <div class="text-center mb-10">
                <h2 class="text-4xl font-extrabold text-gray-800 mb-4">Contact TrashSmart</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Have questions or need assistance? Get in touch with our team.</p>
            </div>
            <div class="flex flex-col lg:flex-row gap-12 items-center bg-white rounded-2xl shadow-lg p-6 w-full mx-auto mt-12">
                <div class="flex-1 max-w-xs px-4 text-left flex flex-col justify-center">
                    <div class="space-y-4">
                        <div class="flex items-start space-x-2">
                            <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-white text-base"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-800 mb-1">Address</h3>
                                <p class="text-gray-600 text-sm"><?php echo nl2br(htmlspecialchars($company_settings['address'])); ?></p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-2">
                            <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-phone text-white text-base"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-800 mb-1">Phone</h3>
                                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($company_settings['phone']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-2">
                            <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-envelope text-white text-base"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-800 mb-1">Email</h3>
                                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($company_settings['email']); ?></p>
                            </div>
                        </div>
                        <?php if ($company_settings['social_facebook'] || $company_settings['social_twitter'] || $company_settings['social_instagram'] || $company_settings['social_linkedin']): ?>
                        <div class="border-t pt-4">
                            <h3 class="text-base font-semibold text-gray-800 mb-2">Follow Us</h3>
                            <div class="flex space-x-2">
                                <?php if ($company_settings['social_facebook']): ?>
                                <a href="<?php echo htmlspecialchars($company_settings['social_facebook']); ?>" 
                                   class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white hover:bg-blue-700 transition-colors">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($company_settings['social_twitter']): ?>
                                <a href="<?php echo htmlspecialchars($company_settings['social_twitter']); ?>" 
                                   class="w-8 h-8 bg-blue-400 rounded-lg flex items-center justify-center text-white hover:bg-blue-500 transition-colors">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($company_settings['social_instagram']): ?>
                                <a href="<?php echo htmlspecialchars($company_settings['social_instagram']); ?>" 
                                   class="w-8 h-8 bg-pink-600 rounded-lg flex items-center justify-center text-white hover:bg-pink-700 transition-colors">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($company_settings['social_linkedin']): ?>
                                <a href="<?php echo htmlspecialchars($company_settings['social_linkedin']); ?>" 
                                   class="w-8 h-8 bg-blue-800 rounded-lg flex items-center justify-center text-white hover:bg-blue-900 transition-colors">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-1 bg-white rounded-xl p-8 shadow-md flex items-center justify-center">
                    <?php if (isset($_SESSION['contact_success'])): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($_SESSION['contact_success']); unset($_SESSION['contact_success']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['contact_error'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($_SESSION['contact_error']); unset($_SESSION['contact_error']); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="space-y-6 w-full max-w-md mx-auto">
                        <input type="hidden" name="action" value="contact">
                        <div>
                            <label for="contactName" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                            <input type="text" id="contactName" name="name" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label for="contactEmail" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="contactEmail" name="email" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label for="contactMessage" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                            <textarea id="contactMessage" name="message" rows="4" required 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all resize-none"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition-colors font-medium">
                            <i class="fas fa-paper-plane mr-2"></i>Send Message
                        </button>
                    </form>
                </div>
                <div class="flex-1 relative w-full h-[28rem] lg:h-[34rem] flex items-center justify-center">
                    <img src="images/contact-waste-collection.jpg" alt="Contact Waste Collection" class="shadow-2xl w-full h-full object-cover rounded-2xl">
                </div>
            </div>
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Login</h2>
                <button id="closeLoginModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <?php if ($login_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="login">
                
                <div>
                    <label for="loginEmail" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" id="loginEmail" name="email" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                </div>
                
                <div>
                    <label for="loginPassword" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="loginPassword" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                </div>
                
                <div class="flex items-center">
                    <label class="flex items-center">
                        <input type="checkbox" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                </div>
                
                <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition-colors font-medium">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-gray-600">Don't have an account? 
                    <button id="switchToSignup" class="text-green-600 hover:text-green-700 font-medium">Sign up</button>
                </p>
            </div>
        </div>
    </div>

    <!-- Sign Up Modal -->
    <div id="signupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Sign Up</h2>
                <button id="closeSignupModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <?php if ($register_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($register_error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($register_success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($register_success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Citizen Registration Form -->
            <div id="citizenForm" class="space-y-6">
                <div class="mb-6">
                    <h3 class="text-xl font-semibold text-gray-800 text-center">Citizen Registration</h3>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="citizenFirstName" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="citizenFirstName" name="firstName" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label for="citizenLastName" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="citizenLastName" name="lastName" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        </div>
                    </div>
                    
                    <div>
                        <label for="citizenDOB" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" id="citizenDOB" name="dateOfBirth" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label for="citizenDistrict" class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <input type="text" id="citizenDistrict" name="district" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label for="citizenAddress" class="block text-sm font-medium text-gray-700 mb-1">Home Address</label>
                        <textarea id="citizenAddress" name="address" rows="2" required 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all resize-none"></textarea>
                    </div>
                    
                    <div>
                        <label for="citizenTown" class="block text-sm font-medium text-gray-700 mb-1">Nearest Town</label>
                        <input type="text" id="citizenTown" name="nearestTown" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label for="citizenPhone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" id="citizenPhone" name="phone" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label for="citizenEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="citizenEmail" name="email" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label for="citizenPassword" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
               <input type="password" id="citizenPassword" name="password" required minlength="6"
                   pattern="^(?=.*[a-zA-Z])(?=.*[0-9]).*$"
                               title="Password must be at least 6 characters long and contain both letters and numbers"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label for="citizenConfirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" id="citizenConfirmPassword" name="confirmPassword" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                    
                    <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition-colors font-medium">
                        <i class="fas fa-user-plus mr-2"></i>Register as Citizen
                    </button>
                </form>
            </div>
            
            <div class="mt-6 text-center">
                <p class="text-gray-600">Already have an account? 
                    <button id="switchToLogin" class="text-green-600 hover:text-green-700 font-medium">Login</button>
                </p>
            </div>
        </div>
    </div>

    <!-- Custom JavaScript -->
    <script src="js/main.js"></script>
    
    <?php if ($login_error): ?>
    <script>
        // Show login modal if there's a login error
        document.getElementById('loginModal').classList.remove('hidden');
    </script>
    <?php endif; ?>
    
    <?php if ($register_error): ?>
    <script>
        // Show signup modal if there's a registration error
        document.getElementById('signupModal').classList.remove('hidden');
    </script>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-green-100 text-black py-6 mt-8">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-6">
                <div class="col-span-2">
                    <div class="flex items-center mb-3">
                        <?php if (!empty($company_settings['company_logo']) && file_exists($company_settings['company_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($company_settings['company_logo']) . '?v=' . time(); ?>" alt="<?php echo htmlspecialchars($company_settings['company_name']); ?> Logo" class="h-8 w-auto mr-2">
                        <?php else: ?>
                            <img src="images/trash-smart-logo.jpg" alt="<?php echo htmlspecialchars($company_settings['company_name']); ?> Logo" class="h-8 w-auto mr-2">
                        <?php endif; ?>
                    </div>
                    <p class="text-black mb-3 text-sm text-justify leading-relaxed max-w-md" style="max-width:20rem;"><?php echo htmlspecialchars($company_settings['about_us']); ?></p>
                    <div class="flex space-x-4 mt-1">
                        <a href="#" class="text-black hover:text-white transition-colors">
                            <i class="fab fa-facebook text-2xl"></i>
                        </a>
                        <a href="#" class="text-black hover:text-white transition-colors">
                            <i class="fab fa-twitter text-2xl"></i>
                        </a>
                        <a href="#" class="text-black hover:text-white transition-colors">
                            <i class="fab fa-instagram text-2xl"></i>
                        </a>
                        <a href="#" class="text-black hover:text-white transition-colors">
                            <i class="fab fa-linkedin text-2xl"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="text-base font-semibold mb-3">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-black hover:text-white transition-colors">Home</a></li>
                        <li><a href="#about" class="text-black hover:text-white transition-colors">About</a></li>
                        <li><a href="#tips" class="text-black hover:text-white transition-colors">Tips</a></li>
                        <li><a href="#contact" class="text-black hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-base font-semibold mb-3">Services</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-black hover:text-white transition-colors">Waste Collection</a></li>
                        <li><a href="#" class="text-black hover:text-white transition-colors">Recycling</a></li>
                        <li><a href="#" class="text-black hover:text-white transition-colors">Composting</a></li>
                        <li><a href="#" class="text-black hover:text-white transition-colors">Consultation</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-green-800 mt-6 pt-4 text-center">
                <p class="text-black text-sm">&copy; 2025 TrashSmart. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
