<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

$auth = new Auth();

$token = $_GET['token'] ?? '';
$success = '';
$error = '';

if (empty($token)) {
    $error = 'Invalid verification link';
} else {
    $result = $auth->verifyEmail($token);

    if ($result['success']) {
        $success = $result['message'];

        // Auto-login the user after verification
        if (isset($result['business_id'])) {
            $business = $auth->getBusiness();
            if ($business) {
                $_SESSION['business_id'] = $business['id'];
                $_SESSION['business_name'] = $business['business_name'];
                $_SESSION['business_email'] = $business['email'];
                $_SESSION['last_activity'] = time();
            }
        }
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h2>Email Verification</h2>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h3>✅ <?php echo $success; ?></h3>
                    <p>Your email has been verified and your account is now active!</p>
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <h3>❌ Verification Failed</h3>
                    <p><?php echo $error; ?></p>
                    <?php if (strpos($error, 'expired') !== false): ?>
                        <p>Would you like to request a new verification email?</p>
                        <a href="resend-verification.php" class="btn btn-primary">Resend Verification Email</a>
                    <?php endif; ?>
                    <p style="margin-top: 20px;">
                        <a href="login.php">← Back to Login</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
