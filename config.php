<?php
/**
 * BookingPro Configuration
 *
 * INSTALLATION INSTRUCTIONS:
 * 1. Create a MySQL database in your NameCheap cPanel
 * 2. Import database.sql into your database
 * 3. Copy .env.example to .env and update with your credentials
 * 4. Upload all files to your web hosting
 * 5. Access index.php in your browser
 */

// Load environment variables
require_once __DIR__ . '/includes/env-loader.php';
loadEnv();

// Database Configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', ''));
define('DB_USER', env('DB_USER', ''));
define('DB_PASS', env('DB_PASS', ''));

// Site Configuration
define('SITE_NAME', env('SITE_NAME', 'BookingPro'));
define('SITE_URL', env('SITE_URL', 'http://localhost'));
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@localhost'));

// Security
define('SESSION_TIMEOUT', env('SESSION_TIMEOUT', 7200));
define('PASSWORD_MIN_LENGTH', env('PASSWORD_MIN_LENGTH', 8));
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', '')); // Used for CSRF tokens

// Timezone
date_default_timezone_set('UTC'); // Change to your timezone

// Error Reporting (controlled by APP_ENV)
$appEnv = env('APP_ENV', 'production');
if ($appEnv === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Email Configuration (for sending notifications)
define('SMTP_ENABLED', env('SMTP_ENABLED', false));
define('SMTP_HOST', env('SMTP_HOST', 'smtp.yourdomain.com'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'noreply@localhost'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'BookingPro'));

// Payment Integration (Optional)
define('STRIPE_ENABLED', env('STRIPE_ENABLED', false));
define('STRIPE_PUBLISHABLE_KEY', env('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_SECRET_KEY', env('STRIPE_SECRET_KEY', ''));
define('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET', ''));

// Features
define('ALLOW_REGISTRATION', env('ALLOW_REGISTRATION', true));
define('REQUIRE_EMAIL_VERIFICATION', env('REQUIRE_EMAIL_VERIFICATION', false));
define('DEMO_MODE', env('DEMO_MODE', false))

?>
