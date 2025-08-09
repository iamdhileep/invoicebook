<?php
$page_title = "Company Announcements";
session_start();

// Include database connection with absolute path handling
$db_path = __DIR__ . '/../db.php';
if (!file_exists($db_path)) {
    $db_path = '../db.php';
}
require_once $db_path;

// Check authentication - flexible approach
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include header with absolute path handling
$header_path = __DIR__ . '/../layouts/header.php';
if (!file_exists($header_path)) {
    $header_path = '../layouts/header.php';
}
require_once $header_path;

// Check if user is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

// Get current date for filtering active announcements
$current_date = date('Y-m-d');

// Get active announcements
$announcements_query = "SELECT 
    a.*, 
    CONCAT(e.first_name, ' ', e.last_name) as author_name,
    d.name as department_name
    FROM hr_announcements a 
    LEFT JOIN hr_employees e ON a.author_id = e.id
    LEFT JOIN hr_departments d ON a.department_id = d.id
    WHERE a.is_active = 1 
    AND a.publish_date <= '$current_date'
    AND (a.expiry_date IS NULL OR a.expiry_date >= '$current_date')
    ORDER BY 
        CASE 
            WHEN a.priority = 'urgent' THEN 1
            WHEN a.priority = 'high' THEN 2
            WHEN a.priority = 'medium' THEN 3
            WHEN a.priority = 'low' THEN 4
        END,
        a.created_at DESC";
$announcements_result = $conn->query($announcements_query);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-bullhorn me-2"></i>Company Announcements
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">HRMS</a></li>
                    <li class="breadcrumb-item active">Announcements</li>
                </ol>
            </nav>
        </div>
        <div class="text-muted">
            <i class="fas fa-clock me-2"></i>Last updated: <?= date('M j, Y g:i A') ?>
        </div>
    </div>

    <?php if ($announcements_result->num_rows > 0): ?>
        <div class="row">
            <?php while ($announcement = $announcements_result->fetch_assoc()): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm border-0 
                        <?php 
                        switch($announcement['priority']) {
                            case 'urgent': echo 'border-start border-danger border-4'; break;
                            case 'high': echo 'border-start border-warning border-4'; break;
                            case 'medium': echo 'border-start border-primary border-4'; break;
                            case 'low': echo 'border-start border-secondary border-4'; break;
                        }
                        ?>">
                        <div class="card-header bg-white border-0 pb-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?= htmlspecialchars($announcement['title']) ?>
                                    </h5>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="badge bg-<?php 
                                            switch($announcement['type']) {
                                                case 'general': echo 'secondary'; break;
                                                case 'policy': echo 'primary'; break;
                                                case 'event': echo 'success'; break;
                                                case 'urgent': echo 'danger'; break;
                                                case 'celebration': echo 'warning'; break;
                                                default: echo 'info';
                                            }
                                        ?>">
                                            <i class="fas fa-<?php 
                                                switch($announcement['type']) {
                                                    case 'general': echo 'info-circle'; break;
                                                    case 'policy': echo 'gavel'; break;
                                                    case 'event': echo 'calendar-alt'; break;
                                                    case 'urgent': echo 'exclamation-triangle'; break;
                                                    case 'celebration': echo 'trophy'; break;
                                                    default: echo 'bullhorn';
                                                }
                                            ?> me-1"></i>
                                            <?= ucfirst($announcement['type']) ?>
                                        </span>
                                        
                                        <span class="badge bg-<?php 
                                            switch($announcement['priority']) {
                                                case 'urgent': echo 'danger'; break;
                                                case 'high': echo 'warning'; break;
                                                case 'medium': echo 'primary'; break;
                                                case 'low': echo 'secondary'; break;
                                            }
                                        ?>">
                                            <?= ucfirst($announcement['priority']) ?> Priority
                                        </span>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="shareAnnouncement(<?= $announcement['id'] ?>)">
                                            <i class="fas fa-share me-2"></i>Share
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" onclick="printAnnouncement(<?= $announcement['id'] ?>)">
                                            <i class="fas fa-print me-2"></i>Print
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body pt-2">
                            <p class="card-text text-muted mb-3">
                                <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                            </p>
                            
                            <div class="row text-sm text-muted">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <i class="fas fa-user me-2"></i>
                                        <strong>By:</strong> <?= htmlspecialchars($announcement['author_name'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="mb-2">
                                        <i class="fas fa-calendar me-2"></i>
                                        <strong>Published:</strong> <?= date('M j, Y', strtotime($announcement['publish_date'])) ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <i class="fas fa-users me-2"></i>
                                        <strong>Target:</strong> <?= ucfirst($announcement['target_audience']) ?>
                                        <?php if ($announcement['department_name']): ?>
                                            (<?= htmlspecialchars($announcement['department_name']) ?>)
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($announcement['expiry_date']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-hourglass-end me-2"></i>
                                        <strong>Expires:</strong> 
                                        <span class="<?= strtotime($announcement['expiry_date']) <= strtotime('+3 days') ? 'text-warning fw-bold' : '' ?>">
                                            <?= date('M j, Y', strtotime($announcement['expiry_date'])) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($announcement['priority'] === 'urgent'): ?>
                        <div class="card-footer bg-danger-subtle border-0">
                            <div class="d-flex align-items-center text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>URGENT:</strong> This announcement requires immediate attention.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-bullhorn fa-4x text-muted"></i>
            </div>
            <h3 class="text-muted">No Active Announcements</h3>
            <p class="text-muted">There are currently no active announcements to display.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function shareAnnouncement(id) {
    const url = `${window.location.origin}/billbook/HRMS/view_announcement.php?id=${id}`;
    
    if (navigator.share) {
        navigator.share({
            title: 'Company Announcement',
            url: url
        }).catch(console.error);
    } else {
        // Fallback for browsers that don't support Web Share API
        navigator.clipboard.writeText(url).then(() => {
            alert('Announcement link copied to clipboard!');
        }).catch(() => {
            // Fallback for older browsers
            prompt('Copy this link:', url);
        });
    }
}

function printAnnouncement(id) {
    const printWindow = window.open(`view_announcement.php?id=${id}&print=1`, '_blank');
    printWindow.onload = function() {
        printWindow.print();
    };
}

// Add some animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add hover effects
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
});
</script>

<?php
// Include footer with absolute path handling
$footer_path = __DIR__ . '/../layouts/footer.php';
if (!file_exists($footer_path)) {
    $footer_path = '../layouts/footer.php';
}
require_once $footer_path;
?>
