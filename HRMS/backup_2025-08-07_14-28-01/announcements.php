<?php
session_start();
$page_title = "Announcements - HRMS";

// Include header and navigation
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
include '../db.php';
?>

<!-- Page Content Starts Here -->
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-0">
                                    <i class="fas fa-bullhorn me-2"></i>
                                    Announcements & Communication
                                </h3>
                                <p class="mb-0 opacity-75">Keep your team informed with important updates and news</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#newAnnouncementModal">
                                    <i class="fas fa-plus"></i> New Announcement
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="display-6 text-primary">
                            <i class="fas fa-megaphone"></i>
                        </div>
                        <h4 class="mt-2 mb-0">12</h4>
                        <p class="text-muted mb-0">Active Announcements</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="display-6 text-success">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h4 class="mt-2 mb-0">87%</h4>
                        <p class="text-muted mb-0">Read Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="display-6 text-warning">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h4 class="mt-2 mb-0">5</h4>
                        <p class="text-muted mb-0">Urgent Notices</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="display-6 text-info">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h4 class="mt-2 mb-0">23</h4>
                        <p class="text-muted mb-0">Comments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" placeholder="Search announcements..." id="searchInput">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="priorityFilter">
                                    <option value="">All Priorities</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="normal">Normal</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <option value="general">General</option>
                                    <option value="hr">HR Updates</option>
                                    <option value="policy">Policy</option>
                                    <option value="events">Events</option>
                                    <option value="training">Training</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="draft">Draft</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements List -->
        <div class="row">
            <!-- Urgent Announcements -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Urgent Announcements
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="announcement-item urgent mb-3">
                            <div class="row align-items-start">
                                <div class="col-md-1 text-center">
                                    <div class="announcement-icon bg-danger text-white">
                                        <i class="fas fa-exclamation"></i>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <h6 class="mb-1">System Maintenance Scheduled</h6>
                                    <p class="mb-2 text-muted">The HRMS system will be under maintenance on Saturday from 2:00 AM to 6:00 AM. Please save your work before the scheduled time.</p>
                                    <div class="announcement-meta">
                                        <span class="badge bg-danger me-2">URGENT</span>
                                        <span class="text-muted">
                                            <i class="fas fa-user me-1"></i>IT Department
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-clock me-1"></i>2 hours ago
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-eye me-1"></i>45 views
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-copy me-2"></i>Duplicate</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-share me-2"></i>Share</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="announcement-item urgent">
                            <div class="row align-items-start">
                                <div class="col-md-1 text-center">
                                    <div class="announcement-icon bg-warning text-white">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <h6 class="mb-1">Updated Security Policy</h6>
                                    <p class="mb-2 text-muted">New security protocols have been implemented. All employees must update their passwords and complete the security training by end of week.</p>
                                    <div class="announcement-meta">
                                        <span class="badge bg-warning me-2">HIGH</span>
                                        <span class="text-muted">
                                            <i class="fas fa-user me-1"></i>Security Team
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-clock me-1"></i>1 day ago
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-eye me-1"></i>128 views
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-copy me-2"></i>Duplicate</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-share me-2"></i>Share</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Announcements -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            All Announcements
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="announcement-item mb-3">
                            <div class="row align-items-start">
                                <div class="col-md-1 text-center">
                                    <div class="announcement-icon bg-success text-white">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <h6 class="mb-1">Team Building Event - Registration Open</h6>
                                    <p class="mb-2 text-muted">Join us for our annual team building event on March 15th at Riverside Park. Activities include team challenges, BBQ lunch, and networking sessions.</p>
                                    <div class="announcement-meta">
                                        <span class="badge bg-success me-2">EVENTS</span>
                                        <span class="text-muted">
                                            <i class="fas fa-user me-1"></i>HR Team
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-clock me-1"></i>3 days ago
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-eye me-1"></i>89 views
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-copy me-2"></i>Duplicate</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-share me-2"></i>Share</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="announcement-item mb-3">
                            <div class="row align-items-start">
                                <div class="col-md-1 text-center">
                                    <div class="announcement-icon bg-info text-white">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <h6 class="mb-1">Professional Development Training Session</h6>
                                    <p class="mb-2 text-muted">Enhance your skills with our upcoming training sessions on digital marketing, project management, and leadership development.</p>
                                    <div class="announcement-meta">
                                        <span class="badge bg-info me-2">TRAINING</span>
                                        <span class="text-muted">
                                            <i class="fas fa-user me-1"></i>Training Dept
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-clock me-1"></i>5 days ago
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-eye me-1"></i>67 views
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-copy me-2"></i>Duplicate</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-share me-2"></i>Share</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="announcement-item">
                            <div class="row align-items-start">
                                <div class="col-md-1 text-center">
                                    <div class="announcement-icon bg-primary text-white">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <h6 class="mb-1">Employee of the Month - February 2024</h6>
                                    <p class="mb-2 text-muted">Congratulations to Sarah Johnson from the Marketing team for her outstanding performance and dedication. Keep up the excellent work!</p>
                                    <div class="announcement-meta">
                                        <span class="badge bg-primary me-2">RECOGNITION</span>
                                        <span class="text-muted">
                                            <i class="fas fa-user me-1"></i>Management
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-clock me-1"></i>1 week ago
                                        </span>
                                        <span class="text-muted ms-3">
                                            <i class="fas fa-eye me-1"></i>156 views
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-copy me-2"></i>Duplicate</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-share me-2"></i>Share</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Categories -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-tags me-2"></i>
                            Categories
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-circle text-primary me-2"></i>General</span>
                            <span class="badge bg-light text-dark">5</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-circle text-danger me-2"></i>HR Updates</span>
                            <span class="badge bg-light text-dark">3</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-circle text-warning me-2"></i>Policy</span>
                            <span class="badge bg-light text-dark">2</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-circle text-success me-2"></i>Events</span>
                            <span class="badge bg-light text-dark">4</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-circle text-info me-2"></i>Training</span>
                            <span class="badge bg-light text-dark">2</span>
                        </div>
                    </div>
                </div>

                <!-- Communication Channels -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-broadcast-tower me-2"></i>
                            Communication Channels
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="channel-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="channel-icon bg-primary text-white me-3">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Email Notifications</h6>
                                    <small class="text-muted">Automatic email alerts</small>
                                </div>
                                <div class="ms-auto">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" checked>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="channel-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="channel-icon bg-success text-white me-3">
                                    <i class="fas fa-sms"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">SMS Alerts</h6>
                                    <small class="text-muted">Critical announcements only</small>
                                </div>
                                <div class="ms-auto">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="channel-item">
                            <div class="d-flex align-items-center">
                                <div class="channel-icon bg-info text-white me-3">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Push Notifications</h6>
                                    <small class="text-muted">Real-time updates</small>
                                </div>
                                <div class="ms-auto">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" checked>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#newAnnouncementModal">
                            <i class="fas fa-plus me-2"></i>New Announcement
                        </button>
                        <button class="btn btn-outline-secondary w-100 mb-2">
                            <i class="fas fa-download me-2"></i>Export Reports
                        </button>
                        <button class="btn btn-outline-info w-100">
                            <i class="fas fa-cog me-2"></i>Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Announcement Modal -->
<div class="modal fade" id="newAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-bullhorn text-primary me-2"></i>
                    Create New Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="announcementForm">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" placeholder="Enter announcement title" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select class="form-select" required>
                                <option value="">Select Priority</option>
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="general">General</option>
                                <option value="hr">HR Updates</option>
                                <option value="policy">Policy</option>
                                <option value="events">Events</option>
                                <option value="training">Training</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Audience</label>
                            <select class="form-select" multiple>
                                <option value="all">All Employees</option>
                                <option value="management">Management</option>
                                <option value="hr">HR Department</option>
                                <option value="it">IT Department</option>
                                <option value="finance">Finance</option>
                                <option value="marketing">Marketing</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea class="form-control" rows="6" placeholder="Enter announcement content" required></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Publish Date</label>
                            <input type="datetime-local" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="datetime-local" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="emailNotification">
                            <label class="form-check-label" for="emailNotification">
                                Send email notification
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="pushNotification">
                            <label class="form-check-label" for="pushNotification">
                                Send push notification
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary">Save as Draft</button>
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Publish
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.announcement-item {
    padding: 20px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: white;
    transition: box-shadow 0.2s;
}

.announcement-item:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.announcement-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.announcement-meta {
    font-size: 0.875rem;
}

.channel-item {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.channel-item:last-child {
    border-bottom: none;
}

.channel-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}
</style>

<script>
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('priorityFilter').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('statusFilter').value = '';
}

// Form submission
document.getElementById('announcementForm').addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Announcement created successfully!');
    $('#newAnnouncementModal').modal('hide');
});

// Real-time search
document.getElementById('searchInput').addEventListener('input', function() {
    // Implement search functionality
    console.log('Searching for:', this.value);
});

// Filter change handlers
document.getElementById('priorityFilter').addEventListener('change', function() {
    console.log('Priority filter:', this.value);
});

document.getElementById('categoryFilter').addEventListener('change', function() {
    console.log('Category filter:', this.value);
});

document.getElementById('statusFilter').addEventListener('change', function() {
    console.log('Status filter:', this.value);
});
</script>

<?php require_once 'hrms_footer_simple.php'; 
<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

function loadRecord(id, modalId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal form fields
            Object.keys(data.data).forEach(key => {
                const field = document.getElementById(key) || document.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = data.data[key];
                }
            });
            showModal(modalId);
        } else {
            alert('Error loading record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function deleteRecord(id, confirmMessage = 'Are you sure you want to delete this record?') {
    if (!confirm(confirmMessage)) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Record deleted successfully');
            location.reload();
        } else {
            alert('Error deleting record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function updateStatus(id, status) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&id=' + id + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully');
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to forms with class 'ajax-form'
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operation completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        });
    });
});
</script>

require_once 'hrms_footer_simple.php';
?>