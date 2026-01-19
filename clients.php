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
$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'edit') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $referralSource = sanitize($_POST['referral_source'] ?? '');

        if (empty($firstName) || empty($lastName)) {
            $error = 'First name and last name are required';
        } elseif (empty($email) && empty($phone)) {
            $error = 'Please provide at least an email or phone number';
        } else {
            $data = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'notes' => $notes,
                'referral_source' => $referralSource
            ];

            if ($postAction === 'edit' && $clientId) {
                $updated = $db->update('clients', $data, 'id = :id AND business_id = :business_id', [
                    'id' => $clientId,
                    'business_id' => $businessId
                ]);

                if ($updated) {
                    $success = 'Client updated successfully';
                    $action = 'view';
                } else {
                    $error = 'Failed to update client';
                }
            } else {
                $data['business_id'] = $businessId;
                $data['first_visit'] = date('Y-m-d');
                $newClientId = $db->insert('clients', $data);

                if ($newClientId) {
                    $success = 'Client added successfully';
                    $clientId = $newClientId;
                    $action = 'view';
                } else {
                    $error = 'Failed to add client';
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $deleteId = (int)($_POST['client_id'] ?? 0);

        if ($deleteId) {
            // Check if client has appointments
            $hasAppointments = $db->fetchOne(
                "SELECT COUNT(*) as count FROM appointments WHERE client_id = ?",
                [$deleteId]
            );

            if ($hasAppointments['count'] > 0) {
                $error = 'Cannot delete client with existing appointments';
            } else {
                $deleted = $db->delete('clients', 'id = :id AND business_id = :business_id', [
                    'id' => $deleteId,
                    'business_id' => $businessId
                ]);

                if ($deleted) {
                    $success = 'Client deleted successfully';
                    $action = 'list';
                } else {
                    $error = 'Failed to delete client';
                }
            }
        }
    }
}

// Get client for view/edit
$client = null;
if ($clientId && ($action === 'view' || $action === 'edit')) {
    $client = $db->fetchOne(
        "SELECT * FROM clients WHERE id = ? AND business_id = ?",
        [$clientId, $businessId]
    );

    if (!$client) {
        $error = 'Client not found';
        $action = 'list';
    }
}

// Get client appointments for view
$clientAppointments = [];
if ($action === 'view' && $client) {
    $clientAppointments = $db->fetchAll(
        "SELECT a.*, s.name as service_name, st.name as staff_name
         FROM appointments a
         LEFT JOIN services s ON a.service_id = s.id
         LEFT JOIN staff st ON a.staff_id = st.id
         WHERE a.client_id = ?
         ORDER BY a.appointment_date DESC, a.start_time DESC
         LIMIT 20",
        [$clientId]
    );
}

// For list view
$clients = [];
if ($action === 'list') {
    $search = $_GET['search'] ?? '';

    if ($search) {
        $searchTerm = "%$search%";
        $clients = $db->fetchAll(
            "SELECT * FROM clients
             WHERE business_id = ?
             AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)
             ORDER BY first_name, last_name
             LIMIT 100",
            [$businessId, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
        );
    } else {
        $clients = $db->fetchAll(
            "SELECT * FROM clients WHERE business_id = ? ORDER BY created_at DESC LIMIT 100",
            [$businessId]
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - <?php echo SITE_NAME; ?></title>
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
                    <h1>Clients</h1>
                    <a href="?action=new" class="btn btn-primary">+ Add Client</a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Search Clients</h2>
                        <form method="GET" action="" style="display: flex; gap: 0.5rem;">
                            <input type="text" name="search" placeholder="Search by name, email, or phone"
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   style="min-width: 300px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <?php if (!empty($_GET['search'])): ?>
                                <a href="clients.php" class="btn btn-outline">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (empty($clients)): ?>
                            <div class="empty-state">
                                <p><?php echo !empty($_GET['search']) ? 'No clients found' : 'No clients yet'; ?></p>
                                <a href="?action=new" class="btn btn-primary">Add Your First Client</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>First Visit</th>
                                            <th>Total Visits</th>
                                            <th>Total Spent</th>
                                            <th>Referral Source</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients as $c): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($c['email']): ?>
                                                        <div><a href="mailto:<?php echo htmlspecialchars($c['email']); ?>"><?php echo htmlspecialchars($c['email']); ?></a></div>
                                                    <?php endif; ?>
                                                    <?php if ($c['phone']): ?>
                                                        <div><a href="tel:<?php echo htmlspecialchars($c['phone']); ?>"><?php echo htmlspecialchars($c['phone']); ?></a></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $c['first_visit'] ? formatDate($c['first_visit']) : '-'; ?></td>
                                                <td><?php echo $c['total_visits']; ?></td>
                                                <td><?php echo formatCurrency($c['total_spent'], $business['currency']); ?></td>
                                                <td><?php echo htmlspecialchars($c['referral_source'] ?: '-'); ?></td>
                                                <td class="table-actions">
                                                    <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-card">
                    <h3>✅ Accurate Client Tracking</h3>
                    <p>Unlike Fresha's broken client recognition system, our smart tracking correctly identifies returning clients by email AND phone number. No more false "new client" marketplace fees!</p>
                </div>

            <?php elseif ($action === 'view' && $client): ?>
                <!-- View Client -->
                <div class="page-header">
                    <h1><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h1>
                    <div>
                        <a href="?action=edit&id=<?php echo $client['id']; ?>" class="btn btn-outline">Edit</a>
                        <a href="appointments.php?action=new&client_id=<?php echo $client['id']; ?>" class="btn btn-primary">+ New Appointment</a>
                        <a href="?action=list" class="btn btn-outline">Back to List</a>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">📅</div>
                        <div class="stat-info">
                            <h3><?php echo $client['total_visits']; ?></h3>
                            <p>Total Visits</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">💰</div>
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($client['total_spent'], $business['currency']); ?></h3>
                            <p>Total Spent</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">📆</div>
                        <div class="stat-info">
                            <h3><?php echo $client['first_visit'] ? formatDate($client['first_visit']) : '-'; ?></h3>
                            <p>First Visit</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">🔄</div>
                        <div class="stat-info">
                            <h3><?php echo $client['last_visit'] ? formatDate($client['last_visit']) : 'Never'; ?></h3>
                            <p>Last Visit</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Client Information</h2>
                    </div>
                    <div class="card-body">
                        <table style="width: 100%; max-width: 600px;">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"><?php echo htmlspecialchars($client['email'] ?: '-'); ?></a></td>
                            </tr>
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td><a href="tel:<?php echo htmlspecialchars($client['phone']); ?>"><?php echo htmlspecialchars($client['phone'] ?: '-'); ?></a></td>
                            </tr>
                            <tr>
                                <td><strong>Referral Source:</strong></td>
                                <td><?php echo htmlspecialchars($client['referral_source'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Client Since:</strong></td>
                                <td><?php echo formatDate($client['created_at']); ?></td>
                            </tr>
                        </table>

                        <?php if ($client['notes']): ?>
                            <div style="margin-top: 1.5rem;">
                                <h3>Notes</h3>
                                <p><?php echo nl2br(htmlspecialchars($client['notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Appointment History</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($clientAppointments)): ?>
                            <p>No appointments yet</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Service</th>
                                            <th>Staff</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientAppointments as $apt): ?>
                                            <tr>
                                                <td>
                                                    <?php echo formatDate($apt['appointment_date']); ?><br>
                                                    <small><?php echo formatTime($apt['start_time']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                                <td><?php echo htmlspecialchars($apt['staff_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $apt['status']; ?>">
                                                        <?php echo ucfirst($apt['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatCurrency($apt['paid_amount'], $business['currency']); ?></td>
                                                <td>
                                                    <a href="appointments.php?action=view&id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Actions</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this client? This cannot be undone.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                            <button type="submit" class="btn btn-danger">Delete Client</button>
                        </form>
                    </div>
                </div>

            <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <!-- Create/Edit Form -->
                <div class="page-header">
                    <h1><?php echo $action === 'new' ? 'Add New Client' : 'Edit Client'; ?></h1>
                    <a href="?action=list" class="btn btn-outline">Cancel</a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" class="form">
                            <input type="hidden" name="action" value="<?php echo $action === 'new' ? 'create' : 'edit'; ?>">

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" required
                                           value="<?php echo htmlspecialchars($client['first_name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" required
                                           value="<?php echo htmlspecialchars($client['last_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required
                                           value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                                    <small>Required for client recognition</small>
                                </div>

                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="tel" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                                    <small>Also used for client recognition</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="referral_source">How did they find you?</label>
                                <select id="referral_source" name="referral_source">
                                    <option value="">Select source</option>
                                    <option value="google" <?php echo (isset($client) && $client['referral_source'] == 'google') ? 'selected' : ''; ?>>Google Search</option>
                                    <option value="instagram" <?php echo (isset($client) && $client['referral_source'] == 'instagram') ? 'selected' : ''; ?>>Instagram</option>
                                    <option value="facebook" <?php echo (isset($client) && $client['referral_source'] == 'facebook') ? 'selected' : ''; ?>>Facebook</option>
                                    <option value="referral" <?php echo (isset($client) && $client['referral_source'] == 'referral') ? 'selected' : ''; ?>>Friend/Family Referral</option>
                                    <option value="walk_by" <?php echo (isset($client) && $client['referral_source'] == 'walk_by') ? 'selected' : ''; ?>>Walked By</option>
                                    <option value="returning" <?php echo (isset($client) && $client['referral_source'] == 'returning') ? 'selected' : ''; ?>>Returning Client</option>
                                    <option value="other" <?php echo (isset($client) && $client['referral_source'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <small>Track where clients come from (NO COMMISSION CHARGED!)</small>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                                <small>Preferences, allergies, or any other important information</small>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $action === 'new' ? 'Add Client' : 'Update Client'; ?>
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
