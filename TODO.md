# DailyBrew - AI Student Scheduler

## Project Overview
A webapp for college/university students to schedule tasks and deadlines with AI assistance using Google Gemini.

## Tech Stack
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Backend**: PHP 8.x
- **Database**: SQLite (no setup required)
- **AI**: Google Gemini API
- **Calendar**: FullCalendar Library

## Implementation Plan

### Phase 1: Project Setup & Configuration
- [x] 1.1 Create project directory structure
- [x] 1.2 Create config.php with database and API settings
- [x] 1.3 Create SQLite database initialization

### Phase 2: Authentication System
- [x] 2.1 Create signup.html (registration page)
- [x] 2.2 Create index.html (login page)
- [x] 2.3 Create php/auth.php (login/signup handlers)
- [x] 2.4 Create js/auth.js (frontend authentication)

### Phase 3: Dashboard & Navigation
- [x] 3.1 Create dashboard.html (main app)
- [x] 3.2 Create css/styles.css (main styling)
- [x] 3.3 Implement sidebar navigation

### Phase 4: Academic Schedule Management
- [x] 4.1 Create php/schedule.php (academic schedule CRUD)
- [x] 4.2 Implement academic schedule UI in dashboard
- [x] 4.3 Add/Edit/Delete time slots

### Phase 5: Task Management
- [x] 5.1 Create php/tasks.php (task CRUD)
- [x] 5.2 Implement task creation UI
- [x] 5.3 Task priority calculation (Date + Intensity)

### Phase 6: AI Scheduler (Gemini Integration)
- [x] 6.1 Create php/ai.php (Gemini API integration)
- [x] 6.2 Implement STUDY_BLOCK generation logic
- [x] 6.3 Implement Study Block Profiles:
  - [x] Early Crammer
  - [x] Seamless
  - [x] Late Crammer

### Phase 7: Calendar Views
- [x] 7.1 Create js/calendar.js (FullCalendar integration)
- [x] 7.2 Implement Day/Week/Month views
- [x] 7.3 Display academic schedule, tasks, and STUDY_BLOCKS

### Phase 8: User Preferences
- [x] 8.1 Create php/preferences.php
- [x] 8.2 Implement settings UI:
  - [x] Time Start/End
  - [x] Study Block Durations
  - [x] Study Block Profile selection

### Phase 9: Final Polish & Testing
- [x] 9.1 Add welcome tour/guide for new users
- [x] 9.2 Implement manual schedule moving
- [x] 9.3 Ensure no overlapping STUDY_BLOCKS
- [ ] 9.4 Test all functionality

## File Structure
```
DailyBrew/
├── index.html
├── signup.html
├── dashboard.html
├── css/
│   └── styles.css
├── js/
│   ├── auth.js
│   ├── app.js
│   ├── calendar.js
│   └── ai-scheduler.js
├── php/
│   ├── config.php
│   ├── db.php
│   ├── auth.php
│   ├── tasks.php
│   ├── schedule.php
│   ├── ai.php
│   └── preferences.php
└── TODO.md
```

