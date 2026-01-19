<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$businessId = $auth->getBusinessId();
$business = $auth->getBusiness();

$error = '';
$success = '';

// Get staff ID from URL
$staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

if (!$staffId) {
    header('Location: staff.php');
    exit;
}

// Get staff member
$staff = $db->fetchOne(
    "SELECT * FROM staff WHERE id = ? AND business_id = ?",
    [$staffId, $businessId]
);

if (!$staff) {
    header('Location: staff.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_pattern') {
        $pattern = sanitize($_POST['schedule_pattern'] ?? 'weekly');
        $biweeklyWeek = (int)($_POST['biweekly_week'] ?? 1);

        $updated = $db->update('staff', [
            'schedule_pattern' => $pattern,
            'biweekly_week' => $biweeklyWeek
        ], 'id = :id', ['id' => $staffId]);

        if ($updated) {
            $success = 'Schedule pattern updated successfully';
            $staff['schedule_pattern'] = $pattern;
            $staff['biweekly_week'] = $biweeklyWeek;
        } else {
            $error = 'Failed to update schedule pattern';
        }
    } elseif ($action === 'update_hours') {
        // Update regular working hours
        // First, delete existing hours for this staff member
        $db->delete('working_hours', 'staff_id = :staff_id', ['staff_id' => $staffId]);

        // Insert new hours
        for ($day = 0; $day <= 6; $day++) {
            $isWorking = isset($_POST["day_$day"]);
            $startTime = sanitize($_POST["start_time_$day"] ?? '09:00');
            $endTime = sanitize($_POST["end_time_$day"] ?? '17:00');

            $db->insert('working_hours', [
                'staff_id' => $staffId,
                'business_id' => $businessId,
                'day_of_week' => $day,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_closed' => $isWorking ? 0 : 1
            ]);
        }

        $success = 'Working hours updated successfully';
    } elseif ($action === 'add_exception') {
        $exceptionDate = sanitize($_POST['exception_date'] ?? '');
        $exceptionType = sanitize($_POST['exception_type'] ?? 'unavailable');
        $startTime = sanitize($_POST['exception_start_time'] ?? null);
        $endTime = sanitize($_POST['exception_end_time'] ?? null);
        $reason = sanitize($_POST['reason'] ?? '');

        if (empty($exceptionDate)) {
            $error = 'Please select a date';
        } else {
            $inserted = $db->insert('staff_availability_exceptions', [
                'staff_id' => $staffId,
                'exception_date' => $exceptionDate,
                'exception_type' => $exceptionType,
                'start_time' => $exceptionType === 'custom_hours' ? $startTime : null,
                'end_time' => $exceptionType === 'custom_hours' ? $endTime : null,
                'reason' => $reason
            ]);

            if ($inserted) {
                $success = 'Exception added successfully';
            } else {
                $error = 'Failed to add exception';
            }
        }
    } elseif ($action === 'delete_exception') {
        $exceptionId = (int)($_POST['exception_id'] ?? 0);

        $deleted = $db->delete(
            'staff_availability_exceptions',
            'id = :id AND staff_id = :staff_id',
            ['id' => $exceptionId, 'staff_id' => $staffId]
        );

        if ($deleted) {
            $success = 'Exception deleted successfully';
        } else {
            $error = 'Failed to delete exception';
        }
    }
}

// Get current working hours
$workingHours = [];
$hours = $db->fetchAll(
    "SELECT * FROM working_hours WHERE staff_id = ? ORDER BY day_of_week",
    [$staffId]
);

foreach ($hours as $hour) {
    $workingHours[$hour['day_of_week']] = $hour;
}

// Get exceptions
$exceptions = $db->fetchAll(
    "SELECT * FROM staff_availability_exceptions
     WHERE staff_id = ? AND exception_date >= CURDATE()
     ORDER BY exception_date",
    [$staffId]
);

$daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Schedule - <?php echo htmlspecialchars($staff['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h1>Schedule for <?php echo htmlspecialchars($staff['name']); ?></h1>
                <a href="staff.php" class="btn btn-outline">← Back to Staff</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Schedule Pattern -->
            <div class="card">
                <div class="card-header">
                    <h2>📅 Schedule Pattern</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="update_pattern">

                        <div class="form-group">
                            <label>How does this staff member work?</label>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <label class="checkbox-label" style="padding: 1rem; border: 2px solid #E5E7EB; border-radius: 8px;">
                                    <input type="radio" name="schedule_pattern" value="weekly"
                                           <?php echo (!isset($staff['schedule_pattern']) || $staff['schedule_pattern'] === 'weekly') ? 'checked' : ''; ?>>
                                    <div>
                                        <strong>Every Week</strong>
                                        <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: #6B7280;">
                                            Works the same days every week
                                        </p>
                                    </div>
                                </label>

                                <label class="checkbox-label" style="padding: 1rem; border: 2px solid #E5E7EB; border-radius: 8px;">
                                    <input type="radio" name="schedule_pattern" value="biweekly"
                                           <?php echo (isset($staff['schedule_pattern']) && $staff['schedule_pattern'] === 'biweekly') ? 'checked' : ''; ?>>
                                    <div>
                                        <strong>Every Other Week (Alternating)</strong>
                                        <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: #6B7280;">
                                            Works alternating weeks (e.g., Week 1, Week 3, Week 5...)
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="biweeklyOptions" style="display: <?php echo (isset($staff['schedule_pattern']) && $staff['schedule_pattern'] === 'biweekly') ? 'block' : 'none'; ?>; margin-top: 1rem;">
                            <div class="form-group">
                                <label>Current Week Selection</label>
                                <p style="font-size: 0.875rem; color: #6B7280; margin-bottom: 0.5rem;">
                                    This week is <strong>Week <?php echo (date('W') % 2) + 1; ?></strong> of the alternating pattern.
                                </p>
                                <select name="biweekly_week">
                                    <option value="1" <?php echo (isset($staff['biweekly_week']) && $staff['biweekly_week'] == 1) ? 'selected' : ''; ?>>
                                        Works on odd weeks (Week 1, 3, 5...)
                                    </option>
                                    <option value="2" <?php echo (isset($staff['biweekly_week']) && $staff['biweekly_week'] == 2) ? 'selected' : ''; ?>>
                                        Works on even weeks (Week 2, 4, 6...)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Schedule Pattern</button>
                    </form>
                </div>
            </div>

            <!-- Weekly Schedule -->
            <div class="card">
                <div class="card-header">
                    <h2>🕒 Weekly Working Hours</h2>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1.5rem; color: #6B7280;">
                        Set which days and what times <?php echo htmlspecialchars($staff['name']); ?> is available to work.
                    </p>

                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="update_hours">

                        <?php foreach ($daysOfWeek as $dayNum => $dayName): ?>
                            <?php
                            $dayData = $workingHours[$dayNum] ?? null;
                            $isWorking = $dayData ? !$dayData['is_closed'] : ($dayNum >= 1 && $dayNum <= 5); // Default Mon-Fri
                            $startTime = $dayData ? substr($dayData['start_time'], 0, 5) : '09:00';
                            $endTime = $dayData ? substr($dayData['end_time'], 0, 5) : '17:00';
                            ?>
                            <div style="display: grid; grid-template-columns: 120px 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid #E5E7EB;">
                                <label class="checkbox-label" style="margin-bottom: 0;">
                                    <input type="checkbox" name="day_<?php echo $dayNum; ?>" <?php echo $isWorking ? 'checked' : ''; ?>
                                           onchange="toggleDay(<?php echo $dayNum; ?>)">
                                    <strong><?php echo $dayName; ?></strong>
                                </label>

                                <div>
                                    <label for="start_time_<?php echo $dayNum; ?>" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Start Time</label>
                                    <input type="time" id="start_time_<?php echo $dayNum; ?>" name="start_time_<?php echo $dayNum; ?>"
                                           value="<?php echo $startTime; ?>" <?php echo !$isWorking ? 'disabled' : ''; ?>>
                                </div>

                                <div>
                                    <label for="end_time_<?php echo $dayNum; ?>" style="font-size: 0.875rem; margin-bottom: 0.25rem;">End Time</label>
                                    <input type="time" id="end_time_<?php echo $dayNum; ?>" name="end_time_<?php echo $dayNum; ?>"
                                           value="<?php echo $endTime; ?>" <?php echo !$isWorking ? 'disabled' : ''; ?>>
                                </div>

                                <div style="color: #6B7280; font-size: 0.875rem;" id="day_status_<?php echo $dayNum; ?>">
                                    <?php echo $isWorking ? 'Working' : 'Closed'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Save Working Hours</button>
                    </form>
                </div>
            </div>

            <!-- Exceptions (Time Off / Blocked Dates) -->
            <div class="card">
                <div class="card-header">
                    <h2>🚫 Exceptions & Time Off</h2>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1.5rem; color: #6B7280;">
                        Block specific dates when <?php echo htmlspecialchars($staff['name']); ?> is unavailable or has different hours.
                    </p>

                    <!-- Add Exception Form -->
                    <form method="POST" action="" class="form" style="background: #F9FAFB; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <input type="hidden" name="action" value="add_exception">

                        <h3>Add New Exception</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="exception_date">Date *</label>
                                <input type="date" id="exception_date" name="exception_date" required
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="form-group">
                                <label for="exception_type">Type *</label>
                                <select id="exception_type" name="exception_type" required onchange="toggleCustomHours()">
                                    <option value="unavailable">Unavailable (Day Off)</option>
                                    <option value="custom_hours">Custom Hours</option>
                                </select>
                            </div>
                        </div>

                        <div id="customHoursFields" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="exception_start_time">Start Time</label>
                                    <input type="time" id="exception_start_time" name="exception_start_time">
                                </div>

                                <div class="form-group">
                                    <label for="exception_end_time">End Time</label>
                                    <input type="time" id="exception_end_time" name="exception_end_time">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason (optional)</label>
                            <input type="text" id="reason" name="reason" placeholder="e.g., Vacation, Doctor appointment">
                        </div>

                        <button type="submit" class="btn btn-primary">Add Exception</button>
                    </form>

                    <!-- List Exceptions -->
                    <?php if (empty($exceptions)): ?>
                        <p>No upcoming exceptions</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Hours</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exceptions as $exception): ?>
                                    <tr>
                                        <td><?php echo formatDate($exception['exception_date']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $exception['exception_type'] === 'unavailable' ? 'cancelled' : 'pending'; ?>">
                                                <?php echo $exception['exception_type'] === 'unavailable' ? 'Unavailable' : 'Custom Hours'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($exception['exception_type'] === 'custom_hours'): ?>
                                                <?php echo formatTime($exception['start_time']); ?> - <?php echo formatTime($exception['end_time']); ?>
                                            <?php else: ?>
                                                All Day
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($exception['reason'] ?: '-'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this exception?');">
                                                <input type="hidden" name="action" value="delete_exception">
                                                <input type="hidden" name="exception_id" value="<?php echo $exception['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card">
                <h3>💡 How Staff Scheduling Works</h3>
                <ul>
                    <li><strong>Schedule Pattern:</strong> Choose if they work every week or alternating weeks</li>
                    <li><strong>Weekly Hours:</strong> Set which days and what times they're available</li>
                    <li><strong>Exceptions:</strong> Block specific dates for vacation, time off, or special hours</li>
                    <li>The booking system will automatically show only available times based on these settings</li>
                </ul>
            </div>
        </div>
    </main>

    <script>
        function toggleDay(dayNum) {
            const checkbox = document.querySelector(`input[name="day_${dayNum}"]`);
            const startTime = document.getElementById(`start_time_${dayNum}`);
            const endTime = document.getElementById(`end_time_${dayNum}`);
            const status = document.getElementById(`day_status_${dayNum}`);

            if (checkbox.checked) {
                startTime.disabled = false;
                endTime.disabled = false;
                status.textContent = 'Working';
            } else {
                startTime.disabled = true;
                endTime.disabled = true;
                status.textContent = 'Closed';
            }
        }

        function toggleCustomHours() {
            const type = document.getElementById('exception_type').value;
            const customHours = document.getElementById('customHoursFields');

            if (type === 'custom_hours') {
                customHours.style.display = 'block';
                document.getElementById('exception_start_time').required = true;
                document.getElementById('exception_end_time').required = true;
            } else {
                customHours.style.display = 'none';
                document.getElementById('exception_start_time').required = false;
                document.getElementById('exception_end_time').required = false;
            }
        }

        // Handle schedule pattern changes
        document.querySelectorAll('input[name="schedule_pattern"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const biweeklyOptions = document.getElementById('biweeklyOptions');
                if (this.value === 'biweekly') {
                    biweeklyOptions.style.display = 'block';
                } else {
                    biweeklyOptions.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
