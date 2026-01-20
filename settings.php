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

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_business') {
        $businessName = sanitize($_POST['business_name'] ?? '');
        $businessType = sanitize($_POST['business_type'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $timezone = sanitize($_POST['timezone'] ?? 'UTC');
        $currency = sanitize($_POST['currency'] ?? 'USD');

        if (empty($businessName)) {
            $error = 'Business name is required';
        } else {
            $updated = $db->update('businesses', [
                'business_name' => $businessName,
                'business_type' => $businessType,
                'phone' => $phone,
                'address' => $address,
                'description' => $description,
                'timezone' => $timezone,
                'currency' => $currency
            ], 'id = :id', ['id' => $businessId]);

            if ($updated) {
                $success = 'Business information updated successfully';
                $business = $auth->getBusiness(); // Refresh
            } else {
                $error = 'Failed to update business information';
            }
        }
    } elseif ($action === 'update_branding') {
        $primaryColor = sanitize($_POST['primary_color'] ?? '#3B82F6');
        $secondaryColor = sanitize($_POST['secondary_color'] ?? '#10B981');

        // Handle logo upload
        $logoUrl = $business['logo_url'];
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo'], 'logos', ['image/jpeg', 'image/png', 'image/gif']);
            if ($upload['success']) {
                $logoUrl = $upload['url'];
            } else {
                $error = $upload['message'];
            }
        }

        if (!$error) {
            $updated = $db->update('businesses', [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
                'logo_url' => $logoUrl
            ], 'id = :id', ['id' => $businessId]);

            if ($updated) {
                $success = 'Branding updated successfully';
                $business = $auth->getBusiness();
            } else {
                $error = 'Failed to update branding';
            }
        }
    } elseif ($action === 'update_booking') {
        $autoConfirm = isset($_POST['auto_confirm_bookings']) ? 1 : 0;
        $requireDeposit = isset($_POST['require_deposit']) ? 1 : 0;
        $depositPercentage = (int)($_POST['deposit_percentage'] ?? 0);
        $cancellationHours = (int)($_POST['cancellation_hours'] ?? 24);
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;

        $updated = $db->update('businesses', [
            'auto_confirm_bookings' => $autoConfirm,
            'require_deposit' => $requireDeposit,
            'deposit_percentage' => $depositPercentage,
            'cancellation_hours' => $cancellationHours,
            'email_notifications' => $emailNotifications,
            'sms_notifications' => $smsNotifications
        ], 'id = :id', ['id' => $businessId]);

        if ($updated) {
            $success = 'Booking settings updated successfully';
            $business = $auth->getBusiness();
        } else {
            $error = 'Failed to update booking settings';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h1>Settings</h1>
                <a href="book.php?slug=<?php echo $business['booking_page_slug']; ?>" target="_blank" class="btn btn-outline">
                    Preview Booking Page
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Business Information -->
            <div class="card">
                <div class="card-header">
                    <h2>Business Information</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="update_business">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="business_name">Business Name *</label>
                                <input type="text" id="business_name" name="business_name" required
                                       value="<?php echo htmlspecialchars($business['business_name']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="business_type">Business Type</label>
                                <select id="business_type" name="business_type">
                                    <option value="">Select type</option>
                                    <?php
                                    $types = ['salon' => 'Hair Salon', 'barbershop' => 'Barbershop', 'spa' => 'Spa / Wellness',
                                              'nails' => 'Nail Salon', 'beauty' => 'Beauty Services', 'massage' => 'Massage Therapy',
                                              'fitness' => 'Fitness / Personal Training', 'clinic' => 'Medical / Clinic', 'other' => 'Other'];
                                    foreach ($types as $value => $label):
                                    ?>
                                        <option value="<?php echo $value; ?>" <?php echo $business['business_type'] == $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($business['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" value="<?php echo htmlspecialchars($business['email']); ?>" disabled>
                                <small>Login email (cannot be changed here)</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($business['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($business['description'] ?? ''); ?></textarea>
                            <small>This will be displayed on your booking page</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone">
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York" <?php echo $business['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                    <option value="America/Chicago" <?php echo $business['timezone'] == 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                    <option value="America/Denver" <?php echo $business['timezone'] == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                    <option value="America/Los_Angeles" <?php echo $business['timezone'] == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                    <option value="Europe/London" <?php echo $business['timezone'] == 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select id="currency" name="currency">
                                    <?php
                                    $currencies = ['USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)',
                                                   'CAD' => 'CAD ($)', 'AUD' => 'AUD ($)', 'JPY' => 'JPY (¥)'];
                                    foreach ($currencies as $code => $label):
                                    ?>
                                        <option value="<?php echo $code; ?>" <?php echo $business['currency'] == $code ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Business Information</button>
                    </form>
                </div>
            </div>

            <!-- Branding & Customization -->
            <div class="card">
                <div class="card-header">
                    <h2>🎨 Branding & Customization</h2>
                    <span class="badge badge-success">UNLIMITED</span>
                </div>
                <div class="card-body">
                    <div class="info-banner">
                        <strong>Full Control:</strong> Fully customize and brand your booking page to match your business identity!
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" class="form">
                        <input type="hidden" name="action" value="update_branding">

                        <div class="form-group">
                            <label for="logo">Business Logo</label>
                            <?php if ($business['logo_url']): ?>
                                <div class="current-logo">
                                    <img src="<?php echo htmlspecialchars($business['logo_url']); ?>" alt="Current logo" style="max-width: 200px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" id="logo" name="logo" accept="image/*">
                            <small>Upload your logo (JPG, PNG, or GIF, max 5MB)</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="primary_color">Primary Brand Color</label>
                                <div class="color-picker-group">
                                    <input type="color" id="primary_color" name="primary_color"
                                           value="<?php echo htmlspecialchars($business['primary_color']); ?>">
                                    <input type="text" value="<?php echo htmlspecialchars($business['primary_color']); ?>" readonly>
                                </div>
                                <small>Main color for buttons and highlights</small>
                            </div>

                            <div class="form-group">
                                <label for="secondary_color">Secondary Color</label>
                                <div class="color-picker-group">
                                    <input type="color" id="secondary_color" name="secondary_color"
                                           value="<?php echo htmlspecialchars($business['secondary_color']); ?>">
                                    <input type="text" value="<?php echo htmlspecialchars($business['secondary_color']); ?>" readonly>
                                </div>
                                <small>Accent color for secondary elements</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Booking Page URL</label>
                            <div class="input-group">
                                <input type="text" value="<?php echo SITE_URL; ?>/book.php?slug=<?php echo $business['booking_page_slug']; ?>" readonly>
                                <button type="button" onclick="copyBookingURL()" class="btn btn-outline">Copy</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Branding</button>
                    </form>
                </div>
            </div>

            <!-- Booking Settings -->
            <div class="card">
                <div class="card-header">
                    <h2>Booking Settings</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="update_booking">

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_confirm_bookings" <?php echo $business['auto_confirm_bookings'] ? 'checked' : ''; ?>>
                                <strong>Auto-confirm bookings</strong>
                                <small>Automatically confirm bookings without manual approval</small>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="cancellation_hours">Free Cancellation Window</label>
                            <select id="cancellation_hours" name="cancellation_hours">
                                <?php
                                $hours = [2 => '2 hours', 4 => '4 hours', 12 => '12 hours', 24 => '24 hours', 48 => '48 hours', 72 => '72 hours'];
                                foreach ($hours as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" <?php echo $business['cancellation_hours'] == $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Clients can cancel for free up to this many hours before their appointment</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="require_deposit" <?php echo $business['require_deposit'] ? 'checked' : ''; ?>>
                                <strong>Require deposit for bookings</strong>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="deposit_percentage">Deposit Percentage</label>
                            <input type="number" id="deposit_percentage" name="deposit_percentage" min="0" max="100"
                                   value="<?php echo $business['deposit_percentage']; ?>">
                            <small>Percentage of service price to charge as deposit (0-100%)</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="email_notifications" <?php echo $business['email_notifications'] ? 'checked' : ''; ?>>
                                <strong>Email notifications</strong>
                                <small>Send confirmation and reminder emails</small>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="sms_notifications" <?php echo $business['sms_notifications'] ? 'checked' : ''; ?>>
                                <strong>SMS notifications</strong>
                                <small>Send SMS reminders (requires SMS provider setup)</small>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Booking Settings</button>
                    </form>
                </div>
            </div>

            <!-- Transparent Pricing Info -->
            <div class="card highlight-card">
                <div class="card-header">
                    <h2>💰 Your Pricing - 100% Transparent</h2>
                </div>
                <div class="card-body">
                    <div class="pricing-info">
                        <h3>Zero Commission Forever</h3>
                        <p><strong>You keep 100% of your revenue.</strong> No marketplace fees or commission charges.</p>
                        <p><strong>Your costs:</strong></p>
                        <ul>
                            <li>✅ Domain & hosting (typically $3-10/month)</li>
                            <li>✅ That's it. No hidden fees.</li>
                        </ul>
                        <p class="text-muted">Unlike other platforms that charge up to 20% commission, you keep everything you earn!</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function copyBookingURL() {
            const input = event.target.previousElementSibling;
            input.select();
            document.execCommand('copy');
            alert('Booking URL copied to clipboard!');
        }

        // Update color preview
        document.querySelectorAll('input[type="color"]').forEach(colorInput => {
            colorInput.addEventListener('change', function() {
                this.nextElementSibling.value = this.value;
            });
        });
    </script>
</body>
</html>
