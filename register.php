<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

$auth = new Auth();

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Check if registration is allowed
if (!ALLOW_REGISTRATION) {
    die('Registration is currently closed');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!csrf_validate()) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $businessName = sanitize($_POST['business_name'] ?? '');
        $businessType = sanitize($_POST['business_type'] ?? '');

        if (empty($email) || empty($password) || empty($businessName)) {
            $error = 'Please fill in all required fields';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
        $result = $auth->register($email, $password, $businessName, $businessType);

        if ($result['success']) {
            $businessId = $result['business_id'];

            // Send verification email if required
            if (REQUIRE_EMAIL_VERIFICATION) {
                $verificationResult = $auth->sendVerificationEmail($businessId, $email, $businessName);

                if ($verificationResult['success']) {
                    $success = 'Registration successful! Please check your email to verify your account.';
                } else {
                    $success = 'Registration successful, but we couldn\'t send the verification email. Please contact support.';
                }
            } else {
                // Auto-login after registration if verification not required
                $auth->login($email, $password);
                header('Location: dashboard.php?welcome=1');
                exit;
            }
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
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h2>Create Your Account</h2>
                <p>Start accepting bookings in minutes - Zero commission forever</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="business_name">Business Name *</label>
                    <input type="text" id="business_name" name="business_name" required
                           value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>"
                           placeholder="e.g., Bella's Beauty Salon">
                </div>

                <div class="form-group">
                    <label for="business_type">Business Type</label>
                    <select id="business_type" name="business_type">
                        <option value="">Select type</option>
                        <option value="salon">Hair Salon</option>
                        <option value="barbershop">Barbershop</option>
                        <option value="spa">Spa / Wellness</option>
                        <option value="nails">Nail Salon</option>
                        <option value="beauty">Beauty Services</option>
                        <option value="massage">Massage Therapy</option>
                        <option value="fitness">Fitness / Personal Training</option>
                        <option value="clinic">Medical / Clinic</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters">
                    <small>Use a strong password with letters, numbers, and symbols</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           placeholder="Re-enter your password">
                </div>

                <div class="form-note">
                    <p><strong>What makes us different:</strong></p>
                    <ul>
                        <li>✅ Zero commission - keep 100% of your revenue</li>
                        <li>✅ No hidden fees - ever</li>
                        <li>✅ You own your data</li>
                        <li>✅ Full customization</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p><a href="index.php">← Back to home</a></p>
            </div>
        </div>
    </div>
</body>
</html>
