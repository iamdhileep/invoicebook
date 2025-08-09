<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
include '../../layouts/header.php';

// Handle schedule operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_schedule'])) {
        $employee_id = $_POST['employee_id'];
        $schedule_data = [];
        
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            if (isset($_POST[$day . '_active'])) {
                $schedule_data[$day] = [
                    'active' => 1,
                    'start_time' => $_POST[$day . '_start'],
                    'end_time' => $_POST[$day . '_end']
                ];
            } else {
                $schedule_data[$day] = ['active' => 0];
            }
        }
        
        // Delete existing schedule
        $deleteQuery = $conn->prepare("DELETE FROM employee_schedules WHERE employee_id = ?");
        $deleteQuery->bind_param("i", $employee_id);
        $deleteQuery->execute();
        
        // Insert new schedule
        foreach ($schedule_data as $day => $data) {
            if ($data['active']) {
                $insertQuery = $conn->prepare("
                    INSERT INTO employee_schedules 
                    (employee_id, day_of_week, start_time, end_time, is_active) 
                    VALUES (?, ?, ?, ?, 1)
                ");
                $insertQuery->bind_param("isss", $employee_id, $day, $data['start_time'], $data['end_time']);
                $insertQuery->execute();
            }
        }
        
        $success_message = "Schedule updated successfully!";
    }
}

// Get all employees
$employees = [];
$empQuery = "SELECT employee_id as id, name FROM employees ORDER BY name";
$empResult = $conn->query($empQuery);
if ($empResult) {
    while ($row = $empResult->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Get schedule for selected employee
$selected_employee = $_GET['employee'] ?? ($employees[0]['id'] ?? null);
$schedule = [];
if ($selected_employee) {
    $schedQuery = $conn->prepare("SELECT * FROM employee_schedules WHERE employee_id = ?");
    $schedQuery->bind_param("i", $selected_employee);
    $schedQuery->execute();
    $schedResult = $schedQuery->get_result();
    while ($row = $schedResult->fetch_assoc()) {
        $schedule[$row['day_of_week']] = $row;
    }
}
?>

<style>
    .schedule-container {
        padding: 20px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .schedule-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .day-schedule {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }
    
    .day-schedule.active {
        border-color: #0d6efd;
        background-color: #f8f9ff;
    }
    
    .day-schedule.inactive {
        background-color: #f8f9fa;
        opacity: 0.7;
    }
    
    .day-toggle {
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .time-inputs {
        transition: opacity 0.3s ease;
    }
    
    .time-inputs.disabled {
        opacity: 0.5;
        pointer-events: none;
    }
</style>

<div class="main-content">
    <?php include '../../layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="schedule-container">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Employee Schedules</h1>
                    <p class="text-muted">Manage working hours for each employee</p>
                </div>
                <a href="time_tracking.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Time Tracking
                </a>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Employee Selection -->
            <div class="schedule-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-0">Select Employee</h5>
                        <p class="text-muted mb-0">Choose an employee to manage their schedule</p>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" onchange="window.location.href='?employee=' + this.value">
                            <option value="">Choose Employee...</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= $selected_employee == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if ($selected_employee): ?>
                <!-- Schedule Form -->
                <div class="schedule-card">
                    <form method="POST">
                        <input type="hidden" name="employee_id" value="<?= $selected_employee ?>">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Weekly Schedule</h5>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllDays(true)">
                                    Enable All Days
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllDays(false)">
                                    Disable All Days
                                </button>
                            </div>
                        </div>

                        <?php
                        $days = [
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday', 
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday',
                            'sunday' => 'Sunday'
                        ];

                        foreach ($days as $day => $dayName):
                            $daySchedule = $schedule[$day] ?? null;
                            $isActive = $daySchedule ? $daySchedule['is_active'] : false;
                            $startTime = $daySchedule ? $daySchedule['start_time'] : '09:00';
                            $endTime = $daySchedule ? $daySchedule['end_time'] : '18:00';
                        ?>
                            <div class="day-schedule <?= $isActive ? 'active' : 'inactive' ?>" id="schedule_<?= $day ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input day-checkbox" type="checkbox" 
                                                   name="<?= $day ?>_active" id="<?= $day ?>_active" 
                                                   <?= $isActive ? 'checked' : '' ?>
                                                   onchange="toggleDay('<?= $day ?>')">
                                            <label class="form-check-label day-toggle" for="<?= $day ?>_active">
                                                <?= $dayName ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="row time-inputs <?= !$isActive ? 'disabled' : '' ?>" id="times_<?= $day ?>">
                                            <div class="col-md-4">
                                                <label class="form-label">Start Time</label>
                                                <input type="time" class="form-control" name="<?= $day ?>_start" 
                                                       value="<?= $startTime ?>" <?= !$isActive ? 'disabled' : '' ?>>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">End Time</label>
                                                <input type="time" class="form-control" name="<?= $day ?>_end" 
                                                       value="<?= $endTime ?>" <?= !$isActive ? 'disabled' : '' ?>>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Duration</label>
                                                <div class="form-control-plaintext" id="duration_<?= $day ?>">
                                                    <?php
                                                    if ($isActive) {
                                                        $start = strtotime($startTime);
                                                        $end = strtotime($endTime);
                                                        $duration = ($end - $start) / 3600;
                                                        echo number_format($duration, 1) . ' hours';
                                                    } else {
                                                        echo 'Off Day';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="save_schedule" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Save Schedule
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="schedule-card text-center">
                            <i class="bi bi-calendar-week text-primary fs-1 mb-3"></i>
                            <h6>Working Days</h6>
                            <h4 class="text-primary" id="working-days-count">
                                <?= count(array_filter($schedule, fn($s) => $s['is_active'] ?? false)) ?>
                            </h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="schedule-card text-center">
                            <i class="bi bi-clock text-success fs-1 mb-3"></i>
                            <h6>Weekly Hours</h6>
                            <h4 class="text-success" id="weekly-hours-count">
                                <?php
                                $totalHours = 0;
                                foreach ($schedule as $daySchedule) {
                                    if ($daySchedule['is_active']) {
                                        $start = strtotime($daySchedule['start_time']);
                                        $end = strtotime($daySchedule['end_time']);
                                        $totalHours += ($end - $start) / 3600;
                                    }
                                }
                                echo number_format($totalHours, 1);
                                ?>
                            </h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="schedule-card text-center">
                            <i class="bi bi-moon text-warning fs-1 mb-3"></i>
                            <h6>Rest Days</h6>
                            <h4 class="text-warning" id="rest-days-count">
                                <?= 7 - count(array_filter($schedule, fn($s) => $s['is_active'] ?? false)) ?>
                            </h4>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleDay(day) {
    const checkbox = document.getElementById(day + '_active');
    const daySchedule = document.getElementById('schedule_' + day);
    const timeInputs = document.getElementById('times_' + day);
    const startInput = document.querySelector(`input[name="${day}_start"]`);
    const endInput = document.querySelector(`input[name="${day}_end"]`);
    
    if (checkbox.checked) {
        daySchedule.classList.remove('inactive');
        daySchedule.classList.add('active');
        timeInputs.classList.remove('disabled');
        startInput.disabled = false;
        endInput.disabled = false;
    } else {
        daySchedule.classList.remove('active');
        daySchedule.classList.add('inactive');
        timeInputs.classList.add('disabled');
        startInput.disabled = true;
        endInput.disabled = true;
    }
    
    updateDuration(day);
    updateStats();
}

function toggleAllDays(enable) {
    const checkboxes = document.querySelectorAll('.day-checkbox');
    checkboxes.forEach(checkbox => {
        if (checkbox.checked !== enable) {
            checkbox.checked = enable;
            const day = checkbox.name.replace('_active', '');
            toggleDay(day);
        }
    });
}

function updateDuration(day) {
    const startInput = document.querySelector(`input[name="${day}_start"]`);
    const endInput = document.querySelector(`input[name="${day}_end"]`);
    const durationDiv = document.getElementById('duration_' + day);
    const checkbox = document.getElementById(day + '_active');
    
    if (!checkbox.checked) {
        durationDiv.textContent = 'Off Day';
        return;
    }
    
    const start = new Date('2000-01-01 ' + startInput.value);
    const end = new Date('2000-01-01 ' + endInput.value);
    const duration = (end - start) / (1000 * 60 * 60);
    
    if (duration > 0) {
        durationDiv.textContent = duration.toFixed(1) + ' hours';
    } else {
        durationDiv.textContent = 'Invalid';
    }
}

function updateStats() {
    const checkboxes = document.querySelectorAll('.day-checkbox:checked');
    const workingDays = checkboxes.length;
    const restDays = 7 - workingDays;
    
    let totalHours = 0;
    checkboxes.forEach(checkbox => {
        const day = checkbox.name.replace('_active', '');
        const startInput = document.querySelector(`input[name="${day}_start"]`);
        const endInput = document.querySelector(`input[name="${day}_end"]`);
        
        const start = new Date('2000-01-01 ' + startInput.value);
        const end = new Date('2000-01-01 ' + endInput.value);
        const duration = (end - start) / (1000 * 60 * 60);
        
        if (duration > 0) {
            totalHours += duration;
        }
    });
    
    document.getElementById('working-days-count').textContent = workingDays;
    document.getElementById('weekly-hours-count').textContent = totalHours.toFixed(1);
    document.getElementById('rest-days-count').textContent = restDays;
}

// Add event listeners for time inputs
document.addEventListener('DOMContentLoaded', function() {
    const timeInputs = document.querySelectorAll('input[type="time"]');
    timeInputs.forEach(input => {
        input.addEventListener('change', function() {
            const day = this.name.replace('_start', '').replace('_end', '');
            updateDuration(day);
            updateStats();
        });
    });
});
</script>

<?php include '../../layouts/footer.php'; ?>
