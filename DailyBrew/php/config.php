<?php
/**
 * DailyBrew Configuration File
 * Contains database and API settings
 */

// Database Configuration (SQLite)
define('DB_PATH', __DIR__ . '/../database.sqlite');

// Google Gemini API Configuration
define('GEMINI_API_KEY', 'AIzaSyDPWNWnNVBoX-FRq9qZbHOQe17wgf2OafM');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Timezone
date_default_timezone_set('Asia/Manila');

// CORS Headers (for development)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Get database connection
 * @return PDO
 */
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            // Create database directory if not exists
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Initialize database tables
            initializeDatabase($db);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    return $db;
}

/**
 * Initialize database tables
 * @param PDO $db
 */
function initializeDatabase($db) {
    // Users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Academic schedule table
    $db->exec("CREATE TABLE IF NOT EXISTS academic_schedule (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        day_of_week INTEGER NOT NULL, -- 0=Sunday, 1=Monday, etc.
        start_time TEXT NOT NULL, -- HH:MM format
        end_time TEXT NOT NULL,
        title TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Tasks table
    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        due_date DATE NOT NULL,
        priority TEXT DEFAULT 'medium', -- high, medium, low
        complexity INTEGER DEFAULT 2, -- 1-5 scale
        status TEXT DEFAULT 'pending', -- pending, in_progress, completed
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Study blocks table
    $db->exec("CREATE TABLE IF NOT EXISTS study_blocks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        task_id INTEGER,
        title TEXT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        created_by TEXT DEFAULT 'ai', -- ai or user
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
    )");
    
    // User preferences table
    $db->exec("CREATE TABLE IF NOT EXISTS preferences (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        study_profile TEXT DEFAULT 'seamless', -- early_crammer, seamless, late_crammer
        earliest_start TEXT DEFAULT '08:00',
        latest_end TEXT DEFAULT '22:00',
        block_duration INTEGER DEFAULT 30, -- minutes
        has_seen_tour INTEGER DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tasks_user ON tasks(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_study_blocks_user ON study_blocks(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_academic_schedule_user ON academic_schedule(user_id)");
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Require login (redirect to login page if not logged in)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.html');
        exit;
    }
}

/**
 * Send JSON response
 * @param mixed $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get error response
 * @param string $message
 * @param int $statusCode
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse(['error' => true, 'message' => $message], $statusCode);
}

/**
 * Get success response
 * @param mixed $data
 * @param string $message
 */
function successResponse($data = null, $message = 'Success') {
    jsonResponse([
        'error' => false,
        'message' => $message,
        'data' => $data
    ]);
}

