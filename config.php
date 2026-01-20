<?php
/**
 * BookingPro Configuration
 *
 * INSTALLATION INSTRUCTIONS:
 * 1. Create a MySQL database in your NameCheap cPanel
 * 2. Import database.sql into your database
 * 3. Update the database credentials below
 * 4. Upload all files to your web hosting
 * 5. Access index.php in your browser
 */

// Database Configuration
define('DB_HOST', 'localhost');              // Usually 'localhost' for shared hosting
define('DB_NAME', 'ausshgzu_chores');        // Your MySQL database name
define('DB_USER', 'ausshgzu_choreuser');     // Your MySQL username
define('DB_PASS', 'utp674"N&=-wcfT');        // Your MySQL password

// Site Configuration
define('SITE_NAME', 'Chores');
define('SITE_URL', 'https://chores.to');     // Your domain URL (no trailing slash)
define('ADMIN_EMAIL', 'craigbinn@gmail.com'); // Admin email for notifications

// Security
define('SESSION_TIMEOUT', 7200); // Session timeout in seconds (2 hours)
define('PASSWORD_MIN_LENGTH', 8);

// Timezone
date_default_timezone_set('UTC'); // Change to your timezone

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Email Configuration (for sending notifications)
define('SMTP_ENABLED', false); // Set to true if you want to use SMTP
define('SMTP_HOST', 'smtp.yourdomain.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_FROM_EMAIL', 'noreply@chores.to');
define('SMTP_FROM_NAME', 'Chores');

// Payment Integration (Optional)
define('STRIPE_ENABLED', false);
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_xxxxx');
define('STRIPE_SECRET_KEY', 'sk_test_xxxxx');

// Features
define('ALLOW_REGISTRATION', true); // Allow new businesses to register
define('REQUIRE_EMAIL_VERIFICATION', false); // Require email verification
define('DEMO_MODE', false); // Set to true to disable certain actions

?>
