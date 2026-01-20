<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

$auth = new Auth();

// If already logged in and verified, redirect to dashboard
if ($auth->isLoggedIn() && $auth->isEmailVerified()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!csrf_validate()) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email) || !isValidEmail($email)) {
            $error = 'Please enter a valid email address';
        } else {
            $result = $auth->resendVerificationEmail($email);

            if ($result['success']) {
                $success = 'Verification email sent! Please check your inbox.';
            } else {
                $error = $result['message'];
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
    <title>Resend Verification Email - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h2>Resend Verification Email</h2>
                <p>Enter your email to receive a new verification link</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form method="POST" action="" class="auth-form">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="your@email.com">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Resend Verification Email</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p><a href="login.php">← Back to Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
