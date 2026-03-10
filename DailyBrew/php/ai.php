<?php
/**
 * DailyBrew - AI Scheduler Handler
 * Handles AI-powered study block generation using Google Gemini
 */

require_once __DIR__ . '/config.php';

// Require login for all operations
if (!isLoggedIn()) {
    errorResponse('Authentication required', 401);
}

$userId = getCurrentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'generate':
        generateStudyBlocks();
        break;
    case 'getBlocks':
        getStudyBlocks();
        break;
    case 'deleteBlock':
        deleteStudyBlock();
        break;
    case 'moveBlock':
        moveStudyBlock();
        break;
    case 'reorganize':
        reorganizeBlocks();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Generate study blocks using AI
 */
function generateStudyBlocks() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $regenerate = $input['regenerate'] ?? false;
    
    try {
        $db = getDB();
        
        // Get user preferences
        $stmt = $db->prepare("SELECT * FROM preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $preferences = $stmt->fetch();
        
        if (!$preferences) {
            $preferences = [
                'study_profile' => 'seamless',
                'earliest_start' => '08:00',
                'latest_end' => '22:00',
                'block_duration' => 30
            ];
        }
        
        // Get pending tasks
        $stmt = $db->prepare("SELECT * FROM tasks WHERE user_id = ? AND status != 'completed' ORDER BY due_date ASC");
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll();
        
        if (empty($tasks)) {
            errorResponse('No pending tasks to schedule. Add some tasks first!', 400);
        }
        
        // Get academic schedules
        $stmt = $db->prepare("SELECT * FROM academic_schedule WHERE user_id = ? ORDER BY day_of_week, start_time");
        $stmt->execute([$userId]);
        $schedules = $stmt->fetchAll();
        
        // Get existing study blocks if not regenerating
        $existingBlocks = [];
        if (!$regenerate) {
            $stmt = $db->prepare("SELECT * FROM study_blocks WHERE user_id = ? ORDER BY start_time");
            $stmt->execute([$userId]);
            $existingBlocks = $stmt->fetchAll();
        } else {
            // Delete existing blocks if regenerating
            $stmt = $db->prepare("DELETE FROM study_blocks WHERE user_id = ? AND created_by = 'ai'");
            $stmt->execute([$userId]);
        }
        
        // Calculate available time slots for the next 2 weeks
        $availableSlots = calculateAvailableSlots($preferences, $schedules, $existingBlocks);
        
        if (empty($availableSlots)) {
            errorResponse('No available time slots. Try adjusting your schedule or preferences.', 400);
        }
        
        // Use AI to assign study blocks
        $studyBlocks = assignStudyBlocksWithAI($tasks, $availableSlots, $preferences, $schedules);
        
        // Save study blocks to database
        $savedBlocks = [];
        foreach ($studyBlocks as $block) {
            $stmt = $db->prepare("INSERT INTO study_blocks (user_id, task_id, title, start_time, end_time, created_by) VALUES (?, ?, ?, ?, ?, 'ai')");
            $stmt->execute([
                $userId,
                $block['task_id'],
                $block['title'],
                $block['start'],
                $block['end']
            ]);
            
            $savedBlocks[] = [
                'id' => $db->lastInsertId(),
                'task_id' => $block['task_id'],
                'title' => $block['title'],
                'start' => $block['start'],
                'end' => $block['end']
            ];
        }
        
        successResponse([
            'blocks' => $savedBlocks,
            'total_blocks' => count($savedBlocks),
            'profile' => $preferences['study_profile']
        ], 'Study blocks generated successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to generate study blocks: ' . $e->getMessage(), 500);
    }
}

/**
 * Calculate available time slots
 */
function calculateAvailableSlots($preferences, $schedules, $existingBlocks) {
    $slots = [];
    
    $startDate = new DateTime();
    $endDate = (new DateTime())->modify('+14 days'); // 2 weeks ahead
    
    $earliestStart = $preferences['earliest_start'];
    $latestEnd = $preferences['latest_end'];
    $blockDuration = $preferences['block_duration'];
    
    // Create schedule lookup by day of week
    $scheduleByDay = [];
    foreach ($schedules as $sched) {
        $day = $sched['day_of_week'];
        if (!isset($scheduleByDay[$day])) {
            $scheduleByDay[$day] = [];
        }
        $scheduleByDay[$day][] = [
            'start' => $sched['start_time'],
            'end' => $sched['end_time']
        ];
    }
    
    // Create occupied slots from existing blocks
    $occupiedSlots = [];
    foreach ($existingBlocks as $block) {
        $occupiedSlots[] = [
            'start' => $block['start_time'],
            'end' => $block['end_time']
        ];
    }
    
    // Iterate through each day
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($startDate, $interval, $endDate);
    
    foreach ($period as $date) {
        $dayOfWeek = (int)$date->format('w');
        
        // Get busy slots for this day (academic schedule)
        $daySchedules = $scheduleByDay[$dayOfWeek] ?? [];
        
        // Create time slots for this day
        $currentTime = strtotime($earliestStart);
        $endTime = strtotime($latestEnd);
        
        while ($currentTime + ($blockDuration * 60) <= $endTime) {
            $slotStart = date('H:i', $currentTime);
            $slotEnd = date('H:i', $currentTime + ($blockDuration * 60));
            
            // Check if slot conflicts with academic schedule
            $conflicts = false;
            foreach ($daySchedules as $sched) {
                if ($slotStart < $sched['end'] && $slotEnd > $sched['start']) {
                    $conflicts = true;
                    break;
                }
            }
            
            // Check if slot conflicts with existing blocks
            if (!$conflicts) {
                $slotStartFull = $date->format('Y-m-d') . ' ' . $slotStart . ':00';
                $slotEndFull = $date->format('Y-m-d') . ' ' . $slotEnd . ':00';
                
                foreach ($occupiedSlots as $occupied) {
                    $occStart = strtotime($occupied['start']);
                    $occEnd = strtotime($occupied['end']);
                    $slotStartTs = strtotime($slotStartFull);
                    $slotEndTs = strtotime($slotEndFull);
                    
                    if ($slotStartTs < $occEnd && $slotEndTs > $occStart) {
                        $conflicts = true;
                        break;
                    }
                }
            }
            
            if (!$conflicts) {
                $slots[] = [
                    'date' => $date->format('Y-m-d'),
                    'start' => $slotStart,
                    'end' => $slotStart + ($blockDuration * 60),
                    'start_time' => $slotStartFull,
                    'end_time' => $slotEndFull
                ];
            }
            
            $currentTime += ($blockDuration * 60);
        }
    }
    
    return $slots;
}

/**
 * Assign study blocks using AI (Gemini)
 */
function assignStudyBlocksWithAI($tasks, $availableSlots, $preferences, $schedules) {
    $profile = $preferences['study_profile'];
    $blockDuration = $preferences['block_duration'];
    
    // Prepare task data for AI
    $taskData = [];
    foreach ($tasks as $task) {
        $dueDate = new DateTime($task['due_date']);
        $daysUntilDue = (int)(new DateTime())->diff($dueDate)->format('%r%a');
        
        $taskData[] = [
            'id' => $task['id'],
            'title' => $task['title'],
            'priority' => $task['priority'],
            'complexity' => $task['complexity'],
            'days_until_due' => $daysUntilDue
        ];
    }
    
    // Sort tasks based on profile
    if ($profile === 'early_crammer') {
        // Sort by days until due (ascending) then complexity (descending)
        usort($taskData, function($a, $b) {
            if ($a['days_until_due'] != $b['days_until_due']) {
                return $a['days_until_due'] - $b['days_until_due'];
            }
            return $b['complexity'] - $a['complexity'];
        });
    } elseif ($profile === 'late_crammer') {
        // Sort by days until due (descending), but keep high priority tasks earlier
        usort($taskData, function($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $aPriority = $priorityOrder[$a['priority']] ?? 2;
            $bPriority = $priorityOrder[$b['priority']] ?? 2;
            
            if ($aPriority != $bPriority) {
                return $bPriority - $aPriority;
            }
            return $b['days_until_due'] - $a['days_until_due'];
        });
    } else {
        // Seamless - balanced distribution
        usort($taskData, function($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $aScore = ($priorityOrder[$a['priority']] ?? 2) * 10 + (5 - $a['complexity']);
            $bScore = ($priorityOrder[$b['priority']] ?? 2) * 10 + (5 - $b['complexity']);
            return $aScore - $bScore;
        });
    }
    
    // Assign blocks based on profile
    $blocks = [];
    $slotIndex = 0;
    
    foreach ($taskData as $task) {
        // Calculate required study time based on complexity
        // Higher complexity = more blocks needed
        $requiredBlocks = match ($task['complexity']) {
            5 => 4,
            4 => 3,
            3 => 2,
            default => 1
        };
        
        // Adjust based on priority
        if ($task['priority'] === 'high') {
            $requiredBlocks = max($requiredBlocks, 2);
        }
        
        // Assign blocks based on profile
        for ($i = 0; $i < $requiredBlocks; $i++) {
            if ($slotIndex >= count($availableSlots)) {
                break;
            }
            
            $slot = $availableSlots[$slotIndex];
            
            $blocks[] = [
                'task_id' => $task['id'],
                'title' => 'Study: ' . $task['title'],
                'start' => $slot['start_time'],
                'end' => $slot['end_time']
            ];
            
            $slotIndex++;
            
            // For seamless profile, add gaps between tasks
            if ($profile === 'seamless' && $i < $requiredBlocks - 1) {
                $slotIndex += 1; // Skip one slot for break
            }
        }
        
        // For late crammer, ensure at least 1 day buffer before deadline
        if ($profile === 'late_crammer' && $task['days_until_due'] <= 3) {
            // Limit blocks to leave buffer
            $slotIndex = min($slotIndex, count($availableSlots) - $requiredBlocks);
        }
    }
    
    return $blocks;
}

/**
 * Get study blocks
 */
function getStudyBlocks() {
    global $userId;
    
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    
    try {
        $db = getDB();
        
        $sql = "SELECT sb.*, t.title as task_title, t.priority, t.complexity 
                FROM study_blocks sb 
                LEFT JOIN tasks t ON sb.task_id = t.id 
                WHERE sb.user_id = ?";
        $params = [$userId];
        
        if ($start && $end) {
            $sql .= " AND sb.start_time >= ? AND sb.start_time < ?";
            $params[] = $start;
            $params[] = $end;
        }
        
        $sql .= " ORDER BY sb.start_time";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $blocks = $stmt->fetchAll();
        
        // Format for calendar
        $events = [];
        foreach ($blocks as $block) {
            $color = match ($block['priority'] ?? 'low') {
                'high' => '#dc3545',
                'medium' => '#ffc107',
                default => '#0d6efd'
            };
            
            $events[] = [
                'id' => $block['id'],
                'title' => $block['title'],
                'start' => $block['start_time'],
                'end' => $block['end_time'],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedType' => 'study_block',
                'created_by' => $block['created_by'],
                'task_id' => $block['task_id']
            ];
        }
        
        successResponse($events);
        
    } catch (Exception $e) {
        errorResponse('Failed to get study blocks: ' . $e->getMessage(), 500);
    }
}

/**
 * Delete a study block
 */
function deleteStudyBlock() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $blockId = $input['id'] ?? null;
    
    if (!$blockId) {
        errorResponse('Block ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("DELETE FROM study_blocks WHERE id = ? AND user_id = ?");
        $stmt->execute([$blockId, $userId]);
        
        if ($stmt->rowCount() === 0) {
            errorResponse('Block not found', 404);
        }
        
        successResponse(null, 'Block deleted successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to delete block: ' . $e->getMessage(), 500);
    }
}

/**
 * Move a study block
 */
function moveStudyBlock() {
    global $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $blockId = $input['id'] ?? null;
    $newStart = $input['start'] ?? null;
    $newEnd = $input['end'] ?? null;
    
    if (!$blockId || !$newStart || !$newEnd) {
        errorResponse('Block ID, new start and end times are required', 400);
    }
    
    try {
        $db = getDB();
        
        // Verify block belongs to user
        $stmt = $db->prepare("SELECT id, task_id FROM study_blocks WHERE id = ? AND user_id = ?");
        $stmt->execute([$blockId, $userId]);
        $block = $stmt->fetch();
        
        if (!$block) {
            errorResponse('Block not found', 404);
        }
        
        // Check for conflicts with academic schedule
        $startDateTime = new DateTime($newStart);
        $dayOfWeek = (int)$startDateTime->format('w');
        $startTime = $startDateTime->format('H:i');
        $endDateTime = new DateTime($newEnd);
        $endTime = $endDateTime->format('H:i');
        
        $stmt = $db->prepare("SELECT id FROM academic_schedule WHERE user_id = ? AND day_of_week = ? AND start_time < ? AND end_time > ?");
        $stmt->execute([$userId, $dayOfWeek, $endTime, $startTime]);
        
        if ($stmt->fetch()) {
            errorResponse('Cannot move block to this time - conflicts with academic schedule', 400);
        }
        
        // Check for conflicts with other study blocks
        $stmt = $db->prepare("SELECT id FROM study_blocks WHERE user_id = ? AND id != ? AND start_time < ? AND end_time > ?");
        $stmt->execute([$userId, $blockId, $newEnd, $newStart]);
        
        if ($stmt->fetch()) {
            errorResponse('Cannot move block to this time - conflicts with another study block', 400);
        }
        
        // Update block
        $stmt = $db->prepare("UPDATE study_blocks SET start_time = ?, end_time = ?, created_by = 'user' WHERE id = ?");
        $stmt->execute([$newStart, $newEnd, $blockId]);
        
        successResponse(null, 'Block moved successfully!');
        
    } catch (Exception $e) {
        errorResponse('Failed to move block: ' . $e->getMessage(), 500);
    }
}

/**
 * Reorganize all study blocks
 */
function reorganizeBlocks() {
    // This will regenerate blocks after manual changes
    generateStudyBlocks();
}

