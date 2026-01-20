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

// Handle staff creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        $color = sanitize($_POST['color'] ?? '#3B82F6');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            $error = 'Please enter staff member name';
        } else {
            $data = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'bio' => $bio,
                'color' => $color,
                'is_active' => $isActive
            ];

            if ($action === 'edit' && $staffId) {
                $updated = $db->update('staff', $data, 'id = :id AND business_id = :business_id', [
                    'id' => $staffId,
                    'business_id' => $businessId
                ]);

                if ($updated) {
                    $success = 'Staff member updated successfully';
                } else {
                    $error = 'Failed to update staff member';
                }
            } else {
                $data['business_id'] = $businessId;
                $newStaffId = $db->insert('staff', $data);

                if ($newStaffId) {
                    $success = 'Staff member added successfully';
                } else {
                    $error = 'Failed to add staff member';
                }
            }
        }
    } elseif ($action === 'delete') {
        $staffId = (int)($_POST['staff_id'] ?? 0);

        if ($staffId) {
            $deleted = $db->delete('staff', 'id = :id AND business_id = :business_id', [
                'id' => $staffId,
                'business_id' => $businessId
            ]);

            if ($deleted) {
                $success = 'Staff member deleted successfully';
            } else {
                $error = 'Failed to delete staff member';
            }
        }
    }
}

// Get all staff
$staffList = $db->fetchAll(
    "SELECT s.*, COUNT(DISTINCT ss.service_id) as service_count
     FROM staff s
     LEFT JOIN staff_services ss ON s.id = ss.staff_id
     WHERE s.business_id = ?
     GROUP BY s.id
     ORDER BY s.name",
    [$businessId]
);

// Get staff member for editing
$editStaff = null;
if (isset($_GET['edit'])) {
    $editStaffId = (int)$_GET['edit'];
    $editStaff = $db->fetchOne(
        "SELECT * FROM staff WHERE id = ? AND business_id = ?",
        [$editStaffId, $businessId]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h1>Staff Management</h1>
                <button onclick="showAddStaffForm()" class="btn btn-primary">+ Add Staff Member</button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Add/Edit Staff Form -->
            <div id="staffFormCard" class="card" style="display: <?php echo $editStaff ? 'block' : 'none'; ?>">
                <div class="card-header">
                    <h2><?php echo $editStaff ? 'Edit Staff Member' : 'Add New Staff Member'; ?></h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="<?php echo $editStaff ? 'edit' : 'add'; ?>">
                        <?php if ($editStaff): ?>
                            <input type="hidden" name="staff_id" value="<?php echo $editStaff['id']; ?>">
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($editStaff['name'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="role">Role/Title</label>
                                <input type="text" id="role" name="role"
                                       value="<?php echo htmlspecialchars($editStaff['role'] ?? ''); ?>"
                                       placeholder="e.g., Senior Stylist, Barber">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email"
                                       value="<?php echo htmlspecialchars($editStaff['email'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($editStaff['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio/Description</label>
                            <textarea id="bio" name="bio" rows="3"><?php echo htmlspecialchars($editStaff['bio'] ?? ''); ?></textarea>
                            <small>This will be displayed on the booking page</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="color">Calendar Color</label>
                                <input type="color" id="color" name="color"
                                       value="<?php echo htmlspecialchars($editStaff['color'] ?? '#3B82F6'); ?>">
                                <small>Color for this staff member in the calendar</small>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_active" <?php echo (!$editStaff || $editStaff['is_active']) ? 'checked' : ''; ?>>
                                    Active (visible on booking page)
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editStaff ? 'Update Staff Member' : 'Add Staff Member'; ?>
                            </button>
                            <button type="button" onclick="hideStaffForm()" class="btn btn-outline">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Staff List -->
            <div class="card">
                <div class="card-header">
                    <h2>All Staff Members</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($staffList)): ?>
                        <div class="empty-state">
                            <p>No staff members added yet</p>
                            <button onclick="showAddStaffForm()" class="btn btn-primary">Add Your First Staff Member</button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Contact</th>
                                        <th>Services</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staffList as $staff): ?>
                                        <tr>
                                            <td>
                                                <div class="staff-name-cell">
                                                    <span class="color-indicator" style="background-color: <?php echo $staff['color']; ?>"></span>
                                                    <strong><?php echo htmlspecialchars($staff['name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($staff['role'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($staff['email']): ?>
                                                    <div><?php echo htmlspecialchars($staff['email']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($staff['phone']): ?>
                                                    <div><?php echo htmlspecialchars($staff['phone']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!$staff['email'] && !$staff['phone']): ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $staff['service_count']; ?> services</td>
                                            <td>
                                                <span class="badge badge-<?php echo $staff['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <a href="staff-schedule.php?staff_id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-primary">📅 Schedule</a>
                                                <a href="?edit=<?php echo $staff['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
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
        </div>
    </main>

    <script>
        function showAddStaffForm() {
            document.getElementById('staffFormCard').style.display = 'block';
            document.getElementById('staffFormCard').scrollIntoView({ behavior: 'smooth' });
        }

        function hideStaffForm() {
            document.getElementById('staffFormCard').style.display = 'none';
            window.location.href = 'staff.php';
        }
    </script>
</body>
</html>
