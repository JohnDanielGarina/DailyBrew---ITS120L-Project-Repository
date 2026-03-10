<?php
/**
 * DailyBrew - Task Management Handler
 * Handles CRUD operations for tasks
 */

require_once __DIR__ . '/config.php';

// Require login for all operations
if (!isLoggedIn()) {
    errorResponse('Authentication required', 401);
}

$userId = getCurrentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        createTask();
        break;
    case 'read':
        getTasks();
        break;
    case 'update':
        updateTask();
        break;
    case 'delete':
        deleteTask();
        break;
    case 'calculatePriority':
        calculatePriority();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Create a new task
 */
function createTask() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $dueDate = $input['due_date'] ?? '';
    
    // Validation
    if (empty($title)) {
        errorResponse('Task title is required', 400);
    }
    
    if (empty($dueDate)) {
        errorResponse('Due date is required', 400);
    }
    
    // Validate date format
    $dueDateObj = DateTime::createFromFormat('Y-m-d', $dueDate);
    if (!$dueDateObj) {
        errorResponse('Invalid date format. Use YYYY-MM-DD', 400);
    }
    
    // Calculate complexity from description
    $complexity = calculateComplexity($description, $title);
    
    // Calculate priority based on due date and complexity
    $priority = calculateTaskPriority($dueDate, $complexity);
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, complexity) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $description, $dueDate, $priority, $complexity]);
        
        $taskId = $db->lastInsertId();
        
        successResponse([
            'id' => $taskId,
            'title' => $title,
            'description' => $description,
            'due_date' => $dueDate,
            'priority' => $priority,
            'complexity' => $complexity
        ], 'Task created successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to create task: ' . $e->getMessage(), 500);
    }
}

/**
 * Get all tasks for the user
 */
function getTasks() {
    global $userId;
    
    $status = $_GET['status'] ?? null;
    
    try {
        $db = getDB();
        
        $sql = "SELECT * FROM tasks WHERE user_id = ?";
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY due_date ASC, priority DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        
        successResponse($tasks);
        
    } catch (Exception $e) {
        errorResponse('Failed to get tasks: ' . $e->getMessage(), 500);
    }
}

/**
 * Update a task
 */
function updateTask() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $taskId = $input['id'] ?? null;
    $title = $input['title'] ?? null;
    $description = $input['description'] ?? null;
    $dueDate = $input['due_date'] ?? null;
    $priority = $input['priority'] ?? null;
    $complexity = $input['complexity'] ?? null;
    $status = $input['status'] ?? null;
    
    if (!$taskId) {
        errorResponse('Task ID is required', 400);
    }
    
    // Verify task belongs to user
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        
        if (!$stmt->fetch()) {
            errorResponse('Task not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if ($title !== null) {
            $updates[] = 'title = ?';
            $params[] = $title;
        }
        if ($description !== null) {
            $updates[] = 'description = ?';
            $params[] = $description;
        }
        if ($dueDate !== null) {
            $updates[] = 'due_date = ?';
            $params[] = $dueDate;
        }
        if ($priority !== null) {
            $updates[] = 'priority = ?';
            $params[] = $priority;
        }
        if ($complexity !== null) {
            $updates[] = 'complexity = ?';
            $params[] = $complexity;
        }
        if ($status !== null) {
            $updates[] = 'status = ?';
            $params[] = $status;
        }
        
        if (empty($updates)) {
            errorResponse('No fields to update', 400);
        }
        
        $params[] = $taskId;
        $params[] = $userId;
        
        $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        successResponse(null, 'Task updated successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to update task: ' . $e->getMessage(), 500);
    }
}

/**
 * Delete a task
 */
function deleteTask() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = $input['id'] ?? null;
    
    if (!$taskId) {
        errorResponse('Task ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        
        if ($stmt->rowCount() === 0) {
            errorResponse('Task not found', 404);
        }
        
        successResponse(null, 'Task deleted successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to delete task: ' . $e->getMessage(), 500);
    }
}

/**
 * Calculate priority based on due date and complexity
 */
function calculateTaskPriority($dueDate, $complexity) {
    $now = new DateTime();
    $due = new DateTime($dueDate);
    $daysUntilDue = (int)$due->diff($now)->format('%r%a');
    
    // Date-based priority
    $datePriority = 'low';
    if ($daysUntilDue < 0) {
        // Past due - highest priority
        $datePriority = 'high';
    } elseif ($daysUntilDue <= 2) {
        // Due within 2 days
        $datePriority = 'high';
    } elseif ($daysUntilDue <= 7) {
        // Due within a week
        $datePriority = 'medium';
    }
    
    // Complexity-based adjustment
    $complexityPriority = 'low';
    if ($complexity >= 4) {
        $complexityPriority = 'high';
    } elseif ($complexity >= 3) {
        $complexityPriority = 'medium';
    }
    
    // Combine priorities (highest wins)
    $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
    
    $dateScore = $priorities[$datePriority] ?? 2;
    $complexityScore = $priorities[$complexityPriority] ?? 1;
    $totalScore = $dateScore + $complexityScore;
    
    if ($totalScore >= 5) {
        return 'high';
    } elseif ($totalScore >= 3) {
        return 'medium';
    }
    return 'low';
}

/**
 * Calculate complexity based on description and title
 */
function calculateComplexity($description, $title) {
    $complexity = 2; // Default complexity
    
    // Count words in description
    $wordCount = str_word_count(strip_tags($description));
    
    // Adjust based on word count
    if ($wordCount > 2000) {
        $complexity = 5;
    } elseif ($wordCount > 1000) {
        $complexity = 4;
    } elseif ($wordCount > 500) {
        $complexity = 3;
    } elseif ($wordCount > 100) {
        $complexity = 2;
    }
    
    // Check for common high-complexity keywords in title
    $highComplexityKeywords = ['exam', 'final', 'midterm', 'thesis', 'project', 'research', 'dissertation'];
    $mediumComplexityKeywords = ['quiz', 'assignment', 'report', 'presentation', 'paper', 'essay'];
    
    $titleLower = strtolower($title);
    
    foreach ($highComplexityKeywords as $keyword) {
        if (strpos($titleLower, $keyword) !== false) {
            $complexity = min(5, $complexity + 2);
            break;
        }
    }
    
    foreach ($mediumComplexityKeywords as $keyword) {
        if (strpos($titleLower, $keyword) !== false) {
            $complexity = min(4, $complexity + 1);
            break;
        }
    }
    
    return $complexity;
}

/**
 * Calculate and return priority without saving
 */
function calculatePriority() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $dueDate = $input['due_date'] ?? '';
    
    if (empty($dueDate)) {
        errorResponse('Due date is required', 400);
    }
    
    $complexity = calculateComplexity($description, $title);
    $priority = calculateTaskPriority($dueDate, $complexity);
    
    successResponse([
        'priority' => $priority,
        'complexity' => $complexity,
        'word_count' => str_word_count(strip_tags($description))
    ]);
}

