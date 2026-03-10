/**
 * DailyBrew - Calendar JavaScript
 * Handles calendar functionality with FullCalendar
 */

// Calendar configuration
const CalendarConfig = {
    defaultView: 'month',
    header: false,
    editable: true,
    eventLimit: true,
    slotDuration: '00:30:00',
    minTime: '06:00:00',
    maxTime: '23:00:00',
    allDaySlot: false,
    slotLabelFormat: 'h:mm A',
    columnFormat: 'ddd M/D',
    timeFormat: 'h:mm A',
    defaultTimedEventDuration: '01:00:00',
    forceEventDuration: true,
    nowIndicator: true,
    dayPopoverFormat: 'MMMM D, YYYY'
};

// Initialize calendar
function initCalendar(elementId = 'calendar') {
    const calendarEl = document.getElementById(elementId);
    
    if (!calendarEl) {
        console.error('Calendar element not found');
        return null;
    }
    
    const calendar = $(calendarEl).fullCalendar({
        ...CalendarConfig,
        header: {
            left: '',
            center: '',
            right: ''
        },
        events: function(start, end, timezone, callback) {
            loadCalendarEvents(start, end).then(events => callback(events));
        },
        eventDrop: function(event, delta, revertFunc) {
            handleEventDrop(event, delta, revertFunc);
        },
        eventResize: function(event, delta, revertFunc) {
            handleEventResize(event, delta, revertFunc);
        },
        eventClick: function(event, jsEvent, view) {
            handleEventClick(event, jsEvent, view);
        },
        dayClick: function(date, jsEvent, view) {
            handleDayClick(date, jsEvent, view);
        },
        viewRender: function(view, element) {
            updateCalendarTitle(view.title);
        }
    });
    
    return calendar;
}

// Load events from server
async function loadCalendarEvents(start, end) {
    const events = [];
    
    try {
        // Load academic schedules
        const schedResponse = await fetch(
            `php/schedule.php?action=getWeek&start=${start.format()}&end=${end.format()}`
        );
        const schedResult = await schedResponse.json();
        
        if (!schedResult.error && schedResult.data) {
            events.push(...schedResult.data);
        }
        
        // Load study blocks
        const blocksResponse = await fetch(
            `php/ai.php?action=getBlocks&start=${start.format()}&end=${end.format()}`
        );
        const blocksResult = await blocksResponse.json();
        
        if (!blocksResult.error && blocksResult.data) {
            events.push(...blocksResult.data);
        }
        
    } catch (error) {
        console.error('Error loading calendar events:', error);
    }
    
    return events;
}

// Handle event drop (moving events)
async function handleEventDrop(event, delta, revertFunc) {
    // Only allow moving study blocks, not academic schedules
    if (event.extendedType !== 'study_block') {
        showToast('Cannot move academic schedule items', 'warning');
        revertFunc();
        return;
    }
    
    const newStart = event.start.format();
    const newEnd = event.end.format();
    
    try {
        const response = await fetch('php/ai.php?action=moveBlock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: event.id,
                start: newStart,
                end: newEnd
            })
        });
        
        const result = await response.json();
        
        if (result.error) {
            showToast(result.message, 'error');
            revertFunc();
        } else {
            showToast('Study block moved successfully', 'success');
            refreshCalendar();
        }
    } catch (error) {
        showToast('Failed to move study block', 'error');
        revertFunc();
    }
}

// Handle event resize
async function handleEventResize(event, delta, revertFunc) {
    const newStart = event.start.format();
    const newEnd = event.end.format();
    
    try {
        const response = await fetch('php/ai.php?action=moveBlock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: event.id,
                start: newStart,
                end: newEnd
            })
        });
        
        const result = await response.json();
        
        if (result.error) {
            showToast(result.message, 'error');
            revertFunc();
        } else {
            showToast('Study block resized', 'success');
        }
    } catch (error) {
        showToast('Failed to resize study block', 'error');
        revertFunc();
    }
}

// Handle event click
function handleEventClick(event, jsEvent, view) {
    if (event.extendedType === 'study_block') {
        // Show study block details
        const message = `Study Block: ${event.title}\n` +
            `Time: ${event.start.format('MMM D, h:mm A')} - ${event.end.format('h:mm A')}\n` +
            `Created by: ${event.created_by}`;
        
        // For now, show a simple alert - could be enhanced to a modal
        if (confirm(message + '\n\nDelete this study block?')) {
            deleteStudyBlock(event.id);
        }
    } else if (event.extendedType === 'academic') {
        showToast('Academic schedule item - click to edit in Schedule tab', 'info');
    }
}

// Handle day click
function handleDayClick(date, jsEvent, view) {
    // Could be used to add new events by clicking on a day
    const dateStr = date.format('YYYY-MM-DD');
    showToast(`Clicked on ${date.format('MMMM D, YYYY')}`, 'info');
}

// Update calendar title
function updateCalendarTitle(title) {
    const titleEl = document.getElementById('calendarTitle');
    if (titleEl) {
        titleEl.textContent = title;
    }
}

// Refresh calendar events
function refreshCalendar() {
    if (typeof calendar !== 'undefined' && calendar) {
        calendar.fullCalendar('refetchEvents');
    }
}

// Change calendar view
function changeCalendarView(viewName) {
    if (typeof calendar !== 'undefined' && calendar) {
        calendar.fullCalendar('changeView', viewName);
    }
}

// Go to previous period
function calendarPrev() {
    if (typeof calendar !== 'undefined' && calendar) {
        calendar.fullCalendar('prev');
    }
}

// Go to next period
function calendarNext() {
    if (typeof calendar !== 'undefined' && calendar) {
        calendar.fullCalendar('next');
    }
}

// Go to today
function calendarToday() {
    if (typeof calendar !== 'undefined' && calendar) {
        calendar.fullCalendar('today');
    }
}

// Go to specific date
function calendarGoToDate(date) {
    if (typeof calendar !== 'undefined' && calendar) {
        calendar.fullCalendar('gotoDate', date);
    }
}

// Delete study block
async function deleteStudyBlock(blockId) {
    try {
        const response = await fetch('php/ai.php?action=deleteBlock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: blockId })
        });
        
        const result = await response.json();
        
        if (result.error) {
            showToast(result.message, 'error');
        } else {
            showToast('Study block deleted', 'success');
            refreshCalendar();
            
            // Update study blocks count if element exists
            const countEl = document.getElementById('studyBlocksCount');
            if (countEl) {
                const currentCount = parseInt(countEl.textContent) || 0;
                countEl.textContent = Math.max(0, currentCount - 1);
            }
        }
    } catch (error) {
        showToast('Failed to delete study block', 'error');
    }
}

// Export functions to global scope
window.initCalendar = initCalendar;
window.refreshCalendar = refreshCalendar;
window.changeCalendarView = changeCalendarView;
window.calendarPrev = calendarPrev;
window.calendarNext = calendarNext;
window.calendarToday = calendarToday;
window.calendarGoToDate = calendarGoToDate;
window.deleteStudyBlock = deleteStudyBlock;

