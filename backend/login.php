<?php
/**
 * TrashSmart Login Page
 * Handles user login with traditional form submission
 */

session_start();
// require_once 'config/database.php';

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Connect to database
            $db = new Database();
            
            // Find user by email
            $user = $db->fetch("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                // Prefer explicit first/last name; fall back to user_name if present
                $fullName = trim((($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
                $_SESSION['user_name'] = $fullName !== '' ? $fullName : ($user['user_name'] ?? '');
                $_SESSION['status'] = $user['status'] ?? 'active';
                
                // Update last login
                $db->query("UPDATE users SET last_login = NOW() WHERE user_id = ?", [$user['user_id']]);
                
                // Redirect based on user type (like your other project)
                switch ($_SESSION['user_type']) {
                    case "admin":
                        header("Location: ../frontend/TrashSmart-Project/admin-dashboard.php");
                        exit();
                    case "citizen":
                        header("Location: ../frontend/TrashSmart-Project/citizen-profile.php");
                        exit();
                    default:
                        $error = 'Invalid user type.';
                        break;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TrashSmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-brown-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-sign-in-alt text-green-600 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Welcome Back</h1>
            <p class="text-gray-600 mt-2">Sign in to your TrashSmart account</p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                       placeholder="Enter your email">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" id="password" name="password" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                       placeholder="Enter your password">
            </div>
            
            <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition-colors font-medium mt-6">
                <i class="fas fa-sign-in-alt mr-2"></i>Sign In
            </button>
        </form>

        <!-- Sign Up Link -->
        <div class="mt-6 text-center">
            <p class="text-gray-600">Don't have an account? 
                <a href="register.php" class="text-green-600 hover:text-green-700 font-medium">Sign up here</a>
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
