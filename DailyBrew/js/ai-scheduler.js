/**
 * DailyBrew - AI Scheduler JavaScript
 * Handles AI-powered study block generation
 */

// AI Scheduler configuration
const AISchedulerConfig = {
    defaultBlockDuration: 30, // minutes
    maxDaysAhead: 14,
    minBlockGap: 5, // minutes between blocks for seamless profile
    profiles: {
        early_crammer: {
            name: 'Early Crammer',
            description: 'Finish tasks as early as possible',
            sortPriority: 'days_asc',
            bufferDays: 3
        },
        seamless: {
            name: 'Seamless',
            description: 'Balanced study blocks with breaks',
            sortPriority: 'balanced',
            blockGap: 15
        },
        late_crammer: {
            name: 'Late Crammer',
            description: 'Study up to a few days before deadline',
            sortPriority: 'priority_first',
            bufferDays: 1
        }
    }
};

// Study Block Manager
class StudyBlockManager {
    constructor() {
        this.tasks = [];
        this.schedules = [];
        this.preferences = {};
        this.studyBlocks = [];
    }
    
    // Load all necessary data
    async loadData() {
        try {
            // Load tasks
            const tasksResponse = await fetch('php/tasks.php?action=read');
            const tasksResult = await tasksResponse.json();
            this.tasks = tasksResult.error ? [] : tasksResult.data;
            
            // Load schedules
            const schedResponse = await fetch('php/schedule.php?action=read');
            const schedResult = await schedResponse.json();
            this.schedules = schedResult.error ? [] : schedResult.data;
            
            // Load preferences
            const prefsResponse = await fetch('php/preferences.php?action=get');
            const prefsResult = await prefsResponse.json();
            this.preferences = prefsResult.error ? {} : prefsResult.data;
            
            return true;
        } catch (error) {
            console.error('Failed to load data:', error);
            return false;
        }
    }
    
    // Calculate available time slots
    calculateAvailableSlots() {
        const slots = [];
        const earliestStart = this.preferences.earliest_start || '08:00';
        const latestEnd = this.preferences.latest_end || '22:00';
        const blockDuration = this.preferences.block_duration || 30;
        
        const startDate = moment();
        const endDate = moment().add(AISchedulerConfig.maxDaysAhead, 'days');
        
        // Group schedules by day of week
        const scheduleByDay = {};
        this.schedules.forEach(sched => {
            if (!scheduleByDay[sched.day_of_week]) {
                scheduleByDay[sched.day_of_week] = [];
            }
            scheduleByDay[sched.day_of_week].push({
                start: sched.start_time,
                end: sched.end_time
            });
        });
        
        // Iterate through each day
        let currentDate = moment(startDate);
        while (currentDate.isBefore(endDate)) {
            const dayOfWeek = currentDate.day();
            const daySchedules = scheduleByDay[dayOfWeek] || [];
            
            // Parse time bounds
            const dayStart = moment(currentDate).set({
                hour: parseInt(earliestStart.split(':')[0]),
                minute: parseInt(earliestStart.split(':')[1])
            });
            
            const dayEnd = moment(currentDate).set({
                hour: parseInt(latestEnd.split(':')[0]),
                minute: parseInt(latestEnd.split(':')[1])
            });
            
            // Find available slots
            let currentTime = moment(dayStart);
            
            while (currentTime.clone().add(blockDuration, 'minutes').isBefore(dayEnd) || 
                   currentTime.clone().add(blockDuration, 'minutes').isSame(dayEnd)) {
                
                const slotStart = currentTime.format('HH:mm');
                const slotEnd = currentTime.clone().add(blockDuration, 'minutes').format('HH:mm');
                
                // Check for conflicts with academic schedule
                let conflicts = false;
                for (const sched of daySchedules) {
                    if (slotStart < sched.end && slotEnd > sched.start) {
                        conflicts = true;
                        break;
                    }
                }
                
                // Check for conflicts with existing study blocks
                if (!conflicts) {
                    const slotStartFull = currentTime.format('YYYY-MM-DD HH:mm:ss');
                    const slotEndFull = currentTime.clone().add(blockDuration, 'minutes').format('YYYY-MM-DD HH:mm:ss');
                    
                    for (const block of this.studyBlocks) {
                        const blockStart = moment(block.start_time);
                        const blockEnd = moment(block.end_time);
                        const slotStartMs = moment(slotStartFull);
                        const slotEndMs = moment(slotEndFull);
                        
                        if (slotStartMs.isBefore(blockEnd) && slotEndMs.isAfter(blockStart)) {
                            conflicts = true;
                            break;
                        }
                    }
                }
                
                if (!conflicts) {
                    slots.push({
                        date: currentDate.format('YYYY-MM-DD'),
                        start: slotStart,
                        end: slotEnd,
                        startFull: currentTime.format('YYYY-MM-DD HH:mm:ss'),
                        endFull: currentTime.clone().add(blockDuration, 'minutes').format('YYYY-MM-DD HH:mm:ss')
                    });
                }
                
                currentTime.add(blockDuration, 'minutes');
            }
            
            currentDate.add(1, 'days');
        }
        
        return slots;
    }
    
    // Sort tasks based on profile
    sortTasks(tasks, profile) {
        const sorted = [...tasks];
        
        switch (profile) {
            case 'early_crammer':
                sorted.sort((a, b) => {
                    const aDue = moment(a.due_date);
                    const bDue = moment(b.due_date);
                    const aDiff = aDue.diff(moment(), 'days');
                    const bDiff = bDue.diff(moment(), 'days');
                    
                    if (aDiff !== bDiff) return aDiff - bDiff;
                    return b.complexity - a.complexity;
                });
                break;
                
            case 'late_crammer':
                sorted.sort((a, b) => {
                    const priorityOrder = { high: 3, medium: 2, low: 1 };
                    const aPriority = priorityOrder[a.priority] || 2;
                    const bPriority = priorityOrder[b.priority] || 2;
                    
                    if (aPriority !== bPriority) return bPriority - aPriority;
                    
                    const aDue = moment(a.due_date);
                    const bDue = moment(b.due_date);
                    return bDue.diff(aDue);
                });
                break;
                
            case 'seamless':
            default:
                sorted.sort((a, b) => {
                    const priorityOrder = { high: 3, medium: 2, low: 1 };
                    const aScore = (priorityOrder[a.priority] || 2) * 10 + (5 - a.complexity);
                    const bScore = (priorityOrder[b.priority] || 2) * 10 + (5 - b.complexity);
                    return aScore - bScore;
                });
                break;
        }
        
        return sorted;
    }
    
    // Assign study blocks to tasks
    assignBlocks(tasks, availableSlots) {
        const blocks = [];
        const profile = this.preferences.study_profile || 'seamless';
        const config = AISchedulerConfig.profiles[profile];
        
        // Sort tasks based on profile
        const sortedTasks = this.sortTasks(tasks, profile);
        
        let slotIndex = 0;
        
        for (const task of sortedTasks) {
            // Calculate required blocks based on complexity
            let requiredBlocks = Math.ceil(task.complexity / 2);
            if (task.priority === 'high') {
                requiredBlocks = Math.max(requiredBlocks, 2);
            }
            
            // Assign blocks
            for (let i = 0; i < requiredBlocks; i++) {
                if (slotIndex >= availableSlots.length) {
                    break;
                }
                
                const slot = availableSlots[slotIndex];
                
                blocks.push({
                    task_id: task.id,
                    title: `Study: ${task.title}`,
                    start_time: slot.startFull,
                    end_time: slot.endFull
                });
                
                slotIndex++;
                
                // Add gap for seamless profile
                if (profile === 'seamless' && i < requiredBlocks - 1) {
                    slotIndex += 1;
                }
            }
        }
        
        return blocks;
    }
    
    // Generate study blocks via server
    async generateStudyBlocks(regenerate = false) {
        showLoading();
        
        try {
            const response = await fetch('php/ai.php?action=generate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ regenerate })
            });
            
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.message);
            }
            
            return result.data;
        } catch (error) {
            console.error('Failed to generate study blocks:', error);
            throw error;
        } finally {
            hideLoading();
        }
    }
    
    // Get study blocks from server
    async getStudyBlocks(start, end) {
        try {
            const url = `php/ai.php?action=getBlocks&start=${start}&end=${end}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.message);
            }
            
            return result.data;
        } catch (error) {
            console.error('Failed to get study blocks:', error);
            return [];
        }
    }
    
    // Delete a study block
    async deleteBlock(blockId) {
        try {
            const response = await fetch('php/ai.php?action=deleteBlock', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: blockId })
            });
            
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.message);
            }
            
            return true;
        } catch (error) {
            console.error('Failed to delete block:', error);
            return false;
        }
    }
    
    // Move a study block
    async moveBlock(blockId, newStart, newEnd) {
        try {
            const response = await fetch('php/ai.php?action=moveBlock', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: blockId, start: newStart, end: newEnd })
            });
            
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.message);
            }
            
            return true;
        } catch (error) {
            console.error('Failed to move block:', error);
            return false;
        }
    }
}

// Create global instance
window.aiScheduler = new StudyBlockManager();

// Export for use
window.AISchedulerConfig = AISchedulerConfig;

