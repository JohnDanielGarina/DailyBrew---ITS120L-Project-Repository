<?php
/**
 * DailyBrew - Authentication Handler
 * Handles user login, signup, and logout
 */

require_once __DIR__ . '/config.php';

// Handle request based on action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'signup':
        handleSignup();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkAuth();
        break;
    case 'getUser':
        getUserInfo();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Handle user registration
 */
function handleSignup() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validation checks
    $errors = [];
    
    if (empty($firstName)) {
        $errors[] = 'First name is required';
    } elseif (strlen($firstName) > 50) {
        $errors[] = 'First name must be less than 50 characters';
    }
    
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    } elseif (strlen($lastName) > 50) {
        $errors[] = 'Last name must be less than 50 characters';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!empty($errors)) {
        errorResponse(implode('. ', $errors), 400);
    }
    
    try {
        $db = getDB();
        
        // Check if user already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE first_name = ? AND last_name = ?");
        $stmt->execute([$firstName, $lastName]);
        
        if ($stmt->fetch()) {
            errorResponse('User with this name already exists', 400);
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $passwordHash]);
        
        $userId = $db->lastInsertId();
        
        // Create default preferences for new user
        $stmt = $db->prepare("INSERT INTO preferences (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        
        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['first_name'] = $firstName;
        
        successResponse([
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName
        ], 'Registration successful!');
        
    } catch (Exception $e) {
        errorResponse('Registration failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle user login
 */
function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($password)) {
        errorResponse('Please fill in all fields', 400);
    }
    
    try {
        $db = getDB();
        
        // Find user
        $stmt = $db->prepare("SELECT * FROM users WHERE first_name = ? AND last_name = ?");
        $stmt->execute([$firstName, $lastName]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            errorResponse('Invalid name or password', 401);
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        
        // Get preferences
        $stmt = $db->prepare("SELECT * FROM preferences WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $preferences = $stmt->fetch();
        
        successResponse([
            'user_id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'has_seen_tour' => $preferences['has_seen_tour'] ?? 0
        ], 'Login successful!');
        
    } catch (Exception $e) {
        errorResponse('Login failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    // Destroy session
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    
    session_destroy();
    
    successResponse(null, 'Logout successful!');
}

/**
 * Check if user is authenticated
 */
function checkAuth() {
    if (isLoggedIn()) {
        successResponse([
            'authenticated' => true,
            'user_id' => getCurrentUserId(),
            'first_name' => $_SESSION['first_name']
        ]);
    } else {
        successResponse(['authenticated' => false]);
    }
}

/**
 * Get current user info
 */
function getUserInfo() {
    if (!isLoggedIn()) {
        errorResponse('Not authenticated', 401);
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, first_name, last_name, created_at FROM users WHERE id = ?");
        $stmt->execute([getCurrentUserId()]);
        $user = $stmt->fetch();
        
        // Get preferences
        $stmt = $db->prepare("SELECT * FROM preferences WHERE user_id = ?");
        $stmt->execute([getCurrentUserId()]);
        $preferences = $stmt->fetch();
        
        successResponse([
            'user' => $user,
            'preferences' => $preferences
        ]);
        
    } catch (Exception $e) {
        errorResponse('Failed to get user info: ' . $e->getMessage(), 500);
    }
}

