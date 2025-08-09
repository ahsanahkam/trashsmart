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
        } elseif (!preg_match('/[a-zA-Z]/', $password)) {
            $error = 'Password must contain at least one letter.';
        } elseif (!preg_match('/\d/', $password)) {
            $error = 'Password must contain at least one number.';
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
            // CRITICAL: Hash password FIRST before any database operations
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Verify the hash was created correctly
            if (!$hashedPassword || strlen($hashedPassword) < 60) {
                throw new Exception('Password hashing failed');
            }
            
            // Connect to database using direct mysqli for reliability
            $conn = new mysqli('localhost', 'root', '', 'trashsmart');
            if ($conn->connect_error) {
                throw new Exception('Database connection failed: ' . $conn->connect_error);
            }
            
            // Check if email already exists
            $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'This email is already registered. Please use a different email or login.';
                $checkStmt->close();
                $conn->close();
            } else {
                $checkStmt->close();
                
                // Insert new user with HASHED password
                $insertStmt = $conn->prepare(
                    "INSERT INTO users (first_name, last_name, email, password, phone, date_of_birth, 
                     district, nearest_town, address, user_type, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'citizen', 'active', NOW())"
                );
                
                if (!$insertStmt) {
                    throw new Exception('Failed to prepare insert statement: ' . $conn->error);
                }
                
                $insertStmt->bind_param(
                    "sssssssss",
                    $firstName,
                    $lastName,
                    $email,
                    $hashedPassword,  // THIS IS THE HASHED PASSWORD
                    $cleanPhone,
                    $dateOfBirth,
                    $district,
                    $nearestTown,
                    $address
                );
                
                if ($insertStmt->execute()) {
                    $userId = $conn->insert_id;

                    // Auto-login after successful registration
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_type'] = 'citizen';
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['status'] = 'active';
                    
                    $insertStmt->close();
                    $conn->close();
                    
                    // Redirect to citizen profile
                    header('Location: ../frontend/TrashSmart-Project/citizen-profile.php');
                    exit;
                } else {
                    throw new Exception('Failed to insert user: ' . $insertStmt->error);
                }
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
        
        /* Password strength indicator styles */
        .password-requirement {
            padding: 2px 0;
            transition: color 0.3s ease;
        }
        
        .requirement-met {
            color: #10b981 !important;
        }
        
        .requirement-unmet {
            color: #ef4444 !important;
        }
        
        /* Input field focus states */
        input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .input-valid {
            border-color: #10b981 !important;
            background-color: #f0fdf4;
        }
        
        .input-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2;
        }
        
        /* Feedback animations */
        .feedback-enter {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
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
          <input type="password" id="password" name="password" required minlength="6"
              pattern="^(?=.*[a-zA-Z])(?=.*[0-9]).*$"
              title="Password must be at least 6 characters long and contain both letters and numbers" autocomplete="new-password"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters with letters and numbers</p>
                <div id="password-feedback" class="mt-1 text-xs hidden"></div>
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

    <script>
        // Enhanced client-side password validation with real-time feedback
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirmPassword');
            const passwordFeedback = document.getElementById('password-feedback');
            
            function validatePassword() {
                const password = passwordField.value;
                const confirmPassword = confirmPasswordField.value;
                
                // Password strength validation (updated requirements)
                const hasLetters = /[a-zA-Z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasMinLength = password.length >= 6;
                
                // Clear existing feedback
                passwordFeedback.innerHTML = '';
                passwordFeedback.className = 'mt-1 text-xs';
                
                if (password.length > 0) {
                    let feedbackMessages = [];
                    let isValid = true;
                    
                    // Check minimum length
                    if (!hasMinLength) {
                        feedbackMessages.push('❌ At least 6 characters');
                        isValid = false;
                    } else {
                        feedbackMessages.push('✅ Length requirement met');
                    }
                    
                    // Check for letters
                    if (!hasLetters) {
                        feedbackMessages.push('❌ Must contain letters (a-z, A-Z)');
                        isValid = false;
                    } else {
                        feedbackMessages.push('✅ Contains letters');
                    }
                    
                    // Check for numbers
                    if (!hasNumbers) {
                        feedbackMessages.push('❌ Must contain numbers (0-9)');
                        isValid = false;
                    } else {
                        feedbackMessages.push('✅ Contains numbers');
                    }
                    
                    // Display feedback
                    passwordFeedback.innerHTML = feedbackMessages.join('<br>');
                    passwordFeedback.classList.remove('hidden');
                    
                    if (isValid) {
                        passwordFeedback.className += ' text-green-600';
                        passwordField.style.borderColor = '#10b981';
                    } else {
                        passwordFeedback.className += ' text-red-600';
                        passwordField.style.borderColor = '#ef4444';
                    }
                } else {
                    passwordFeedback.classList.add('hidden');
                    passwordField.style.borderColor = '#d1d5db';
                }
                
                // Confirm password validation
                const confirmFeedback = confirmPasswordField.parentNode.querySelector('.confirm-feedback');
                if (confirmFeedback) {
                    confirmFeedback.remove();
                }
                
                if (confirmPassword.length > 0) {
                    const confirmDiv = document.createElement('div');
                    confirmDiv.className = 'confirm-feedback mt-1 text-xs';
                    
                    if (password === confirmPassword) {
                        confirmDiv.innerHTML = '✅ Passwords match';
                        confirmDiv.className += ' text-green-600';
                        confirmPasswordField.style.borderColor = '#10b981';
                    } else {
                        confirmDiv.innerHTML = '❌ Passwords do not match';
                        confirmDiv.className += ' text-red-600';
                        confirmPasswordField.style.borderColor = '#ef4444';
                    }
                    
                    confirmPasswordField.parentNode.appendChild(confirmDiv);
                } else {
                    confirmPasswordField.style.borderColor = '#d1d5db';
                }
            }
            
            // Add event listeners for real-time validation
            passwordField.addEventListener('input', validatePassword);
            passwordField.addEventListener('focus', validatePassword);
            confirmPasswordField.addEventListener('input', validatePassword);
            confirmPasswordField.addEventListener('focus', validatePassword);
            
            // Show password requirements on focus
            passwordField.addEventListener('focus', function() {
                if (passwordField.value.length === 0) {
                    passwordFeedback.innerHTML = 'Password Requirements:<br>• Minimum 6 characters<br>• At least one letter (a-z, A-Z)<br>• At least one number (0-9)';
                    passwordFeedback.className = 'mt-1 text-xs text-blue-600';
                    passwordFeedback.classList.remove('hidden');
                }
            });
            
            // Hide empty feedback on blur if password is empty
            passwordField.addEventListener('blur', function() {
                if (passwordField.value.length === 0) {
                    passwordFeedback.classList.add('hidden');
                }
            });
        });
    </script>

</body>
</html>
