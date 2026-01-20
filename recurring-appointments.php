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

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_recurring') {
        $clientId = (int)$_POST['client_id'];
        $staffId = (int)$_POST['staff_id'];
        $serviceId = (int)$_POST['service_id'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'] ?? null;
        $pattern = $_POST['recurrence_pattern'];
        $days = $_POST['recurrence_days'] ?? '';
        $time = $_POST['preferred_time'];
        $notes = $_POST['notes'] ?? '';

        // Get service duration
        $service = $db->fetchOne("SELECT duration FROM services WHERE id = ?", [$serviceId]);
        $duration = $service['duration'] ?? 60;

        $db->execute(
            "INSERT INTO recurring_appointments
            (business_id, client_id, staff_id, service_id, start_date, end_date,
             recurrence_pattern, recurrence_days, preferred_time, duration, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$businessId, $clientId, $staffId, $serviceId, $startDate, $endDate,
             $pattern, $days, $time, $duration, $notes]
        );

        $message = "Recurring appointment created successfully! Appointments will be auto-booked based on this pattern.";
    }

    if ($action === 'toggle_active') {
        $id = (int)$_POST['recurring_id'];
        $db->execute(
            "UPDATE recurring_appointments SET is_active = NOT is_active WHERE id = ? AND business_id = ?",
            [$id, $businessId]
        );
        $message = "Recurring appointment status updated.";
    }

    if ($action === 'delete_recurring') {
        $id = (int)$_POST['recurring_id'];
        $db->execute(
            "DELETE FROM recurring_appointments WHERE id = ? AND business_id = ?",
            [$id, $businessId]
        );
        $message = "Recurring appointment deleted.";
    }
}

// Fetch recurring appointments
$recurringList = $db->fetchAll(
    "SELECT ra.*, c.first_name, c.last_name, c.email, c.phone,
            s.name as service_name, st.name as staff_name, st.color as staff_color
     FROM recurring_appointments ra
     LEFT JOIN clients c ON ra.client_id = c.id
     LEFT JOIN services s ON ra.service_id = s.id
     LEFT JOIN staff st ON ra.staff_id = st.id
     WHERE ra.business_id = ?
     ORDER BY ra.is_active DESC, ra.created_at DESC",
    [$businessId]
);

// Get clients for dropdown
$clients = $db->fetchAll(
    "SELECT * FROM clients WHERE business_id = ? ORDER BY first_name, last_name",
    [$businessId]
);

// Get staff for dropdown
$staffList = $db->fetchAll(
    "SELECT * FROM staff WHERE business_id = ? AND is_active = 1 ORDER BY name",
    [$businessId]
);

// Get services for dropdown
$services = $db->fetchAll(
    "SELECT * FROM services WHERE business_id = ? AND is_active = 1 ORDER BY name",
    [$businessId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Appointments - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h1>Recurring Appointments</h1>
                <button onclick="document.getElementById('createForm').style.display='block'" class="btn btn-primary">
                    + New Recurring Appointment
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Info Banner -->
            <div class="info-banner">
                <strong>🔄 Auto-Booking:</strong> Unlike Square's limited recurring options, our system automatically creates appointments based on your pattern. Perfect for regular clients!
            </div>

            <!-- Create Form (Hidden by default) -->
            <div id="createForm" class="card" style="display: none;">
                <div class="card-header">
                    <h2>Create Recurring Appointment</h2>
                    <button onclick="document.getElementById('createForm').style.display='none'" class="btn btn-sm btn-outline">Cancel</button>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="create_recurring">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="client_id">Client *</label>
                                <select id="client_id" name="client_id" required>
                                    <option value="">Select client...</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>">
                                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                            (<?php echo htmlspecialchars($client['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="service_id">Service *</label>
                                <select id="service_id" name="service_id" required>
                                    <option value="">Select service...</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['id']; ?>">
                                            <?php echo htmlspecialchars($service['name']); ?>
                                            (<?php echo $service['duration']; ?> min - <?php echo formatCurrency($service['price'], $business['currency']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="staff_id">Staff Member *</label>
                                <select id="staff_id" name="staff_id" required>
                                    <option value="">Select staff...</option>
                                    <?php foreach ($staffList as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>">
                                            <?php echo htmlspecialchars($staff['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="preferred_time">Preferred Time *</label>
                                <input type="time" id="preferred_time" name="preferred_time" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="recurrence_pattern">Recurrence Pattern *</label>
                            <select id="recurrence_pattern" name="recurrence_pattern" onchange="toggleDaysSelect()" required>
                                <option value="weekly">Weekly (Every week on selected days)</option>
                                <option value="biweekly">Bi-weekly (Every 2 weeks on selected days)</option>
                                <option value="monthly">Monthly (Same day each month)</option>
                                <option value="daily">Daily (Every day)</option>
                            </select>
                        </div>

                        <div class="form-group" id="daysGroup">
                            <label>Days of Week *</label>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="recurrence_days[]" value="0"> Sunday
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="recurrence_days[]" value="1"> Monday
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="recurrence_days[]" value="2"> Tuesday
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="recurrence_days[]" value="3"> Wednesday
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="recurrence_days[]" value="4"> Thursday
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="recurrence_days[]" value="5"> Friday
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="recurrence_days[]" value="6"> Saturday
                                </label>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" required>
                            </div>

                            <div class="form-group">
                                <label for="end_date">End Date (Optional)</label>
                                <input type="date" id="end_date" name="end_date">
                                <small>Leave empty for no end date</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Create Recurring Appointment</button>
                    </form>
                </div>
            </div>

            <!-- Recurring Appointments List -->
            <div class="card">
                <div class="card-header">
                    <h2>Active Recurring Appointments</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($recurringList)): ?>
                        <p class="text-muted">No recurring appointments set up yet. Create one to automatically book regular appointments for your clients!</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Service</th>
                                        <th>Staff</th>
                                        <th>Pattern</th>
                                        <th>Time</th>
                                        <th>Start - End</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recurringList as $recurring): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($recurring['first_name'] . ' ' . $recurring['last_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($recurring['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($recurring['service_name']); ?></td>
                                            <td>
                                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?php echo $recurring['staff_color']; ?>; margin-right: 5px;"></span>
                                                <?php echo htmlspecialchars($recurring['staff_name']); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo ucfirst($recurring['recurrence_pattern']); ?></strong>
                                                <?php if ($recurring['recurrence_days']): ?>
                                                    <br><small class="text-muted">
                                                        <?php
                                                        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                                                        $selectedDays = explode(',', $recurring['recurrence_days']);
                                                        echo implode(', ', array_map(fn($d) => $days[$d] ?? $d, $selectedDays));
                                                        ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatTime($recurring['preferred_time']); ?></td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($recurring['start_date'])); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $recurring['end_date'] ? date('M j, Y', strtotime($recurring['end_date'])) : 'No end'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($recurring['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Paused</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="recurring_id" value="<?php echo $recurring['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline">
                                                        <?php echo $recurring['is_active'] ? 'Pause' : 'Resume'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this recurring appointment?');">
                                                    <input type="hidden" name="action" value="delete_recurring">
                                                    <input type="hidden" name="recurring_id" value="<?php echo $recurring['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Feature Comparison -->
            <div class="card">
                <div class="card-header">
                    <h2>Why Our Recurring System Beats Square</h2>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div>
                            <h4>❌ Square Recurring</h4>
                            <ul>
                                <li>Basic weekly only</li>
                                <li>Limited flexibility</li>
                                <li>Can't customize days easily</li>
                                <li>No biweekly support</li>
                            </ul>
                        </div>
                        <div>
                            <h4>✅ <?php echo SITE_NAME; ?> Recurring</h4>
                            <ul>
                                <li>Daily, weekly, biweekly, monthly</li>
                                <li>Select specific days of week</li>
                                <li>Flexible start and end dates</li>
                                <li>Pause/resume anytime</li>
                                <li>Perfect for regular clients</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleDaysSelect() {
            const pattern = document.getElementById('recurrence_pattern').value;
            const daysGroup = document.getElementById('daysGroup');

            if (pattern === 'daily' || pattern === 'monthly') {
                daysGroup.style.display = 'none';
                // Uncheck all days
                document.querySelectorAll('input[name="recurrence_days[]"]').forEach(cb => cb.checked = false);
            } else {
                daysGroup.style.display = 'block';
            }
        }

        // Initialize
        toggleDaysSelect();

        // Set min date to today
        document.getElementById('start_date').min = new Date().toISOString().split('T')[0];
        document.getElementById('end_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
