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

// Get date range
$range = $_GET['range'] ?? 'this_month';
$startDate = '';
$endDate = date('Y-m-d');

switch ($range) {
    case 'today':
        $startDate = date('Y-m-d');
        break;
    case 'yesterday':
        $startDate = date('Y-m-d', strtotime('-1 day'));
        $endDate = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'this_week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'last_week':
        $startDate = date('Y-m-d', strtotime('monday last week'));
        $endDate = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'this_month':
        $startDate = date('Y-m-01');
        break;
    case 'last_month':
        $startDate = date('Y-m-01', strtotime('first day of last month'));
        $endDate = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_year':
        $startDate = date('Y-01-01');
        break;
    case 'custom':
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        break;
    default:
        $startDate = date('Y-m-01');
}

// Revenue Statistics
$revenueStats = $db->fetchOne(
    "SELECT
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_appointments,
        COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show_appointments,
        SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'completed' THEN paid_amount ELSE 0 END) as collected_revenue,
        AVG(CASE WHEN status = 'completed' THEN price ELSE NULL END) as avg_appointment_value
     FROM appointments
     WHERE business_id = ? AND appointment_date BETWEEN ? AND ?",
    [$businessId, $startDate, $endDate]
);

// New clients
$newClients = $db->fetchOne(
    "SELECT COUNT(*) as count FROM clients
     WHERE business_id = ? AND DATE(created_at) BETWEEN ? AND ?",
    [$businessId, $startDate, $endDate]
)['count'];

// Top services
$topServices = $db->fetchAll(
    "SELECT s.name, COUNT(*) as bookings, SUM(a.paid_amount) as revenue
     FROM appointments a
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.business_id = ? AND a.appointment_date BETWEEN ? AND ?
     AND a.status IN ('completed', 'confirmed')
     GROUP BY s.id
     ORDER BY bookings DESC
     LIMIT 5",
    [$businessId, $startDate, $endDate]
);

// Top staff performance
$topStaff = $db->fetchAll(
    "SELECT st.name, COUNT(*) as appointments, SUM(a.paid_amount) as revenue
     FROM appointments a
     LEFT JOIN staff st ON a.staff_id = st.id
     WHERE a.business_id = ? AND a.appointment_date BETWEEN ? AND ?
     AND a.status IN ('completed', 'confirmed')
     GROUP BY st.id
     ORDER BY appointments DESC
     LIMIT 5",
    [$businessId, $startDate, $endDate]
);

// Revenue by day (for chart)
$dailyRevenue = $db->fetchAll(
    "SELECT appointment_date as date, SUM(paid_amount) as revenue, COUNT(*) as appointments
     FROM appointments
     WHERE business_id = ? AND appointment_date BETWEEN ? AND ?
     AND status = 'completed'
     GROUP BY appointment_date
     ORDER BY appointment_date",
    [$businessId, $startDate, $endDate]
);

// Referral sources
$referralSources = $db->fetchAll(
    "SELECT referral_source, COUNT(*) as count
     FROM clients
     WHERE business_id = ? AND referral_source IS NOT NULL AND referral_source != ''
     GROUP BY referral_source
     ORDER BY count DESC",
    [$businessId]
);

// Payment methods
$paymentMethods = $db->fetchAll(
    "SELECT payment_method, COUNT(*) as count, SUM(paid_amount) as total
     FROM appointments
     WHERE business_id = ? AND appointment_date BETWEEN ? AND ?
     AND status = 'completed' AND payment_method != 'none'
     GROUP BY payment_method
     ORDER BY total DESC",
    [$businessId, $startDate, $endDate]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h1>Reports & Analytics</h1>
            </div>

            <!-- Date Range Selector -->
            <div class="card">
                <div class="card-header">
                    <h2>Date Range</h2>
                </div>
                <div class="card-body">
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="?range=today" class="btn btn-sm <?php echo $range === 'today' ? 'btn-primary' : 'btn-outline'; ?>">Today</a>
                        <a href="?range=yesterday" class="btn btn-sm <?php echo $range === 'yesterday' ? 'btn-primary' : 'btn-outline'; ?>">Yesterday</a>
                        <a href="?range=this_week" class="btn btn-sm <?php echo $range === 'this_week' ? 'btn-primary' : 'btn-outline'; ?>">This Week</a>
                        <a href="?range=last_week" class="btn btn-sm <?php echo $range === 'last_week' ? 'btn-primary' : 'btn-outline'; ?>">Last Week</a>
                        <a href="?range=this_month" class="btn btn-sm <?php echo $range === 'this_month' ? 'btn-primary' : 'btn-outline'; ?>">This Month</a>
                        <a href="?range=last_month" class="btn btn-sm <?php echo $range === 'last_month' ? 'btn-primary' : 'btn-outline'; ?>">Last Month</a>
                        <a href="?range=this_year" class="btn btn-sm <?php echo $range === 'this_year' ? 'btn-primary' : 'btn-outline'; ?>">This Year</a>
                    </div>

                    <form method="GET" style="margin-top: 1rem; display: flex; gap: 0.5rem; align-items: end;">
                        <input type="hidden" name="range" value="custom">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="start_date" style="font-size: 0.875rem;">From</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $range === 'custom' ? ($_GET['start_date'] ?? '') : ''; ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="end_date" style="font-size: 0.875rem;">To</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $range === 'custom' ? ($_GET['end_date'] ?? '') : ''; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </form>

                    <p style="margin-top: 1rem; color: #6B7280;">
                        <strong>Showing:</strong> <?php echo formatDate($startDate); ?> to <?php echo formatDate($endDate); ?>
                    </p>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon green">💰</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($revenueStats['total_revenue'] ?? 0, $business['currency']); ?></h3>
                        <p>Total Revenue</p>
                        <small>100% yours - No commission!</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">💵</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($revenueStats['collected_revenue'] ?? 0, $business['currency']); ?></h3>
                        <p>Collected Revenue</p>
                        <small>Actually paid</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">📊</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($revenueStats['avg_appointment_value'] ?? 0, $business['currency']); ?></h3>
                        <p>Avg. Appointment Value</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">📅</div>
                    <div class="stat-info">
                        <h3><?php echo $revenueStats['total_appointments'] ?? 0; ?></h3>
                        <p>Total Appointments</p>
                        <small><?php echo $revenueStats['completed_appointments'] ?? 0; ?> completed</small>
                    </div>
                </div>
            </div>

            <!-- Appointment Status Breakdown -->
            <div class="card">
                <div class="card-header">
                    <h2>Appointment Status</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?php echo $revenueStats['completed_appointments'] ?? 0; ?></h3>
                                <p>Completed</p>
                                <small><?php echo $revenueStats['total_appointments'] > 0 ? round(($revenueStats['completed_appointments'] / $revenueStats['total_appointments']) * 100) : 0; ?>%</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?php echo $revenueStats['cancelled_appointments'] ?? 0; ?></h3>
                                <p>Cancelled</p>
                                <small><?php echo $revenueStats['total_appointments'] > 0 ? round(($revenueStats['cancelled_appointments'] / $revenueStats['total_appointments']) * 100) : 0; ?>%</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?php echo $revenueStats['no_show_appointments'] ?? 0; ?></h3>
                                <p>No Shows</p>
                                <small><?php echo $revenueStats['total_appointments'] > 0 ? round(($revenueStats['no_show_appointments'] / $revenueStats['total_appointments']) * 100) : 0; ?>%</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?php echo $newClients; ?></h3>
                                <p>New Clients</p>
                                <small>In this period</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- Top Services -->
                <div class="card">
                    <div class="card-header">
                        <h2>Top Services</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topServices)): ?>
                            <p>No data available</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Bookings</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topServices as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['name']); ?></td>
                                            <td><?php echo $service['bookings']; ?></td>
                                            <td><?php echo formatCurrency($service['revenue'], $business['currency']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Staff -->
                <div class="card">
                    <div class="card-header">
                        <h2>Staff Performance</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topStaff)): ?>
                            <p>No data available</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Appointments</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topStaff as $staff): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                            <td><?php echo $staff['appointments']; ?></td>
                                            <td><?php echo formatCurrency($staff['revenue'], $business['currency']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <!-- Referral Sources -->
                <div class="card">
                    <div class="card-header">
                        <h2>Client Referral Sources</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($referralSources)): ?>
                            <p>No data available</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Clients</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $totalReferrals = array_sum(array_column($referralSources, 'count'));
                                    foreach ($referralSources as $source):
                                    ?>
                                        <tr>
                                            <td><?php echo ucfirst(htmlspecialchars($source['referral_source'])); ?></td>
                                            <td><?php echo $source['count']; ?></td>
                                            <td><?php echo round(($source['count'] / $totalReferrals) * 100); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="card">
                    <div class="card-header">
                        <h2>Payment Methods</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($paymentMethods)): ?>
                            <p>No data available</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Transactions</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paymentMethods as $method): ?>
                                        <tr>
                                            <td><?php echo ucfirst(htmlspecialchars($method['payment_method'])); ?></td>
                                            <td><?php echo $method['count']; ?></td>
                                            <td><?php echo formatCurrency($method['total'], $business['currency']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Zero Commission Banner -->
            <div class="highlight-card" style="margin-top: 2rem;">
                <h2>💰 Zero Commission Savings</h2>
                <p><strong>Total Revenue This Period:</strong> <?php echo formatCurrency($revenueStats['total_revenue'] ?? 0, $business['currency']); ?></p>
                <p><strong>If you were using a typical platform (20% commission):</strong> <?php echo formatCurrency(($revenueStats['total_revenue'] ?? 0) * 0.20, $business['currency']); ?> in fees</p>
                <p><strong>Your Savings:</strong> <?php echo formatCurrency(($revenueStats['total_revenue'] ?? 0) * 0.20, $business['currency']); ?> kept in YOUR pocket!</p>
                <p style="margin-top: 1rem; font-size: 0.875rem; opacity: 0.9;">
                    With <?php echo SITE_NAME; ?>, you keep 100% of your revenue. No marketplace fees. No commission. Ever.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
