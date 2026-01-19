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

// Handle service creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $category = sanitize($_POST['category'] ?? '');
        $bufferTime = (int)($_POST['buffer_time'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || $duration <= 0 || $price < 0) {
            $error = 'Please fill in all required fields with valid values';
        } else {
            $data = [
                'name' => $name,
                'description' => $description,
                'duration' => $duration,
                'price' => $price,
                'category' => $category,
                'buffer_time' => $bufferTime,
                'is_active' => $isActive
            ];

            if ($action === 'edit' && $serviceId) {
                $updated = $db->update('services', $data, 'id = :id AND business_id = :business_id', [
                    'id' => $serviceId,
                    'business_id' => $businessId
                ]);

                if ($updated) {
                    $success = 'Service updated successfully';
                } else {
                    $error = 'Failed to update service';
                }
            } else {
                $data['business_id'] = $businessId;
                $newServiceId = $db->insert('services', $data);

                if ($newServiceId) {
                    $success = 'Service added successfully';
                } else {
                    $error = 'Failed to add service';
                }
            }
        }
    } elseif ($action === 'delete') {
        $serviceId = (int)($_POST['service_id'] ?? 0);

        if ($serviceId) {
            $deleted = $db->delete('services', 'id = :id AND business_id = :business_id', [
                'id' => $serviceId,
                'business_id' => $businessId
            ]);

            if ($deleted) {
                $success = 'Service deleted successfully';
            } else {
                $error = 'Failed to delete service';
            }
        }
    }
}

// Get all services grouped by category
$services = $db->fetchAll(
    "SELECT * FROM services WHERE business_id = ? ORDER BY category, name",
    [$businessId]
);

// Get staff member for editing
$editService = null;
if (isset($_GET['edit'])) {
    $editServiceId = (int)$_GET['edit'];
    $editService = $db->fetchOne(
        "SELECT * FROM services WHERE id = ? AND business_id = ?",
        [$editServiceId, $businessId]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h1>Services Management</h1>
                <button onclick="showAddServiceForm()" class="btn btn-primary">+ Add Service</button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Add/Edit Service Form -->
            <div id="serviceFormCard" class="card" style="display: <?php echo $editService ? 'block' : 'none'; ?>">
                <div class="card-header">
                    <h2><?php echo $editService ? 'Edit Service' : 'Add New Service'; ?></h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="<?php echo $editService ? 'edit' : 'add'; ?>">
                        <?php if ($editService): ?>
                            <input type="hidden" name="service_id" value="<?php echo $editService['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Service Name *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($editService['name'] ?? ''); ?>"
                                   placeholder="e.g., Women's Haircut">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editService['description'] ?? ''); ?></textarea>
                            <small>Describe what's included in this service</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration">Duration (minutes) *</label>
                                <input type="number" id="duration" name="duration" required min="5" step="5"
                                       value="<?php echo htmlspecialchars($editService['duration'] ?? '30'); ?>">
                            </div>

                            <div class="form-group">
                                <label for="price">Price (<?php echo $business['currency']; ?>) *</label>
                                <input type="number" id="price" name="price" required min="0" step="0.01"
                                       value="<?php echo htmlspecialchars($editService['price'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category"
                                       value="<?php echo htmlspecialchars($editService['category'] ?? ''); ?>"
                                       placeholder="e.g., Hair, Nails, Spa">
                                <small>Group similar services together</small>
                            </div>

                            <div class="form-group">
                                <label for="buffer_time">Buffer Time (minutes)</label>
                                <input type="number" id="buffer_time" name="buffer_time" min="0" step="5"
                                       value="<?php echo htmlspecialchars($editService['buffer_time'] ?? '0'); ?>">
                                <small>Extra time after service (for cleanup, prep)</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_active" <?php echo (!$editService || $editService['is_active']) ? 'checked' : ''; ?>>
                                Active (visible on booking page)
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editService ? 'Update Service' : 'Add Service'; ?>
                            </button>
                            <button type="button" onclick="hideServiceForm()" class="btn btn-outline">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Services List -->
            <div class="card">
                <div class="card-header">
                    <h2>All Services</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($services)): ?>
                        <div class="empty-state">
                            <p>No services added yet</p>
                            <button onclick="showAddServiceForm()" class="btn btn-primary">Add Your First Service</button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Service Name</th>
                                        <th>Category</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $currentCategory = '';
                                    foreach ($services as $service):
                                        // Category header
                                        if ($service['category'] && $service['category'] !== $currentCategory):
                                            $currentCategory = $service['category'];
                                    ?>
                                        <tr class="category-row">
                                            <td colspan="6"><strong><?php echo htmlspecialchars($currentCategory); ?></strong></td>
                                        </tr>
                                    <?php endif; ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                                <?php if ($service['description']): ?>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($service['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($service['category'] ?: '-'); ?></td>
                                            <td><?php echo $service['duration']; ?> min</td>
                                            <td><?php echo formatCurrency($service['price'], $business['currency']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $service['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <a href="?edit=<?php echo $service['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
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

            <div class="info-card">
                <h3>💡 Pro Tip</h3>
                <p>Unlike Fresha, we don't charge you commission on your services. Set prices that work for YOUR business, and keep 100% of what you earn!</p>
            </div>
        </div>
    </main>

    <script>
        function showAddServiceForm() {
            document.getElementById('serviceFormCard').style.display = 'block';
            document.getElementById('serviceFormCard').scrollIntoView({ behavior: 'smooth' });
        }

        function hideServiceForm() {
            document.getElementById('serviceFormCard').style.display = 'none';
            window.location.href = 'services.php';
        }
    </script>
</body>
</html>
