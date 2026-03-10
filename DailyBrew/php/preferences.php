<?php
/**
 * DailyBrew - User Preferences Handler
 * Handles user preferences and settings
 */

require_once __DIR__ . '/config.php';

// Require login for all operations
if (!isLoggedIn()) {
    errorResponse('Authentication required', 401);
}

$userId = getCurrentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getPreferences();
        break;
    case 'update':
        updatePreferences();
        break;
    case 'markTourSeen':
        markTourSeen();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get user preferences
 */
function getPreferences() {
    global $userId;
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $preferences = $stmt->fetch();
        
        if (!$preferences) {
            // Create default preferences if not exists
            $stmt = $db->prepare("INSERT INTO preferences (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            
            $stmt = $db->prepare("SELECT * FROM preferences WHERE user_id = ?");
            $stmt->execute([$userId]);
            $preferences = $stmt->fetch();
        }
        
        successResponse($preferences);
        
    } catch (Exception $e) {
        errorResponse('Failed to get preferences: ' . $e->getMessage(), 500);
    }
}

/**
 * Update user preferences
 */
function updatePreferences() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $studyProfile = $input['study_profile'] ?? null;
    $earliestStart = $input['earliest_start'] ?? null;
    $latestEnd = $input['latest_end'] ?? null;
    $blockDuration = $input['block_duration'] ?? null;
    
    // Validate study profile
    $validProfiles = ['early_crammer', 'seamless', 'late_crammer'];
    if ($studyProfile !== null && !in_array($studyProfile, $validProfiles)) {
        errorResponse('Invalid study profile. Must be: ' . implode(', ', $validProfiles), 400);
    }
    
    // Validate time formats
    if ($earliestStart !== null && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $earliestStart)) {
        errorResponse('Invalid earliest start time format. Use HH:MM', 400);
    }
    
    if ($latestEnd !== null && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $latestEnd)) {
        errorResponse('Invalid latest end time format. Use HH:MM', 400);
    }
    
    // Validate block duration
    if ($blockDuration !== null) {
        $blockDuration = (int)$blockDuration;
        if ($blockDuration < 15 || $blockDuration > 180) {
            errorResponse('Block duration must be between 15 and 180 minutes', 400);
        }
    }
    
    // Check time validity
    if ($earliestStart !== null && $latestEnd !== null) {
        if ($earliestStart >= $latestEnd) {
            errorResponse('Earliest start time must be before latest end time', 400);
        }
    }
    
    try {
        $db = getDB();
        
        // Build update query
        $updates = [];
        $params = [];
        
        if ($studyProfile !== null) {
            $updates[] = 'study_profile = ?';
            $params[] = $studyProfile;
        }
        if ($earliestStart !== null) {
            $updates[] = 'earliest_start = ?';
            $params[] = $earliestStart;
        }
        if ($latestEnd !== null) {
            $updates[] = 'latest_end = ?';
            $params[] = $latestEnd;
        }
        if ($blockDuration !== null) {
            $updates[] = 'block_duration = ?';
            $params[] = $blockDuration;
        }
        
        if (empty($updates)) {
            errorResponse('No fields to update', 400);
        }
        
        $params[] = $userId;
        
        // Check if preferences exist
        $stmt = $db->prepare("SELECT id FROM preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if (!$stmt->fetch()) {
            // Create new preferences
            $stmt = $db->prepare("INSERT INTO preferences (user_id, study_profile, earliest_start, latest_end, block_duration) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $studyProfile ?? 'seamless',
                $earliestStart ?? '08:00',
                $latestEnd ?? '22:00',
                $blockDuration ?? 30
            ]);
        } else {
            $sql = "UPDATE preferences SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
        
        successResponse(null, 'Preferences updated successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to update preferences: ' . $e->getMessage(), 500);
    }
}

/**
 * Mark tour as seen
 */
function markTourSeen() {
    global $userId;
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("UPDATE preferences SET has_seen_tour = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        successResponse(null, 'Tour marked as seen');
        
    } catch (Exception $e) {
        errorResponse('Failed to update tour status: ' . $e->getMessage(), 500);
    }
}

