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

// Get today's date
$today = date('Y-m-d');

// Get statistics
$stats = [];

// Today's appointments
$stats['today_appointments'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM appointments
     WHERE business_id = ? AND appointment_date = ? AND status NOT IN ('cancelled')",
    [$businessId, $today]
)['count'];

// Total clients
$stats['total_clients'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM clients WHERE business_id = ?",
    [$businessId]
)['count'];

// This month's revenue
$thisMonth = date('Y-m');
$stats['month_revenue'] = $db->fetchOne(
    "SELECT SUM(paid_amount) as total FROM appointments
     WHERE business_id = ? AND DATE_FORMAT(appointment_date, '%Y-%m') = ? AND status = 'completed'",
    [$businessId, $thisMonth]
)['total'] ?? 0;

// Pending appointments
$stats['pending_appointments'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM appointments
     WHERE business_id = ? AND status = 'pending' AND appointment_date >= ?",
    [$businessId, $today]
)['count'];

// Get today's appointments
$todayAppointments = $db->fetchAll(
    "SELECT a.*, c.first_name, c.last_name, c.email, c.phone,
            s.name as service_name, st.name as staff_name
     FROM appointments a
     LEFT JOIN clients c ON a.client_id = c.id
     LEFT JOIN services s ON a.service_id = s.id
     LEFT JOIN staff st ON a.staff_id = st.id
     WHERE a.business_id = ? AND a.appointment_date = ?
     ORDER BY a.start_time ASC",
    [$businessId, $today]
);

// Get upcoming appointments (next 7 days)
$nextWeek = date('Y-m-d', strtotime('+7 days'));
$upcomingAppointments = $db->fetchAll(
    "SELECT a.*, c.first_name, c.last_name,
            s.name as service_name, st.name as staff_name
     FROM appointments a
     LEFT JOIN clients c ON a.client_id = c.id
     LEFT JOIN services s ON a.service_id = s.id
     LEFT JOIN staff st ON a.staff_id = st.id
     WHERE a.business_id = ? AND a.appointment_date > ? AND a.appointment_date <= ?
           AND a.status NOT IN ('cancelled', 'no_show')
     ORDER BY a.appointment_date ASC, a.start_time ASC
     LIMIT 10",
    [$businessId, $today, $nextWeek]
);

// Get recent notifications
$notifications = $db->fetchAll(
    "SELECT * FROM notifications
     WHERE business_id = ? AND is_read = 0
     ORDER BY created_at DESC
     LIMIT 5",
    [$businessId]
);

$showWelcome = isset($_GET['welcome']) && $_GET['welcome'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php if ($showWelcome): ?>
                <div class="alert alert-success">
                    <h3>🎉 Welcome to <?php echo SITE_NAME; ?>!</h3>
                    <p>Your account is ready. Let's get you set up:</p>
                    <ol>
                        <li><a href="staff.php">Add your staff members</a></li>
                        <li><a href="services.php">Create your services</a></li>
                        <li><a href="settings.php">Customize your booking page</a></li>
                        <li>Share your booking link: <strong><?php echo SITE_URL; ?>/book/<?php echo $business['booking_page_slug']; ?></strong></li>
                    </ol>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($business['business_name']); ?></p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">📅</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['today_appointments']; ?></h3>
                        <p>Today's Appointments</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">💰</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($stats['month_revenue'], $business['currency']); ?></h3>
                        <p>This Month's Revenue</p>
                        <small>100% yours - No commission!</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_clients']; ?></h3>
                        <p>Total Clients</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">⏳</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_appointments']; ?></h3>
                        <p>Pending Appointments</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Today's Appointments -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Today's Schedule</h2>
                        <a href="appointments.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todayAppointments)): ?>
                            <div class="empty-state">
                                <p>No appointments scheduled for today</p>
                                <a href="appointments.php?action=new" class="btn btn-primary">Add Appointment</a>
                            </div>
                        <?php else: ?>
                            <div class="appointments-list">
                                <?php foreach ($todayAppointments as $apt): ?>
                                    <div class="appointment-item status-<?php echo $apt['status']; ?>">
                                        <div class="appointment-time">
                                            <?php echo formatTime($apt['start_time']); ?>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($apt['service_name']); ?> with <?php echo htmlspecialchars($apt['staff_name']); ?></p>
                                        </div>
                                        <div class="appointment-status">
                                            <span class="badge badge-<?php echo $apt['status']; ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </div>
                                        <div class="appointment-actions">
                                            <a href="appointments.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm">View</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Appointments -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Upcoming (Next 7 Days)</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingAppointments)): ?>
                            <div class="empty-state">
                                <p>No upcoming appointments</p>
                            </div>
                        <?php else: ?>
                            <div class="upcoming-list">
                                <?php foreach ($upcomingAppointments as $apt): ?>
                                    <div class="upcoming-item">
                                        <div class="upcoming-date">
                                            <?php echo formatDate($apt['appointment_date'], 'M d'); ?>
                                        </div>
                                        <div class="upcoming-details">
                                            <h4><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($apt['service_name']); ?> - <?php echo formatTime($apt['start_time']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="actions-grid">
                    <a href="appointments.php?action=new" class="action-card">
                        <div class="action-icon">📝</div>
                        <h3>New Appointment</h3>
                    </a>
                    <a href="clients.php" class="action-card">
                        <div class="action-icon">👤</div>
                        <h3>View Clients</h3>
                    </a>
                    <a href="calendar.php" class="action-card">
                        <div class="action-icon">📆</div>
                        <h3>Calendar View</h3>
                    </a>
                    <a href="settings.php" class="action-card">
                        <div class="action-icon">⚙️</div>
                        <h3>Settings</h3>
                    </a>
                </div>
            </div>

            <!-- Booking Link -->
            <div class="booking-link-card">
                <h3>Your Booking Page</h3>
                <p>Share this link with your customers:</p>
                <div class="link-container">
                    <input type="text" readonly value="<?php echo SITE_URL; ?>/book/<?php echo $business['booking_page_slug']; ?>" id="bookingLink">
                    <button onclick="copyBookingLink()" class="btn btn-primary">Copy Link</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        function copyBookingLink() {
            const input = document.getElementById('bookingLink');
            input.select();
            document.execCommand('copy');
            alert('Booking link copied to clipboard!');
        }
    </script>
</body>
</html>
