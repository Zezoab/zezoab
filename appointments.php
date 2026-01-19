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
$action = $_GET['action'] ?? 'list';
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'edit') {
        // Get form data
        $clientId = (int)($_POST['client_id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $appointmentDate = sanitize($_POST['appointment_date'] ?? '');
        $startTime = sanitize($_POST['start_time'] ?? '');
        $status = sanitize($_POST['status'] ?? 'pending');
        $notes = sanitize($_POST['notes'] ?? '');
        $internalNotes = sanitize($_POST['internal_notes'] ?? '');
        $paidAmount = (float)($_POST['paid_amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'none');

        // Validate
        if (!$clientId || !$staffId || !$serviceId || !$appointmentDate || !$startTime) {
            $error = 'Please fill in all required fields';
        } else {
            // Get service to calculate end time and price
            $service = $db->fetchOne("SELECT * FROM services WHERE id = ?", [$serviceId]);

            if (!$service) {
                $error = 'Invalid service selected';
            } else {
                $endTime = date('H:i:s', strtotime($startTime) + ($service['duration'] * 60));

                // Check availability (skip for current appointment when editing)
                $excludeId = ($postAction === 'edit' && $appointmentId) ? $appointmentId : null;
                if (!isTimeAvailable($db, $staffId, $appointmentDate, $startTime, $endTime, $excludeId)) {
                    $error = 'This time slot is not available';
                } else {
                    $paymentStatus = 'unpaid';
                    if ($paidAmount >= $service['price']) {
                        $paymentStatus = 'paid';
                    } elseif ($paidAmount > 0) {
                        $paymentStatus = 'partial';
                    }

                    $data = [
                        'client_id' => $clientId,
                        'staff_id' => $staffId,
                        'service_id' => $serviceId,
                        'appointment_date' => $appointmentDate,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'status' => $status,
                        'notes' => $notes,
                        'internal_notes' => $internalNotes,
                        'price' => $service['price'],
                        'paid_amount' => $paidAmount,
                        'payment_method' => $paymentMethod,
                        'payment_status' => $paymentStatus
                    ];

                    if ($postAction === 'edit' && $appointmentId) {
                        $updated = $db->update('appointments', $data, 'id = :id AND business_id = :business_id', [
                            'id' => $appointmentId,
                            'business_id' => $businessId
                        ]);

                        if ($updated) {
                            $success = 'Appointment updated successfully';
                            $action = 'view';
                        } else {
                            $error = 'Failed to update appointment';
                        }
                    } else {
                        $data['business_id'] = $businessId;
                        $newId = $db->insert('appointments', $data);

                        if ($newId) {
                            // Create notification
                            createNotification($db, $businessId, 'new_booking', 'New Appointment', 'New appointment created', $newId);

                            // Update client stats if completed
                            if ($status === 'completed') {
                                updateClientStats($db, $clientId, $paidAmount);
                            }

                            $success = 'Appointment created successfully';
                            $appointmentId = $newId;
                            $action = 'view';
                        } else {
                            $error = 'Failed to create appointment';
                        }
                    }
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $deleteId = (int)($_POST['appointment_id'] ?? 0);

        if ($deleteId) {
            $deleted = $db->delete('appointments', 'id = :id AND business_id = :business_id', [
                'id' => $deleteId,
                'business_id' => $businessId
            ]);

            if ($deleted) {
                $success = 'Appointment deleted successfully';
                $action = 'list';
            } else {
                $error = 'Failed to delete appointment';
            }
        }
    } elseif ($postAction === 'update_status') {
        $updateId = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = sanitize($_POST['new_status'] ?? '');

        if ($updateId && $newStatus) {
            $updateData = ['status' => $newStatus];

            // If marking as completed, update client stats
            if ($newStatus === 'completed') {
                $apt = $db->fetchOne("SELECT * FROM appointments WHERE id = ?", [$updateId]);
                if ($apt) {
                    updateClientStats($db, $apt['client_id'], $apt['paid_amount']);
                }
            }

            $updated = $db->update('appointments', $updateData, 'id = :id AND business_id = :business_id', [
                'id' => $updateId,
                'business_id' => $businessId
            ]);

            if ($updated) {
                $success = 'Appointment status updated';
            } else {
                $error = 'Failed to update status';
            }
        }
    }
}

// Get appointment for view/edit
$appointment = null;
if ($appointmentId && ($action === 'view' || $action === 'edit')) {
    $appointment = $db->fetchOne(
        "SELECT a.*, c.first_name, c.last_name, c.email, c.phone,
                s.name as service_name, s.duration, st.name as staff_name
         FROM appointments a
         LEFT JOIN clients c ON a.client_id = c.id
         LEFT JOIN services s ON a.service_id = s.id
         LEFT JOIN staff st ON a.staff_id = st.id
         WHERE a.id = ? AND a.business_id = ?",
        [$appointmentId, $businessId]
    );

    if (!$appointment) {
        $error = 'Appointment not found';
        $action = 'list';
    }
}

// Get data for forms
$clients = $db->fetchAll("SELECT * FROM clients WHERE business_id = ? ORDER BY first_name, last_name", [$businessId]);
$staff = $db->fetchAll("SELECT * FROM staff WHERE business_id = ? AND is_active = 1 ORDER BY name", [$businessId]);
$services = $db->fetchAll("SELECT * FROM services WHERE business_id = ? AND is_active = 1 ORDER BY name", [$businessId]);

// For list view, get appointments
$appointments = [];
if ($action === 'list') {
    $filter = $_GET['filter'] ?? 'upcoming';
    $today = date('Y-m-d');

    switch ($filter) {
        case 'today':
            $filterSql = "AND a.appointment_date = '$today'";
            break;
        case 'upcoming':
            $filterSql = "AND a.appointment_date >= '$today' AND a.status NOT IN ('completed', 'cancelled')";
            break;
        case 'past':
            $filterSql = "AND (a.appointment_date < '$today' OR a.status = 'completed')";
            break;
        case 'pending':
            $filterSql = "AND a.status = 'pending'";
            break;
        default:
            $filterSql = "AND a.appointment_date >= '$today'";
    }

    $appointments = $db->fetchAll(
        "SELECT a.*, c.first_name, c.last_name, c.email, c.phone,
                s.name as service_name, st.name as staff_name
         FROM appointments a
         LEFT JOIN clients c ON a.client_id = c.id
         LEFT JOIN services s ON a.service_id = s.id
         LEFT JOIN staff st ON a.staff_id = st.id
         WHERE a.business_id = ? $filterSql
         ORDER BY a.appointment_date DESC, a.start_time DESC
         LIMIT 100",
        [$businessId]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- List View -->
                <div class="page-header">
                    <h1>Appointments</h1>
                    <a href="?action=new" class="btn btn-primary">+ New Appointment</a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Filter</h2>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="?filter=today" class="btn btn-sm <?php echo ($_GET['filter'] ?? '') === 'today' ? 'btn-primary' : 'btn-outline'; ?>">Today</a>
                            <a href="?filter=upcoming" class="btn btn-sm <?php echo ($_GET['filter'] ?? 'upcoming') === 'upcoming' ? 'btn-primary' : 'btn-outline'; ?>">Upcoming</a>
                            <a href="?filter=pending" class="btn btn-sm <?php echo ($_GET['filter'] ?? '') === 'pending' ? 'btn-primary' : 'btn-outline'; ?>">Pending</a>
                            <a href="?filter=past" class="btn btn-sm <?php echo ($_GET['filter'] ?? '') === 'past' ? 'btn-primary' : 'btn-outline'; ?>">Past</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="empty-state">
                                <p>No appointments found</p>
                                <a href="?action=new" class="btn btn-primary">Create First Appointment</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Client</th>
                                            <th>Service</th>
                                            <th>Staff</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $apt): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo formatDate($apt['appointment_date']); ?></strong><br>
                                                    <?php echo formatTime($apt['start_time']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?><br>
                                                    <small><?php echo htmlspecialchars($apt['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                                <td><?php echo htmlspecialchars($apt['staff_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $apt['status']; ?>">
                                                        <?php echo ucfirst($apt['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo formatCurrency($apt['paid_amount'], $business['currency']); ?><br>
                                                    <small><?php echo ucfirst($apt['payment_status']); ?></small>
                                                </td>
                                                <td class="table-actions">
                                                    <a href="?action=view&id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($action === 'view' && $appointment): ?>
                <!-- View Appointment -->
                <div class="page-header">
                    <h1>Appointment Details</h1>
                    <div>
                        <a href="?action=edit&id=<?php echo $appointment['id']; ?>" class="btn btn-outline">Edit</a>
                        <a href="?action=list" class="btn btn-outline">Back to List</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Appointment #<?php echo $appointment['id']; ?></h2>
                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                            <?php echo ucfirst($appointment['status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <div>
                                <h3>Appointment Information</h3>
                                <table style="width: 100%;">
                                    <tr>
                                        <td><strong>Date:</strong></td>
                                        <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Time:</strong></td>
                                        <td><?php echo formatTime($appointment['start_time']); ?> - <?php echo formatTime($appointment['end_time']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Duration:</strong></td>
                                        <td><?php echo $appointment['duration']; ?> minutes</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service:</strong></td>
                                        <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Staff:</strong></td>
                                        <td><?php echo htmlspecialchars($appointment['staff_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Price:</strong></td>
                                        <td><?php echo formatCurrency($appointment['price'], $business['currency']); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <div>
                                <h3>Client Information</h3>
                                <table style="width: 100%;">
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><a href="mailto:<?php echo htmlspecialchars($appointment['email']); ?>"><?php echo htmlspecialchars($appointment['email']); ?></a></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><a href="tel:<?php echo htmlspecialchars($appointment['phone']); ?>"><?php echo htmlspecialchars($appointment['phone']); ?></a></td>
                                    </tr>
                                </table>

                                <h3 style="margin-top: 1.5rem;">Payment</h3>
                                <table style="width: 100%;">
                                    <tr>
                                        <td><strong>Paid Amount:</strong></td>
                                        <td><?php echo formatCurrency($appointment['paid_amount'], $business['currency']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment Status:</strong></td>
                                        <td><span class="badge badge-<?php echo $appointment['payment_status']; ?>"><?php echo ucfirst($appointment['payment_status']); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment Method:</strong></td>
                                        <td><?php echo ucfirst($appointment['payment_method']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <?php if ($appointment['notes']): ?>
                            <div style="margin-top: 1.5rem;">
                                <h3>Client Notes</h3>
                                <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($appointment['internal_notes']): ?>
                            <div style="margin-top: 1.5rem;">
                                <h3>Internal Notes</h3>
                                <p><?php echo nl2br(htmlspecialchars($appointment['internal_notes'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                            <?php if ($appointment['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <input type="hidden" name="new_status" value="confirmed">
                                    <button type="submit" class="btn btn-primary">Confirm Appointment</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($appointment['status'] === 'confirmed'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <input type="hidden" name="new_status" value="completed">
                                    <button type="submit" class="btn btn-secondary">Mark as Completed</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this appointment?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                <button type="submit" class="btn btn-danger">Delete Appointment</button>
                            </form>
                        </div>
                    </div>
                </div>

            <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <!-- Create/Edit Form -->
                <div class="page-header">
                    <h1><?php echo $action === 'new' ? 'New Appointment' : 'Edit Appointment'; ?></h1>
                    <a href="?action=list" class="btn btn-outline">Cancel</a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" class="form">
                            <input type="hidden" name="action" value="<?php echo $action === 'new' ? 'create' : 'edit'; ?>">

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="client_id">Client *</label>
                                    <select id="client_id" name="client_id" required>
                                        <option value="">Select client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>" <?php echo (isset($appointment) && $appointment['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Don't see your client? <a href="clients.php?action=new" target="_blank">Add new client</a></small>
                                </div>

                                <div class="form-group">
                                    <label for="service_id">Service *</label>
                                    <select id="service_id" name="service_id" required>
                                        <option value="">Select service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service['id']; ?>" <?php echo (isset($appointment) && $appointment['service_id'] == $service['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($service['name']); ?> - <?php echo formatCurrency($service['price'], $business['currency']); ?> (<?php echo $service['duration']; ?> min)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="staff_id">Staff Member *</label>
                                    <select id="staff_id" name="staff_id" required>
                                        <option value="">Select staff</option>
                                        <?php foreach ($staff as $member): ?>
                                            <option value="<?php echo $member['id']; ?>" <?php echo (isset($appointment) && $appointment['staff_id'] == $member['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($member['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="status">Status *</label>
                                    <select id="status" name="status" required>
                                        <option value="pending" <?php echo (isset($appointment) && $appointment['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo (isset($appointment) && $appointment['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="completed" <?php echo (isset($appointment) && $appointment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo (isset($appointment) && $appointment['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="no_show" <?php echo (isset($appointment) && $appointment['status'] == 'no_show') ? 'selected' : ''; ?>>No Show</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="appointment_date">Date *</label>
                                    <input type="date" id="appointment_date" name="appointment_date" required
                                           value="<?php echo $appointment['appointment_date'] ?? ''; ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="start_time">Start Time *</label>
                                    <input type="time" id="start_time" name="start_time" required
                                           value="<?php echo isset($appointment) ? date('H:i', strtotime($appointment['start_time'])) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="paid_amount">Paid Amount</label>
                                    <input type="number" id="paid_amount" name="paid_amount" min="0" step="0.01"
                                           value="<?php echo $appointment['paid_amount'] ?? '0'; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="payment_method">Payment Method</label>
                                    <select id="payment_method" name="payment_method">
                                        <option value="none" <?php echo (isset($appointment) && $appointment['payment_method'] == 'none') ? 'selected' : ''; ?>>None/Unpaid</option>
                                        <option value="cash" <?php echo (isset($appointment) && $appointment['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                        <option value="card" <?php echo (isset($appointment) && $appointment['payment_method'] == 'card') ? 'selected' : ''; ?>>Card</option>
                                        <option value="online" <?php echo (isset($appointment) && $appointment['payment_method'] == 'online') ? 'selected' : ''; ?>>Online</option>
                                        <option value="deposit" <?php echo (isset($appointment) && $appointment['payment_method'] == 'deposit') ? 'selected' : ''; ?>>Deposit</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Client Notes</label>
                                <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                                <small>Notes from the client (visible to client)</small>
                            </div>

                            <div class="form-group">
                                <label for="internal_notes">Internal Notes</label>
                                <textarea id="internal_notes" name="internal_notes" rows="3"><?php echo htmlspecialchars($appointment['internal_notes'] ?? ''); ?></textarea>
                                <small>Private notes only visible to you</small>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $action === 'new' ? 'Create Appointment' : 'Update Appointment'; ?>
                                </button>
                                <a href="?action=list" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
