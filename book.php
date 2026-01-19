<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/functions.php';

$db = Database::getInstance();

// Get business slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    die('Invalid booking page');
}

// Get business info
$business = $db->fetchOne(
    "SELECT * FROM businesses WHERE booking_page_slug = ? AND status = 'active'",
    [$slug]
);

if (!$business) {
    die('Business not found');
}

$businessId = $business['id'];

// Get services
$services = $db->fetchAll(
    "SELECT * FROM services WHERE business_id = ? AND is_active = 1 ORDER BY name",
    [$businessId]
);

// Get staff
$staff = $db->fetchAll(
    "SELECT * FROM staff WHERE business_id = ? AND is_active = 1 ORDER BY name",
    [$businessId]
);

$success = '';
$error = '';

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $staffId = (int)($_POST['staff_id'] ?? 0);
    $appointmentDate = sanitize($_POST['appointment_date'] ?? '');
    $startTime = sanitize($_POST['start_time'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $referralSource = sanitize($_POST['referral_source'] ?? '');

    if (empty($firstName) || empty($lastName) || empty($email) || empty($serviceId) || empty($staffId) || empty($appointmentDate) || empty($startTime)) {
        $error = 'Please fill in all required fields';
    } else {
        // Get service details
        $service = $db->fetchOne(
            "SELECT * FROM services WHERE id = ? AND business_id = ?",
            [$serviceId, $businessId]
        );

        if (!$service) {
            $error = 'Invalid service selected';
        } else {
            // Calculate end time
            $endTime = date('H:i:s', strtotime($startTime) + ($service['duration'] * 60));

            // Check if time slot is available
            if (!isTimeAvailable($db, $staffId, $appointmentDate, $startTime, $endTime)) {
                $error = 'This time slot is no longer available. Please choose another time.';
            } else {
                // Get or create client (CRITICAL: Proper client tracking)
                $clientId = getOrCreateClient($db, $businessId, $firstName, $lastName, $email, $phone, $referralSource);

                if (!$clientId) {
                    $error = 'Failed to create booking. Please try again.';
                } else {
                    // Create appointment
                    $appointmentId = $db->insert('appointments', [
                        'business_id' => $businessId,
                        'client_id' => $clientId,
                        'staff_id' => $staffId,
                        'service_id' => $serviceId,
                        'appointment_date' => $appointmentDate,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'status' => $business['auto_confirm_bookings'] ? 'confirmed' : 'pending',
                        'notes' => $notes,
                        'price' => $service['price'],
                        'payment_status' => 'unpaid'
                    ]);

                    if ($appointmentId) {
                        // Create notification for business
                        createNotification(
                            $db,
                            $businessId,
                            'new_booking',
                            'New Booking',
                            "$firstName $lastName booked {$service['name']} on " . formatDate($appointmentDate) . " at " . formatTime($startTime),
                            $appointmentId
                        );

                        // Send confirmation email (if enabled)
                        if ($business['email_notifications']) {
                            $subject = "Booking Confirmation - {$business['business_name']}";
                            $message = "
                                <h2>Booking Confirmation</h2>
                                <p>Dear $firstName,</p>
                                <p>Your appointment has been " . ($business['auto_confirm_bookings'] ? 'confirmed' : 'received and pending confirmation') . "!</p>
                                <p><strong>Details:</strong></p>
                                <ul>
                                    <li>Service: {$service['name']}</li>
                                    <li>Date: " . formatDate($appointmentDate) . "</li>
                                    <li>Time: " . formatTime($startTime) . "</li>
                                    <li>Price: " . formatCurrency($service['price'], $business['currency']) . "</li>
                                </ul>
                                <p>Thank you for booking with {$business['business_name']}!</p>
                            ";

                            sendEmail($email, $subject, $message, $business['business_name']);
                        }

                        $success = 'Your appointment has been ' . ($business['auto_confirm_bookings'] ? 'confirmed' : 'received') . '! You will receive a confirmation email shortly.';
                    } else {
                        $error = 'Failed to create booking. Please try again.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/booking.css">
    <style>
        :root {
            --primary-color: <?php echo $business['primary_color']; ?>;
            --secondary-color: <?php echo $business['secondary_color']; ?>;
        }
    </style>
</head>
<body class="booking-page">
    <div class="booking-container">
        <div class="booking-header">
            <?php if ($business['logo_url']): ?>
                <img src="<?php echo htmlspecialchars($business['logo_url']); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?>" class="business-logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($business['business_name']); ?></h1>
            <?php if ($business['description']): ?>
                <p class="business-description"><?php echo nl2br(htmlspecialchars($business['description'])); ?></p>
            <?php endif; ?>
            <?php if ($business['address']): ?>
                <p class="business-address">📍 <?php echo nl2br(htmlspecialchars($business['address'])); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success booking-success">
                <h2>✅ <?php echo $success; ?></h2>
                <a href="book.php?slug=<?php echo $slug; ?>" class="btn btn-primary">Book Another Appointment</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="booking-form" id="bookingForm">
                <input type="hidden" name="action" value="book">

                <div class="form-step active" data-step="1">
                    <h2>Select a Service</h2>
                    <?php if (empty($services)): ?>
                        <p>No services available at this time.</p>
                    <?php else: ?>
                        <div class="services-grid">
                            <?php foreach ($services as $service): ?>
                                <label class="service-card">
                                    <input type="radio" name="service_id" value="<?php echo $service['id']; ?>" required
                                           data-duration="<?php echo $service['duration']; ?>"
                                           data-price="<?php echo $service['price']; ?>">
                                    <div class="service-info">
                                        <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                        <?php if ($service['description']): ?>
                                            <p><?php echo htmlspecialchars($service['description']); ?></p>
                                        <?php endif; ?>
                                        <div class="service-meta">
                                            <span>⏱ <?php echo $service['duration']; ?> min</span>
                                            <span class="service-price"><?php echo formatCurrency($service['price'], $business['currency']); ?></span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                </div>

                <div class="form-step" data-step="2">
                    <h2>Choose Staff Member</h2>
                    <?php if (empty($staff)): ?>
                        <p>No staff available at this time.</p>
                    <?php else: ?>
                        <div class="staff-grid">
                            <?php foreach ($staff as $member): ?>
                                <label class="staff-card">
                                    <input type="radio" name="staff_id" value="<?php echo $member['id']; ?>" required>
                                    <div class="staff-info">
                                        <?php if ($member['photo_url']): ?>
                                            <img src="<?php echo htmlspecialchars($member['photo_url']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>">
                                        <?php else: ?>
                                            <div class="staff-avatar"><?php echo strtoupper(substr($member['name'], 0, 1)); ?></div>
                                        <?php endif; ?>
                                        <h3><?php echo htmlspecialchars($member['name']); ?></h3>
                                        <?php if ($member['role']): ?>
                                            <p><?php echo htmlspecialchars($member['role']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-outline" onclick="prevStep()">Back</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                    </div>
                </div>

                <div class="form-step" data-step="3">
                    <h2>Select Date & Time</h2>

                    <div class="form-group">
                        <label for="appointment_date">Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date" required
                               min="<?php echo date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d', strtotime('+90 days')); ?>">
                    </div>

                    <div class="form-group">
                        <label>Available Times *</label>
                        <div id="timeSlotsContainer" class="time-slots">
                            <p class="text-muted">Select a date to see available times</p>
                        </div>
                    </div>

                    <div class="step-buttons">
                        <button type="button" class="btn btn-outline" onclick="prevStep()">Back</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                    </div>
                </div>

                <div class="form-step" data-step="4">
                    <h2>Your Information</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" placeholder="+1 (555) 123-4567">
                    </div>

                    <div class="form-group">
                        <label for="referral_source">How did you find us?</label>
                        <select id="referral_source" name="referral_source">
                            <option value="">Select an option</option>
                            <option value="google">Google Search</option>
                            <option value="instagram">Instagram</option>
                            <option value="facebook">Facebook</option>
                            <option value="referral">Friend/Family Referral</option>
                            <option value="walk_by">Walked By</option>
                            <option value="returning">Returning Client</option>
                            <option value="other">Other</option>
                        </select>
                        <small>This helps us understand where our clients come from (NO COMMISSION CHARGED!)</small>
                    </div>

                    <div class="form-group">
                        <label for="notes">Special Requests or Notes</label>
                        <textarea id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="booking-summary">
                        <h3>Booking Summary</h3>
                        <div id="bookingSummary"></div>
                    </div>

                    <div class="step-buttons">
                        <button type="button" class="btn btn-outline" onclick="prevStep()">Back</button>
                        <button type="submit" class="btn btn-primary btn-large">Confirm Booking</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="booking-footer">
            <p>Powered by <?php echo SITE_NAME; ?> - Zero Commission Booking</p>
        </div>
    </div>

    <script src="<?php echo SITE_URL; ?>/assets/js/booking.js"></script>
    <script>
        const SITE_URL = '<?php echo SITE_URL; ?>';
        const businessSlug = '<?php echo $slug; ?>';
    </script>
</body>
</html>
