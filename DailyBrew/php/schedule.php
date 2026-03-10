<?php
/**
 * DailyBrew - Academic Schedule Handler
 * Handles CRUD operations for academic schedule (time blocks that AI cannot use)
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
        createSchedule();
        break;
    case 'read':
        getSchedules();
        break;
    case 'update':
        updateSchedule();
        break;
    case 'delete':
        deleteSchedule();
        break;
    case 'getWeek':
        getWeekSchedule();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Create a new academic schedule entry
 */
function createSchedule() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $dayOfWeek = $input['day_of_week'] ?? null; // 0-6 (Sunday-Saturday)
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $title = trim($input['title'] ?? 'Busy');
    
    // Validation
    if ($dayOfWeek === null || !is_numeric($dayOfWeek) || $dayOfWeek < 0 || $dayOfWeek > 6) {
        errorResponse('Valid day of week (0-6) is required', 400);
    }
    
    if (empty($startTime) || empty($endTime)) {
        errorResponse('Start time and end time are required', 400);
    }
    
    // Validate time format (HH:MM)
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $startTime)) {
        errorResponse('Invalid start time format. Use HH:MM', 400);
    }
    
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $endTime)) {
        errorResponse('Invalid end time format. Use HH:MM', 400);
    }
    
    // Ensure end time is after start time
    if ($startTime >= $endTime) {
        errorResponse('End time must be after start time', 400);
    }
    
    try {
        $db = getDB();
        
        // Check for overlapping schedules on the same day
        $stmt = $db->prepare("
            SELECT id FROM academic_schedule 
            WHERE user_id = ? AND day_of_week = ? 
            AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))
        ");
        $stmt->execute([$userId, $dayOfWeek, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime]);
        
        if ($stmt->fetch()) {
            errorResponse('This time slot overlaps with an existing schedule', 400);
        }
        
        $stmt = $db->prepare("INSERT INTO academic_schedule (user_id, day_of_week, start_time, end_time, title) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $dayOfWeek, $startTime, $endTime, $title]);
        
        $scheduleId = $db->lastInsertId();
        
        successResponse([
            'id' => $scheduleId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'title' => $title
        ], 'Schedule added successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to add schedule: ' . $e->getMessage(), 500);
    }
}

/**
 * Get all academic schedules for the user
 */
function getSchedules() {
    global $userId;
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM academic_schedule WHERE user_id = ? ORDER BY day_of_week, start_time");
        $stmt->execute([$userId]);
        $schedules = $stmt->fetchAll();
        
        successResponse($schedules);
        
    } catch (Exception $e) {
        errorResponse('Failed to get schedules: ' . $e->getMessage(), 500);
    }
}

/**
 * Get schedules for a specific week
 */
function getWeekSchedule() {
    global $userId;
    
    $startDate = $_GET['start'] ?? null;
    $endDate = $_GET['end'] ?? null;
    
    if (!$startDate || !$endDate) {
        errorResponse('Start and end dates are required', 400);
    }
    
    try {
        $db = getDB();
        
        // Get recurring schedules
        $stmt = $db->prepare("SELECT * FROM academic_schedule WHERE user_id = ? ORDER BY day_of_week, start_time");
        $stmt->execute([$userId]);
        $schedules = $stmt->fetchAll();
        
        // Convert to calendar events
        $events = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        // Iterate through each day in the range
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        
        foreach ($period as $date) {
            $dayOfWeek = (int)$date->format('w');
            
            foreach ($schedules as $schedule) {
                if ($schedule['day_of_week'] == $dayOfWeek) {
                    $eventStart = clone $date;
                    $eventStart->modify($schedule['start_time']);
                    
                    $eventEnd = clone $date;
                    $eventEnd->modify($schedule['end_time']);
                    
                    $events[] = [
                        'id' => 'sched_' . $schedule['id'],
                        'title' => $schedule['title'] . ' (Occupied)',
                        'start' => $eventStart->format('Y-m-d H:i:s'),
                        'end' => $eventEnd->format('Y-m-d H:i:s'),
                        'allDay' => false,
                        'display' => 'background',
                        'backgroundColor' => '#6c757d',
                        'extendedType' => 'academic'
                    ];
                }
            }
        }
        
        successResponse($events);
        
    } catch (Exception $e) {
        errorResponse('Failed to get week schedule: ' . $e->getMessage(), 500);
    }
}

/**
 * Update an academic schedule entry
 */
function updateSchedule() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $scheduleId = $input['id'] ?? null;
    $dayOfWeek = $input['day_of_week'] ?? null;
    $startTime = $input['start_time'] ?? null;
    $endTime = $input['end_time'] ?? null;
    $title = $input['title'] ?? null;
    
    if (!$scheduleId) {
        errorResponse('Schedule ID is required', 400);
    }
    
    // Verify schedule belongs to user
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT id FROM academic_schedule WHERE id = ? AND user_id = ?");
        $stmt->execute([$scheduleId, $userId]);
        
        if (!$stmt->fetch()) {
            errorResponse('Schedule not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if ($dayOfWeek !== null) {
            $updates[] = 'day_of_week = ?';
            $params[] = $dayOfWeek;
        }
        if ($startTime !== null) {
            $updates[] = 'start_time = ?';
            $params[] = $startTime;
        }
        if ($endTime !== null) {
            $updates[] = 'end_time = ?';
            $params[] = $endTime;
        }
        if ($title !== null) {
            $updates[] = 'title = ?';
            $params[] = $title;
        }
        
        if (empty($updates)) {
            errorResponse('No fields to update', 400);
        }
        
        $params[] = $scheduleId;
        $params[] = $userId;
        
        $sql = "UPDATE academic_schedule SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        successResponse(null, 'Schedule updated successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to update schedule: ' . $e->getMessage(), 500);
    }
}

/**
 * Delete an academic schedule entry
 */
function deleteSchedule() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $scheduleId = $input['id'] ?? null;
    
    if (!$scheduleId) {
        errorResponse('Schedule ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("DELETE FROM academic_schedule WHERE id = ? AND user_id = ?");
        $stmt->execute([$scheduleId, $userId]);
        
        if ($stmt->rowCount() === 0) {
            errorResponse('Schedule not found', 404);
        }
        
        successResponse(null, 'Schedule deleted successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to delete schedule: ' . $e->getMessage(), 500);
    }
}

