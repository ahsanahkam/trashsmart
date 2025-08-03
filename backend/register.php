<?php
/**
 * TrashSmart Registration Page
 * Handles citizen registration with traditional form submission
 */

session_start();
require_once 'config/database.php';

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $dateOfBirth = $_POST['dateOfBirth'] ?? '';
    $district = trim($_POST['district'] ?? '');
    $nearestTown = trim($_POST['nearestTown'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validate required fields
    $required_fields = [
        'firstName' => 'First Name',
        'lastName' => 'Last Name', 
        'email' => 'Email',
        'password' => 'Password',
        'confirmPassword' => 'Confirm Password',
        'phone' => 'Phone Number',
        'dateOfBirth' => 'Date of Birth',
        'district' => 'District',
        'nearestTown' => 'Nearest Town',
        'address' => 'Address'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty($$field)) {
            $error = "$label is required.";
            break;
        }
    }
    
    // Additional validations
    if (!$error) {
        if ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            // Validate phone number
            $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
            if (strlen($cleanPhone) < 10) {
                $error = 'Please enter a valid phone number.';
            }
        }
    }
    
    // If no errors, process registration
    if (!$error) {
        try {
            // Connect to database
            $db = new Database();
            
            // Check if email already exists
            $existingUser = $db->fetch("SELECT user_id FROM users WHERE email = ?", [$email]);
            if ($existingUser) {
                $error = 'This email is already registered. Please use a different email or login.';
            } else {
                // Insert new user with plain text password
                $db->query(
                    "INSERT INTO users (first_name, last_name, email, password, phone, date_of_birth, 
                     district, nearest_town, address, user_type, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'citizen', 'active', NOW())",
                    [
                        $firstName,
                        $lastName,
                        $email,
                        $password,
                        $cleanPhone,
                        $dateOfBirth,
                        $district,
                        $nearestTown,
                        $address
                    ]
                );
                
                $userId = $db->lastInsertId();
                
                // Auto-login after successful registration
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_type'] = 'citizen';
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['status'] = 'active';
                
                // Redirect to citizen profile
                header('Location: ../frontend/TrashSmart-Project/citizen-profile.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - TrashSmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-brown-50 min-h-screen py-8 px-4">
    
    <div class="max-w-md mx-auto bg-white rounded-2xl shadow-xl p-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-user-plus text-green-600 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Join TrashSmart</h1>
            <p class="text-gray-600 mt-2">Create your citizen account</p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="POST" action="" class="space-y-4">
            <!-- Name Fields -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="firstName" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" id="firstName" name="firstName" required 
                           value="<?php echo htmlspecialchars($firstName ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                </div>
                <div>
                    <label for="lastName" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" id="lastName" name="lastName" required 
                           value="<?php echo htmlspecialchars($lastName ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                </div>
            </div>
            
            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>
            
            <!-- Password Fields -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" id="password" name="password" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
            </div>
            
            <div>
                <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>
            
            <!-- Phone -->
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="tel" id="phone" name="phone" required 
                       value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>
            
            <!-- Date of Birth -->
            <div>
                <label for="dateOfBirth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                <input type="date" id="dateOfBirth" name="dateOfBirth" required 
                       value="<?php echo htmlspecialchars($dateOfBirth ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>
            
            <!-- Location Fields -->
            <div>
                <label for="district" class="block text-sm font-medium text-gray-700 mb-1">District</label>
                <input type="text" id="district" name="district" required 
                       value="<?php echo htmlspecialchars($district ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>
            
            <div>
                <label for="nearestTown" class="block text-sm font-medium text-gray-700 mb-1">Nearest Town</label>
                <input type="text" id="nearestTown" name="nearestTown" required 
                       value="<?php echo htmlspecialchars($nearestTown ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>
            
            <!-- Address -->
            <div>
                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Home Address</label>
                <textarea id="address" name="address" rows="2" required 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all resize-none"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition-colors font-medium mt-6">
                <i class="fas fa-user-plus mr-2"></i>Create Account
            </button>
        </form>

        <!-- Login Link -->
        <div class="mt-6 text-center">
            <p class="text-gray-600">Already have an account? 
                <a href="login.php" class="text-green-600 hover:text-green-700 font-medium">Login here</a>
            </p>
        </div>

        <!-- Back to Home -->
        <div class="mt-4 text-center">
            <a href="../frontend/TrashSmart-Project/index.php" class="text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i>Back to Home
            </a>
        </div>
    </div>

</body>
</html>
