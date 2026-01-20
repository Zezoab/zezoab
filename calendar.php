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

// Get current month and year (or from GET params)
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Calculate first and last day of month
$firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$lastDay = mktime(0, 0, 0, $currentMonth + 1, 0, $currentYear);

$startDate = date('Y-m-01', $firstDay);
$endDate = date('Y-m-t', $lastDay);

// Get all appointments for the month
$appointments = $db->fetchAll(
    "SELECT a.*, c.first_name, c.last_name, s.name as service_name,
            st.name as staff_name, st.color as staff_color
     FROM appointments a
     LEFT JOIN clients c ON a.client_id = c.id
     LEFT JOIN services s ON a.service_id = s.id
     LEFT JOIN staff st ON a.staff_id = st.id
     WHERE a.business_id = ? AND a.appointment_date BETWEEN ? AND ?
     AND a.status NOT IN ('cancelled')
     ORDER BY a.appointment_date, a.start_time",
    [$businessId, $startDate, $endDate]
);

// Organize appointments by date
$appointmentsByDate = [];
foreach ($appointments as $apt) {
    $date = $apt['appointment_date'];
    if (!isset($appointmentsByDate[$date])) {
        $appointmentsByDate[$date] = [];
    }
    $appointmentsByDate[$date][] = $apt;
}

// Get staff list for filtering
$staffList = $db->fetchAll(
    "SELECT * FROM staff WHERE business_id = ? AND is_active = 1 ORDER BY name",
    [$businessId]
);

// Get staff exceptions for the month
$staffExceptions = $db->fetchAll(
    "SELECT sae.*, s.name as staff_name, s.color as staff_color
     FROM staff_availability_exceptions sae
     LEFT JOIN staff s ON sae.staff_id = s.id
     WHERE s.business_id = ? AND sae.exception_date BETWEEN ? AND ?
     ORDER BY sae.exception_date",
    [$businessId, $startDate, $endDate]
);

// Organize exceptions by date
$exceptionsByDate = [];
foreach ($staffExceptions as $exc) {
    $date = $exc['exception_date'];
    if (!isset($exceptionsByDate[$date])) {
        $exceptionsByDate[$date] = [];
    }
    $exceptionsByDate[$date][] = $exc;
}

// Navigation dates
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .calendar-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .calendar-nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: #E5E7EB;
            border: 1px solid #E5E7EB;
        }

        .calendar-day-header {
            background-color: #F3F4F6;
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.875rem;
            color: #4B5563;
        }

        .calendar-day {
            background-color: white;
            min-height: 85px;
            padding: 0.4rem;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }

        .calendar-day:hover {
            background-color: #F9FAFB;
            box-shadow: inset 0 0 0 2px var(--primary-color);
            z-index: 1;
        }

        .calendar-day.other-month {
            background-color: #F9FAFB;
            color: #9CA3AF;
        }

        .calendar-day.today {
            background-color: #EFF6FF;
        }

        .day-number {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .day-appointments {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .calendar-appointment {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            line-height: 1.2;
            cursor: pointer;
            border-left: 3px solid;
            background-color: rgba(59, 130, 246, 0.1);
        }

        .calendar-appointment:hover {
            opacity: 0.8;
            transform: scale(1.02);
        }

        .appointment-time {
            font-weight: 600;
        }

        .appointment-client {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Staff Exception Indicators */
        .calendar-exception {
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
            font-size: 0.65rem;
            line-height: 1.2;
            cursor: pointer;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            border: 1px dashed;
            background-color: rgba(239, 68, 68, 0.1);
            border-color: #EF4444;
            color: #991B1B;
        }

        .calendar-exception.custom-hours {
            background-color: rgba(245, 158, 11, 0.1);
            border-color: #F59E0B;
            color: #92400E;
        }

        .calendar-exception:hover {
            transform: scale(1.02);
            opacity: 0.9;
        }

        .exception-icon {
            font-size: 0.7rem;
        }

        .exception-staff {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        /* Day Detail Panel */
        .day-detail-panel {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: right 0.3s;
            overflow-y: auto;
        }

        .day-detail-panel.open {
            right: 0;
        }

        .detail-header {
            padding: 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            background: var(--primary-color);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .detail-header h3 {
            margin: 0;
            color: white;
        }

        .detail-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .detail-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .detail-content {
            padding: 1.5rem;
        }

        .detail-section {
            margin-bottom: 1.5rem;
        }

        .detail-section h4 {
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            color: #6B7280;
            font-weight: 600;
        }

        .detail-item {
            padding: 0.75rem;
            background: #F9FAFB;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            border-left: 3px solid;
        }

        .detail-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.25rem;
        }

        .detail-item-title {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .detail-item-time {
            font-size: 0.813rem;
            color: #6B7280;
        }

        .detail-item-meta {
            font-size: 0.813rem;
            color: #6B7280;
            margin-top: 0.25rem;
        }

        .detail-empty {
            text-align: center;
            padding: 2rem;
            color: #9CA3AF;
        }

        /* Overlay */
        .detail-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .detail-overlay.show {
            display: block;
        }

        /* More indicator */
        .more-indicator {
            font-size: 0.7rem;
            color: #6B7280;
            padding: 0.2rem 0.4rem;
            background: #F3F4F6;
            border-radius: 3px;
            text-align: center;
            cursor: pointer;
        }

        .more-indicator:hover {
            background: #E5E7EB;
        }

        .calendar-legend {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .calendar-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .calendar-grid {
                font-size: 0.75rem;
            }

            .calendar-day {
                min-height: 80px;
                padding: 0.25rem;
            }

            .calendar-appointment {
                font-size: 0.625rem;
            }
        }
    </style>
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h1>Calendar</h1>
                <a href="appointments.php?action=new" class="btn btn-primary">+ New Appointment</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="calendar-header">
                        <h2><?php echo date('F Y', $firstDay); ?></h2>
                        <div class="calendar-nav">
                            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-outline">← Prev</a>
                            <a href="calendar.php" class="btn btn-outline">Today</a>
                            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-outline">Next →</a>
                        </div>
                    </div>

                    <div class="calendar-container">
                        <div class="calendar-grid">
                            <!-- Day headers -->
                            <div class="calendar-day-header">Sun</div>
                            <div class="calendar-day-header">Mon</div>
                            <div class="calendar-day-header">Tue</div>
                            <div class="calendar-day-header">Wed</div>
                            <div class="calendar-day-header">Thu</div>
                            <div class="calendar-day-header">Fri</div>
                            <div class="calendar-day-header">Sat</div>

                            <?php
                            // Get day of week for first day (0 = Sunday)
                            $firstDayOfWeek = date('w', $firstDay);

                            // Days in month
                            $daysInMonth = date('t', $firstDay);

                            // Previous month padding
                            $prevMonthDays = date('t', mktime(0, 0, 0, $currentMonth - 1, 1, $currentYear));
                            for ($i = $firstDayOfWeek - 1; $i >= 0; $i--) {
                                $day = $prevMonthDays - $i;
                                echo '<div class="calendar-day other-month">';
                                echo '<div class="day-number">' . $day . '</div>';
                                echo '</div>';
                            }

                            // Current month days
                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $date = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                $isToday = ($date === date('Y-m-d'));
                                $dayClass = $isToday ? 'calendar-day today' : 'calendar-day';

                                echo '<div class="' . $dayClass . '" onclick="showDayDetail(\'' . $date . '\')">';
                                echo '<div class="day-number">' . $day . '</div>';

                                // Show appointments for this day
                                if (isset($appointmentsByDate[$date])) {
                                    echo '<div class="day-appointments">';
                                    $count = 0;
                                    foreach ($appointmentsByDate[$date] as $apt) {
                                        if ($count >= 3) {
                                            $remaining = count($appointmentsByDate[$date]) - 3;
                                            echo '<div class="calendar-appointment" style="border-color: #6B7280;">';
                                            echo '+' . $remaining . ' more';
                                            echo '</div>';
                                            break;
                                        }

                                        $color = $apt['staff_color'] ?? '#3B82F6';
                                        echo '<div class="calendar-appointment" style="border-color: ' . $color . ';" onclick="event.stopPropagation(); viewAppointment(' . $apt['id'] . ');">';
                                        echo '<div class="appointment-time">' . formatTime($apt['start_time']) . '</div>';
                                        echo '<div class="appointment-client">' . htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) . '</div>';
                                        echo '</div>';
                                        $count++;
                                    }
                                    echo '</div>';
                                }

                                // Show exceptions for this day
                                if (isset($exceptionsByDate[$date])) {
                                    echo '<div class="day-appointments">';
                                    $excCount = 0;
                                    foreach ($exceptionsByDate[$date] as $exc) {
                                        if ($excCount >= 2) {
                                            $remaining = count($exceptionsByDate[$date]) - 2;
                                            echo '<div class="more-indicator">+' . $remaining . ' exception' . ($remaining > 1 ? 's' : '') . '</div>';
                                            break;
                                        }

                                        $excClass = $exc['exception_type'] === 'custom_hours' ? 'calendar-exception custom-hours' : 'calendar-exception';
                                        echo '<div class="' . $excClass . '" onclick="event.stopPropagation(); showDayDetail(\'' . $date . '\');">';
                                        echo '<span class="exception-icon">' . ($exc['exception_type'] === 'unavailable' ? '🚫' : '⏰') . '</span>';
                                        echo '<span class="exception-staff">' . htmlspecialchars($exc['staff_name']) . '</span>';
                                        echo '</div>';
                                        $excCount++;
                                    }
                                    echo '</div>';
                                }

                                echo '</div>';
                            }

                            // Next month padding
                            $totalCells = $firstDayOfWeek + $daysInMonth;
                            $remainingCells = (7 - ($totalCells % 7)) % 7;
                            for ($i = 1; $i <= $remainingCells; $i++) {
                                echo '<div class="calendar-day other-month">';
                                echo '<div class="day-number">' . $i . '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <?php if (!empty($staffList)): ?>
                            <div class="calendar-legend">
                                <strong>Staff:</strong>
                                <?php foreach ($staffList as $staff): ?>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: <?php echo $staff['color']; ?>;"></div>
                                        <span><?php echo htmlspecialchars($staff['name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Month Statistics</h2>
                </div>
                <div class="card-body">
                    <?php
                    $totalApts = count($appointments);
                    $confirmedApts = count(array_filter($appointments, fn($a) => $a['status'] === 'confirmed'));
                    $completedApts = count(array_filter($appointments, fn($a) => $a['status'] === 'completed'));
                    $totalRevenue = array_sum(array_map(fn($a) => $a['status'] === 'completed' ? $a['paid_amount'] : 0, $appointments));
                    ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon blue">📅</div>
                            <div class="stat-info">
                                <h3><?php echo $totalApts; ?></h3>
                                <p>Total Appointments</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon green">✅</div>
                            <div class="stat-info">
                                <h3><?php echo $confirmedApts; ?></h3>
                                <p>Confirmed</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon purple">✔️</div>
                            <div class="stat-info">
                                <h3><?php echo $completedApts; ?></h3>
                                <p>Completed</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon orange">💰</div>
                            <div class="stat-info">
                                <h3><?php echo formatCurrency($totalRevenue, $business['currency']); ?></h3>
                                <p>Revenue (100% yours!)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Detail Panel Overlay -->
    <div class="detail-overlay" onclick="closeDayDetail()"></div>

    <!-- Day Detail Panel -->
    <div class="day-detail-panel" id="dayDetailPanel">
        <div class="detail-header">
            <h3 id="detailDateTitle">Loading...</h3>
            <button class="detail-close" onclick="closeDayDetail()">×</button>
        </div>
        <div class="detail-content" id="detailContent">
            <div class="detail-empty">Loading...</div>
        </div>
    </div>

    <script>
        // Calendar data for JavaScript
        const appointmentsByDate = <?php echo json_encode($appointmentsByDate); ?>;
        const exceptionsByDate = <?php echo json_encode($exceptionsByDate); ?>;

        function viewAppointment(id) {
            window.location.href = 'appointments.php?id=' + id;
        }

        function showDayDetail(date) {
            const panel = document.getElementById('dayDetailPanel');
            const overlay = document.querySelector('.detail-overlay');
            const titleEl = document.getElementById('detailDateTitle');
            const contentEl = document.getElementById('detailContent');

            // Format date for display
            const dateObj = new Date(date + 'T00:00:00');
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            titleEl.textContent = dateObj.toLocaleDateString('en-US', options);

            // Build content
            let html = '';

            // Show appointments
            if (appointmentsByDate[date] && appointmentsByDate[date].length > 0) {
                html += '<div class="detail-section">';
                html += '<h4>Appointments (' + appointmentsByDate[date].length + ')</h4>';
                appointmentsByDate[date].forEach(apt => {
                    const color = apt.staff_color || '#3B82F6';
                    html += '<div class="detail-item" style="border-color: ' + color + ';">';
                    html += '<div class="detail-item-header">';
                    html += '<div class="detail-item-title">' + escapeHtml(apt.first_name + ' ' + apt.last_name) + '</div>';
                    html += '<div class="detail-item-time">' + formatTime(apt.start_time) + '</div>';
                    html += '</div>';
                    html += '<div class="detail-item-meta">';
                    html += escapeHtml(apt.service_name) + ' • ' + escapeHtml(apt.staff_name);
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            // Show exceptions
            if (exceptionsByDate[date] && exceptionsByDate[date].length > 0) {
                html += '<div class="detail-section">';
                html += '<h4>Staff Exceptions (' + exceptionsByDate[date].length + ')</h4>';
                exceptionsByDate[date].forEach(exc => {
                    const isUnavailable = exc.exception_type === 'unavailable';
                    const color = isUnavailable ? '#EF4444' : '#F59E0B';
                    const icon = isUnavailable ? '🚫' : '⏰';
                    const typeLabel = isUnavailable ? 'Unavailable' : 'Custom Hours';

                    html += '<div class="detail-item" style="border-color: ' + color + ';">';
                    html += '<div class="detail-item-header">';
                    html += '<div class="detail-item-title">' + icon + ' ' + escapeHtml(exc.staff_name) + '</div>';
                    html += '<div class="detail-item-time">' + typeLabel + '</div>';
                    html += '</div>';

                    if (exc.exception_type === 'custom_hours' && exc.start_time && exc.end_time) {
                        html += '<div class="detail-item-meta">';
                        html += formatTime(exc.start_time) + ' - ' + formatTime(exc.end_time);
                        html += '</div>';
                    }

                    if (exc.reason) {
                        html += '<div class="detail-item-meta">';
                        html += escapeHtml(exc.reason);
                        html += '</div>';
                    }
                    html += '</div>';
                });
                html += '</div>';
            }

            // If no data
            if (html === '') {
                html = '<div class="detail-empty">No appointments or exceptions on this day</div>';
            }

            contentEl.innerHTML = html;

            // Show panel
            panel.classList.add('open');
            overlay.classList.add('show');
        }

        function closeDayDetail() {
            const panel = document.getElementById('dayDetailPanel');
            const overlay = document.querySelector('.detail-overlay');
            panel.classList.remove('open');
            overlay.classList.remove('show');
        }

        function formatTime(timeStr) {
            if (!timeStr) return '';
            const [hours, minutes] = timeStr.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return displayHour + ':' + minutes + ' ' + ampm;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Close panel with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDayDetail();
            }
        });
    </script>
</body>
</html>
