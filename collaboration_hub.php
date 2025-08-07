<?php
/**
 * Real-time Collaboration Hub
 * Team collaboration, task management, and instant communication center
 */

session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
include 'db.php';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_team_activities':
            getTeamActivities($conn);
            break;
        case 'create_task':
            createTask($conn, $_POST);
            break;
        case 'update_task_status':
            updateTaskStatus($conn, $_POST);
            break;
        case 'send_message':
            sendMessage($conn, $_POST);
            break;
        case 'get_messages':
            getMessages($conn, $_POST);
            break;
        case 'create_meeting':
            createMeeting($conn, $_POST);
            break;
        case 'get_upcoming_meetings':
            getUpcomingMeetings($conn);
            break;
    }
    exit;
}

// Collaboration Functions
function getTeamActivities($conn) {
    $activities = [
        [
            'id' => 1,
            'user' => 'Sarah Wilson',
            'action' => 'completed task',
            'description' => 'Q4 Budget Review',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
            'type' => 'task_completed',
            'priority' => 'high'
        ],
        [
            'id' => 2,
            'user' => 'Mike Johnson',
            'action' => 'created meeting',
            'description' => 'Weekly Team Standup',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-12 minutes')),
            'type' => 'meeting_created',
            'priority' => 'medium'
        ],
        [
            'id' => 3,
            'user' => 'Emily Davis',
            'action' => 'shared document',
            'description' => 'Project Timeline Draft',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-18 minutes')),
            'type' => 'document_shared',
            'priority' => 'low'
        ],
        [
            'id' => 4,
            'user' => 'John Smith',
            'action' => 'updated status',
            'description' => 'Client Presentation - In Progress',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-25 minutes')),
            'type' => 'status_updated',
            'priority' => 'medium'
        ]
    ];
    
    echo json_encode(['success' => true, 'activities' => $activities]);
}

function createTask($conn, $data) {
    $task = [
        'id' => rand(100, 999),
        'title' => $data['title'] ?? 'New Task',
        'description' => $data['description'] ?? '',
        'assignee' => $data['assignee'] ?? 'Unassigned',
        'priority' => $data['priority'] ?? 'medium',
        'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode(['success' => true, 'task' => $task, 'message' => 'Task created successfully']);
}

function updateTaskStatus($conn, $data) {
    $task_id = $data['task_id'] ?? 0;
    $status = $data['status'] ?? 'pending';
    
    echo json_encode(['success' => true, 'message' => "Task #{$task_id} status updated to {$status}"]);
}

function sendMessage($conn, $data) {
    $message = [
        'id' => rand(1000, 9999),
        'sender' => $_SESSION['admin'] ?? 'Current User',
        'message' => $data['message'] ?? '',
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'text'
    ];
    
    echo json_encode(['success' => true, 'message' => $message]);
}

function getMessages($conn, $data) {
    $messages = [
        [
            'id' => 1,
            'sender' => 'Sarah Wilson',
            'message' => 'Has anyone reviewed the Q4 budget proposal?',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'type' => 'text'
        ],
        [
            'id' => 2,
            'sender' => 'Mike Johnson',
            'message' => 'I\'ll take a look at it this afternoon.',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-28 minutes')),
            'type' => 'text'
        ],
        [
            'id' => 3,
            'sender' => 'Emily Davis',
            'message' => 'Just shared the updated project timeline in the documents section.',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
            'type' => 'text'
        ]
    ];
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function createMeeting($conn, $data) {
    $meeting = [
        'id' => rand(500, 999),
        'title' => $data['title'] ?? 'Team Meeting',
        'description' => $data['description'] ?? '',
        'date' => $data['date'] ?? date('Y-m-d'),
        'time' => $data['time'] ?? '10:00',
        'duration' => $data['duration'] ?? 60,
        'attendees' => $data['attendees'] ?? [],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode(['success' => true, 'meeting' => $meeting, 'message' => 'Meeting scheduled successfully']);
}

function getUpcomingMeetings($conn) {
    $meetings = [
        [
            'id' => 501,
            'title' => 'Weekly Team Standup',
            'date' => date('Y-m-d'),
            'time' => '10:00',
            'duration' => 30,
            'attendees' => ['Sarah Wilson', 'Mike Johnson', 'Emily Davis'],
            'status' => 'upcoming'
        ],
        [
            'id' => 502,
            'title' => 'Project Review',
            'date' => date('Y-m-d', strtotime('+1 day')),
            'time' => '14:00',
            'duration' => 60,
            'attendees' => ['John Smith', 'Lisa Brown'],
            'status' => 'scheduled'
        ]
    ];
    
    echo json_encode(['success' => true, 'meetings' => $meetings]);
}

$page_title = 'Collaboration Hub';
include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Collaboration Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-people text-primary me-3"></i>Collaboration Hub
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Real-time team collaboration, task management, and communication center</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success" onclick="showCreateTaskModal()">
                    <i class="bi bi-plus-circle me-2"></i>New Task
                </button>
                <button class="btn btn-outline-info" onclick="showCreateMeetingModal()">
                    <i class="bi bi-calendar-plus me-2"></i>Schedule Meeting
                </button>
                <button class="btn btn-primary" onclick="refreshCollaborationData()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Collaboration Dashboard Grid -->
        <div class="row g-4 mb-4">
            <!-- Real-time Activity Feed -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-activity text-success me-2"></i>
                                Team Activity Feed
                            </h5>
                            <div class="activity-status">
                                <span class="badge bg-success">
                                    <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                                    Live
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="activity-feed" id="activityFeed" style="max-height: 400px; overflow-y: auto;">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary mb-3"></div>
                                <p class="text-muted">Loading team activities...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats & Actions -->
            <div class="col-lg-6">
                <div class="row g-3">
                    <!-- Team Stats -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="mb-0">
                                    <i class="bi bi-graph-up text-info me-2"></i>
                                    Team Performance
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="stat-card bg-success bg-opacity-10 p-3 rounded text-center">
                                            <div class="stat-number text-success">24</div>
                                            <small class="text-muted">Active Members</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-card bg-primary bg-opacity-10 p-3 rounded text-center">
                                            <div class="stat-number text-primary">89%</div>
                                            <small class="text-muted">Task Completion</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-card bg-warning bg-opacity-10 p-3 rounded text-center">
                                            <div class="stat-number text-warning">15</div>
                                            <small class="text-muted">Pending Tasks</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-card bg-info bg-opacity-10 p-3 rounded text-center">
                                            <div class="stat-number text-info">8</div>
                                            <small class="text-muted">This Week Meetings</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="mb-0">
                                    <i class="bi bi-lightning text-warning me-2"></i>
                                    Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button class="btn btn-outline-primary w-100 btn-sm" onclick="showCreateTaskModal()">
                                            <i class="bi bi-plus-circle me-1"></i>Create Task
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-success w-100 btn-sm" onclick="openTeamChat()">
                                            <i class="bi bi-chat-dots me-1"></i>Team Chat
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-info w-100 btn-sm" onclick="showCreateMeetingModal()">
                                            <i class="bi bi-calendar-plus me-1"></i>Schedule Meeting
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-warning w-100 btn-sm" onclick="openDocumentCenter()">
                                            <i class="bi bi-files me-1"></i>Documents
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Management & Communication -->
        <div class="row g-4 mb-4">
            <!-- Active Tasks Board -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-kanban text-primary me-2"></i>
                                Task Board
                            </h5>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm active" onclick="showTaskView('kanban')">Kanban</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showTaskView('list')">List</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showTaskView('calendar')">Calendar</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="taskBoard">
                            <div class="row g-3">
                                <!-- To Do -->
                                <div class="col-md-4">
                                    <div class="task-column">
                                        <div class="task-column-header bg-secondary bg-opacity-10 p-2 rounded mb-3">
                                            <h6 class="mb-0 text-secondary">
                                                <i class="bi bi-clock me-1"></i>To Do (5)
                                            </h6>
                                        </div>
                                        <div class="task-list" id="todoTasks">
                                            <div class="task-card border rounded p-3 mb-2" draggable="true">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-danger">High</span>
                                                    <small class="text-muted">Due: Today</small>
                                                </div>
                                                <h6 class="mb-1">Q4 Budget Review</h6>
                                                <p class="small text-muted mb-2">Review and approve quarterly budget allocation</p>
                                                <div class="d-flex align-items-center">
                                                    <img src="https://ui-avatars.com/api/?name=Sarah+Wilson&size=24&background=007bff&color=fff" class="rounded-circle me-2" alt="Assignee">
                                                    <small>Sarah Wilson</small>
                                                </div>
                                            </div>
                                            <div class="task-card border rounded p-3 mb-2" draggable="true">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-warning">Medium</span>
                                                    <small class="text-muted">Due: Tomorrow</small>
                                                </div>
                                                <h6 class="mb-1">Update Project Timeline</h6>
                                                <p class="small text-muted mb-2">Revise project milestones and deadlines</p>
                                                <div class="d-flex align-items-center">
                                                    <img src="https://ui-avatars.com/api/?name=Mike+Johnson&size=24&background=28a745&color=fff" class="rounded-circle me-2" alt="Assignee">
                                                    <small>Mike Johnson</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- In Progress -->
                                <div class="col-md-4">
                                    <div class="task-column">
                                        <div class="task-column-header bg-primary bg-opacity-10 p-2 rounded mb-3">
                                            <h6 class="mb-0 text-primary">
                                                <i class="bi bi-play-circle me-1"></i>In Progress (3)
                                            </h6>
                                        </div>
                                        <div class="task-list" id="inProgressTasks">
                                            <div class="task-card border rounded p-3 mb-2" draggable="true">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-primary">Medium</span>
                                                    <small class="text-muted">Due: Friday</small>
                                                </div>
                                                <h6 class="mb-1">Client Presentation</h6>
                                                <p class="small text-muted mb-2">Prepare slides for client meeting</p>
                                                <div class="progress mb-2" style="height: 4px;">
                                                    <div class="progress-bar bg-primary" style="width: 65%;"></div>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <img src="https://ui-avatars.com/api/?name=Emily+Davis&size=24&background=ffc107&color=000" class="rounded-circle me-2" alt="Assignee">
                                                    <small>Emily Davis</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Completed -->
                                <div class="col-md-4">
                                    <div class="task-column">
                                        <div class="task-column-header bg-success bg-opacity-10 p-2 rounded mb-3">
                                            <h6 class="mb-0 text-success">
                                                <i class="bi bi-check-circle me-1"></i>Completed (8)
                                            </h6>
                                        </div>
                                        <div class="task-list" id="completedTasks">
                                            <div class="task-card border rounded p-3 mb-2 opacity-75" draggable="true">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-success">Completed</span>
                                                    <small class="text-muted">Completed Today</small>
                                                </div>
                                                <h6 class="mb-1">Team Meeting Minutes</h6>
                                                <p class="small text-muted mb-2">Document weekly standup discussions</p>
                                                <div class="d-flex align-items-center">
                                                    <img src="https://ui-avatars.com/api/?name=John+Smith&size=24&background=dc3545&color=fff" class="rounded-circle me-2" alt="Assignee">
                                                    <small>John Smith</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Chat -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-chat-dots text-success me-2"></i>
                                Team Chat
                            </h5>
                            <div class="online-status">
                                <span class="badge bg-success">
                                    <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                                    4 Online
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 d-flex flex-column">
                        <div class="chat-messages flex-grow-1 p-3" id="chatMessages" style="max-height: 350px; overflow-y: auto;">
                            <div class="text-center py-4">
                                <div class="spinner-border text-success mb-3"></div>
                                <p class="text-muted">Loading chat messages...</p>
                            </div>
                        </div>
                        <div class="chat-input border-top p-3">
                            <form onsubmit="sendChatMessage(event)">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Type your message..." id="chatInput" autocomplete="off">
                                    <button class="btn btn-success" type="submit">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Meetings & Calendar -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-calendar3 text-info me-2"></i>
                                Upcoming Meetings & Events
                            </h5>
                            <button class="btn btn-outline-info btn-sm" onclick="showCreateMeetingModal()">
                                <i class="bi bi-plus-circle me-1"></i>Schedule Meeting
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3" id="meetingsContainer">
                            <div class="text-center py-4">
                                <div class="spinner-border text-info mb-3"></div>
                                <p class="text-muted">Loading upcoming meetings...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Create New Task
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="createNewTask(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Assignee</label>
                            <select class="form-select" name="assignee">
                                <option value="Sarah Wilson">Sarah Wilson</option>
                                <option value="Mike Johnson">Mike Johnson</option>
                                <option value="Emily Davis">Emily Davis</option>
                                <option value="John Smith">John Smith</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Meeting Modal -->
<div class="modal fade" id="createMeetingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus text-info me-2"></i>Schedule Meeting
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="createNewMeeting(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Meeting Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="time" value="10:00">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Duration (minutes)</label>
                        <select class="form-select" name="duration">
                            <option value="30">30 minutes</option>
                            <option value="60" selected>1 hour</option>
                            <option value="90">1.5 hours</option>
                            <option value="120">2 hours</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Schedule Meeting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.gradient-text {
    background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.activity-item {
    border-left: 3px solid transparent;
    padding-left: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.activity-item:hover {
    background-color: rgba(0,0,0,0.02);
}

.activity-item.high { border-left-color: #dc3545; }
.activity-item.medium { border-left-color: #ffc107; }
.activity-item.low { border-left-color: #28a745; }

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}

.task-card {
    transition: all 0.3s ease;
    cursor: move;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.task-column {
    min-height: 400px;
}

.chat-messages {
    background-color: #f8f9fa;
}

.message-item {
    margin-bottom: 1rem;
}

.message-bubble {
    background: white;
    border-radius: 1rem;
    padding: 0.75rem 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.message-bubble.own {
    background: #007bff;
    color: white;
    margin-left: 2rem;
}

.meeting-card {
    border-left: 4px solid #17a2b8;
    transition: all 0.3s ease;
}

.meeting-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .task-board .col-md-4 {
        margin-bottom: 2rem;
    }
    
    .chat-messages {
        max-height: 250px !important;
    }
}
</style>

<script>
// Collaboration Hub JavaScript

document.addEventListener('DOMContentLoaded', function() {
    loadTeamActivities();
    loadChatMessages();
    loadUpcomingMeetings();
    
    // Auto-refresh activities every 30 seconds
    setInterval(loadTeamActivities, 30000);
    setInterval(loadChatMessages, 15000);
});

function loadTeamActivities() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_team_activities'
    })
    .then(response => response.json())
    .then(data => {
        displayTeamActivities(data.activities);
    })
    .catch(error => console.error('Error loading activities:', error));
}

function displayTeamActivities(activities) {
    const feed = document.getElementById('activityFeed');
    let html = '';
    
    activities.forEach(activity => {
        const timeAgo = getTimeAgo(activity.timestamp);
        html += `
            <div class="activity-item ${activity.priority} p-3 border-bottom">
                <div class="d-flex align-items-start">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(activity.user)}&size=32&background=random" 
                         class="rounded-circle me-3" alt="User">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${activity.user}</strong> ${activity.action}
                                <div class="text-primary fw-semibold">${activity.description}</div>
                            </div>
                            <small class="text-muted">${timeAgo}</small>
                        </div>
                        <div class="activity-type mt-1">
                            <span class="badge bg-${getActivityTypeColor(activity.type)}">${formatActivityType(activity.type)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    feed.innerHTML = html;
}

function loadChatMessages() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_messages'
    })
    .then(response => response.json())
    .then(data => {
        displayChatMessages(data.messages);
    })
    .catch(error => console.error('Error loading messages:', error));
}

function displayChatMessages(messages) {
    const chatContainer = document.getElementById('chatMessages');
    let html = '';
    
    messages.forEach(message => {
        const timeAgo = getTimeAgo(message.timestamp);
        const isOwn = message.sender === 'Current User';
        
        html += `
            <div class="message-item ${isOwn ? 'text-end' : ''}">
                <div class="message-bubble ${isOwn ? 'own' : ''}">
                    <div class="message-content">${message.message}</div>
                    <div class="message-meta mt-1">
                        <small class="${isOwn ? 'text-white-50' : 'text-muted'}">
                            ${isOwn ? 'You' : message.sender} • ${timeAgo}
                        </small>
                    </div>
                </div>
            </div>
        `;
    });
    
    chatContainer.innerHTML = html;
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function loadUpcomingMeetings() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_upcoming_meetings'
    })
    .then(response => response.json())
    .then(data => {
        displayUpcomingMeetings(data.meetings);
    })
    .catch(error => console.error('Error loading meetings:', error));
}

function displayUpcomingMeetings(meetings) {
    const container = document.getElementById('meetingsContainer');
    let html = '';
    
    meetings.forEach(meeting => {
        html += `
            <div class="col-md-6">
                <div class="meeting-card card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">${meeting.title}</h6>
                            <span class="badge bg-info">${meeting.status}</span>
                        </div>
                        <div class="meeting-details">
                            <p class="small text-muted mb-2">
                                <i class="bi bi-calendar me-1"></i>${formatDate(meeting.date)}
                                <i class="bi bi-clock ms-2 me-1"></i>${meeting.time}
                                <i class="bi bi-stopwatch ms-2 me-1"></i>${meeting.duration}min
                            </p>
                            <div class="attendees">
                                <small class="text-muted">Attendees:</small>
                                <div class="d-flex align-items-center mt-1">
                                    ${meeting.attendees.slice(0, 3).map(attendee => 
                                        `<img src="https://ui-avatars.com/api/?name=${encodeURIComponent(attendee)}&size=24&background=random" 
                                              class="rounded-circle me-1" alt="${attendee}" title="${attendee}">`
                                    ).join('')}
                                    ${meeting.attendees.length > 3 ? `<span class="badge bg-secondary">+${meeting.attendees.length - 3}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function sendChatMessage(event) {
    event.preventDefault();
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=send_message&message=${encodeURIComponent(message)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadChatMessages(); // Refresh messages
            showAlert('Message sent!', 'success');
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        showAlert('Failed to send message', 'error');
    });
}

function showCreateTaskModal() {
    new bootstrap.Modal(document.getElementById('createTaskModal')).show();
}

function createNewTask(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    data.action = 'create_task';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('createTaskModal')).hide();
            showAlert(result.message, 'success');
            loadTeamActivities(); // Refresh activities
        }
    })
    .catch(error => {
        console.error('Error creating task:', error);
        showAlert('Failed to create task', 'error');
    });
}

function showCreateMeetingModal() {
    new bootstrap.Modal(document.getElementById('createMeetingModal')).show();
}

function createNewMeeting(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    data.action = 'create_meeting';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('createMeetingModal')).hide();
            showAlert(result.message, 'success');
            loadUpcomingMeetings(); // Refresh meetings
        }
    })
    .catch(error => {
        console.error('Error creating meeting:', error);
        showAlert('Failed to schedule meeting', 'error');
    });
}

function refreshCollaborationData() {
    showAlert('Refreshing collaboration data...', 'info');
    loadTeamActivities();
    loadChatMessages();
    loadUpcomingMeetings();
}

function openTeamChat() {
    document.getElementById('chatInput').focus();
}

function openDocumentCenter() {
    showAlert('Document Center opening...', 'info');
}

// Utility functions
function getTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

function getActivityTypeColor(type) {
    const colors = {
        'task_completed': 'success',
        'meeting_created': 'info',
        'document_shared': 'warning',
        'status_updated': 'primary'
    };
    return colors[type] || 'secondary';
}

function formatActivityType(type) {
    return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        weekday: 'short', 
        month: 'short', 
        day: 'numeric' 
    });
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Task board view switching
function showTaskView(view) {
    // Update active button
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // In real implementation, this would switch between different view modes
    showAlert(`Switched to ${view} view`, 'info');
}
</script>

<?php include 'layouts/footer.php'; ?>
