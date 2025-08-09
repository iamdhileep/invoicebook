<?php
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

// Get announcement ID
$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

if (!$announcement_id) {
    header("Location: announcements.php");
    exit();
}

// Get announcement details
$stmt = $conn->prepare("SELECT 
    a.*, 
    CONCAT(e.first_name, ' ', e.last_name) as author_name,
    d.name as department_name
    FROM hr_announcements a 
    LEFT JOIN hr_employees e ON a.author_id = e.id
    LEFT JOIN hr_departments d ON a.department_id = d.id
    WHERE a.id = ?");
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: announcements.php");
    exit();
}

$announcement = $result->fetch_assoc();

if (!$print_mode) {
    // Include header with absolute path handling
    $header_path = __DIR__ . '/../layouts/header.php';
    if (!file_exists($header_path)) {
        $header_path = '../layouts/header.php';
    }
    require_once $header_path;
}
?>

<?php if ($print_mode): ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print Announcement - <?= htmlspecialchars($announcement['title']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { border-bottom: 2px solid #ccc; padding-bottom: 20px; margin-bottom: 30px; }
        .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .announcement-title { font-size: 20px; font-weight: bold; margin: 20px 0; }
        .meta-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .content { line-height: 1.6; margin: 20px 0; }
        .priority-urgent { color: #dc3545; font-weight: bold; }
        .priority-high { color: #fd7e14; font-weight: bold; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-primary { background: #0d6efd; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
<?php else: ?>
<div class="container-fluid">
<?php endif; ?>

    <?php if (!$print_mode): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">HRMS</a></li>
                    <li class="breadcrumb-item"><a href="announcements.php">Announcements</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars(substr($announcement['title'], 0, 30)) ?>...</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            <button class="btn btn-secondary" onclick="window.history.back()">
                <i class="fas fa-arrow-left me-2"></i>Back
            </button>
            <button class="btn btn-primary" onclick="printAnnouncement()">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <button class="btn btn-info" onclick="shareAnnouncement()">
                <i class="fas fa-share me-2"></i>Share
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($print_mode): ?>
    <div class="header">
        <div class="company-name">Company Name</div>
        <div style="font-size: 14px; color: #666;">Internal Announcement</div>
    </div>
    <?php endif; ?>

    <div class="<?= $print_mode ? '' : 'card shadow' ?>">
        <?php if (!$print_mode): ?>
        <div class="card-header border-0 bg-white">
        <?php endif; ?>
            <div class="d-flex justify-content-between align-items-start <?= $print_mode ? 'mb-3' : '' ?>">
                <div>
                    <h1 class="<?= $print_mode ? 'announcement-title' : 'h3 mb-2' ?>">
                        <?= htmlspecialchars($announcement['title']) ?>
                    </h1>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge <?= $print_mode ? 'badge-primary' : 'bg-primary' ?>">
                            <?= ucfirst($announcement['type']) ?>
                        </span>
                        <span class="badge <?php 
                            $priority_class = $print_mode ? 'badge-' : 'bg-';
                            switch($announcement['priority']) {
                                case 'urgent': echo $priority_class . 'danger'; break;
                                case 'high': echo $priority_class . 'warning'; break;
                                case 'medium': echo $priority_class . 'primary'; break;
                                case 'low': echo $priority_class . 'secondary'; break;
                            }
                        ?>">
                            <?= ucfirst($announcement['priority']) ?> Priority
                        </span>
                    </div>
                </div>
                <?php if (!$print_mode): ?>
                <div class="text-muted">
                    <small>ID: #<?= $announcement['id'] ?></small>
                </div>
                <?php endif; ?>
            </div>
        <?php if (!$print_mode): ?>
        </div>
        <?php endif; ?>

        <div class="<?= $print_mode ? 'meta-info' : 'card-body' ?>">
            <?php if ($announcement['priority'] === 'urgent'): ?>
            <div class="alert alert-danger border-0 mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>URGENT ANNOUNCEMENT</strong> - This requires immediate attention.
                </div>
            </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong><i class="fas fa-user me-2"></i>Author:</strong>
                        <?= htmlspecialchars($announcement['author_name'] ?? 'Unknown') ?>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-calendar me-2"></i>Published:</strong>
                        <?= date('F j, Y \a\t g:i A', strtotime($announcement['publish_date'])) ?>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-users me-2"></i>Target Audience:</strong>
                        <?= ucfirst($announcement['target_audience']) ?>
                        <?php if ($announcement['department_name']): ?>
                            - <?= htmlspecialchars($announcement['department_name']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php if ($announcement['expiry_date']): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-hourglass-end me-2"></i>Expires:</strong>
                        <span class="<?= strtotime($announcement['expiry_date']) <= strtotime('+3 days') ? 'text-warning fw-bold' : '' ?>">
                            <?= date('F j, Y', strtotime($announcement['expiry_date'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-clock me-2"></i>Created:</strong>
                        <?= date('F j, Y \a\t g:i A', strtotime($announcement['created_at'])) ?>
                    </div>
                    <?php if ($announcement['updated_at'] !== $announcement['created_at']): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-edit me-2"></i>Last Updated:</strong>
                        <?= date('F j, Y \a\t g:i A', strtotime($announcement['updated_at'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <hr>

            <div class="<?= $print_mode ? 'content' : '' ?>">
                <h4 class="mb-3">Announcement Details:</h4>
                <div class="lh-lg">
                    <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                </div>
            </div>
        </div>

        <?php if (!$print_mode): ?>
        <div class="card-footer bg-light border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        This announcement is <?= $announcement['is_active'] ? 'currently active' : 'inactive' ?>
                    </small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="printAnnouncement()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php if ($print_mode): ?>
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; color: #666; font-size: 12px;">
        <p>This announcement was printed on <?= date('F j, Y \a\t g:i A') ?></p>
        <p>For questions or concerns, please contact the HR department.</p>
    </div>
</body>
</html>
<?php else: ?>
</div>

<script>
function printAnnouncement() {
    const url = `<?= $_SERVER['PHP_SELF'] ?>?id=<?= $announcement_id ?>&print=1`;
    const printWindow = window.open(url, '_blank', 'width=800,height=600');
    printWindow.onload = function() {
        printWindow.print();
    };
}

function shareAnnouncement() {
    const url = window.location.href;
    const title = '<?= htmlspecialchars($announcement['title']) ?>';
    
    if (navigator.share) {
        navigator.share({
            title: `Company Announcement: ${title}`,
            url: url
        }).catch(console.error);
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('Announcement link copied to clipboard!');
        }).catch(() => {
            prompt('Copy this link:', url);
        });
    }
}
</script>

<?php
// Include footer with absolute path handling
$footer_path = __DIR__ . '/../layouts/footer.php';
if (!file_exists($footer_path)) {
    $footer_path = '../layouts/footer.php';
}
require_once $footer_path;
endif;
?>
