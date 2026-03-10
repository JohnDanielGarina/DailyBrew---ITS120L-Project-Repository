/**
 * DailyBrew - Main Application JavaScript
 * Contains shared application functionality
 */

// Application state
const AppState = {
    currentView: 'calendar',
    calendar: null,
    tasks: [],
    schedules: [],
    studyBlocks: [],
    preferences: {
        study_profile: 'seamless',
        earliest_start: '08:00',
        latest_end: '22:00',
        block_duration: 30
    }
};

// API Helper functions
const API = {
    async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        if (mergedOptions.body && typeof mergedOptions.body === 'object') {
            mergedOptions.body = JSON.stringify(mergedOptions.body);
        }
        
        try {
            const response = await fetch(url, mergedOptions);
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.message);
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    tasks: {
        async getAll() {
            return API.request('php/tasks.php?action=read');
        },
        
        async create(taskData) {
            return API.request('php/tasks.php?action=create', {
                method: 'POST',
                body: taskData
            });
        },
        
        async update(taskData) {
            return API.request('php/tasks.php?action=update', {
                method: 'POST',
                body: taskData
            });
        },
        
        async delete(taskId) {
            return API.request('php/tasks.php?action=delete', {
                method: 'POST',
                body: { id: taskId }
            });
        }
    },
    
    schedule: {
        async getAll() {
            return API.request('php/schedule.php?action=read');
        },
        
        async create(scheduleData) {
            return API.request('php/schedule.php?action=create', {
                method: 'POST',
                body: scheduleData
            });
        },
        
        async update(scheduleData) {
            return API.request('php/schedule.php?action=update', {
                method: 'POST',
                body: scheduleData
            });
        },
        
        async delete(scheduleId) {
            return API.request('php/schedule.php?action=delete', {
                method: 'POST',
                body: { id: scheduleId }
            });
        }
    },
    
    ai: {
        async generate(regenerate = false) {
            return API.request('php/ai.php?action=generate', {
                method: 'POST',
                body: { regenerate }
            });
        },
        
        async getBlocks(start, end) {
            return API.request(`php/ai.php?action=getBlocks&start=${start}&end=${end}`);
        },
        
        async moveBlock(blockId, newStart, newEnd) {
            return API.request('php/ai.php?action=moveBlock', {
                method: 'POST',
                body: { id: blockId, start: newStart, end: newEnd }
            });
        },
        
        async deleteBlock(blockId) {
            return API.request('php/ai.php?action=deleteBlock', {
                method: 'POST',
                body: { id: blockId }
            });
        }
    },
    
    preferences: {
        async get() {
            return API.request('php/preferences.php?action=get');
        },
        
        async update(preferencesData) {
            return API.request('php/preferences.php?action=update', {
                method: 'POST',
                body: preferencesData
            });
        }
    }
};

// Utility functions
const Utils = {
    formatDate(date) {
        return moment(date).format('YYYY-MM-DD');
    },
    
    formatDateTime(dateTime) {
        return moment(dateTime).format('YYYY-MM-DD HH:mm:ss');
    },
    
    formatTime(time) {
        return moment(time, 'HH:mm').format('h:mm A');
    },
    
    getDaysUntilDue(dueDate) {
        const today = moment().startOf('day');
        const due = moment(dueDate);
        return due.diff(today, 'days');
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Priority colors
const PriorityColors = {
    high: '#dc3545',
    medium: '#ffc107',
    low: '#17a2b8'
};

// Profile names
const ProfileNames = {
    early_crammer: 'Early Crammer',
    seamless: 'Seamless',
    late_crammer: 'Late Crammer'
};

// Export for use in other files
window.AppState = AppState;
window.API = API;
window.Utils = Utils;
window.PriorityColors = PriorityColors;
window.ProfileNames = ProfileNames;

