<?php
/**
 * Authentication Class
 * Handles user registration, login, and session management
 */

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();

        // Start session if not already started with security settings
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session cookie parameters
            $cookieParams = [
                'lifetime' => SESSION_TIMEOUT,
                'path' => '/',
                'domain' => parse_url(SITE_URL, PHP_URL_HOST) ?? '',
                'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), // Only true if HTTPS
                'httponly' => true, // Prevent JavaScript access
                'samesite' => 'Lax' // CSRF protection
            ];
            session_set_cookie_params($cookieParams);

            session_start();

            // Session fingerprinting (prevent session hijacking)
            $this->validateSessionFingerprint();
        }

        // Check session timeout
        if (isset($_SESSION['business_id']) && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                $this->logout();
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Validate session fingerprint to prevent session hijacking
     */
    private function validateSessionFingerprint() {
        $fingerprint = $this->generateSessionFingerprint();

        if (isset($_SESSION['fingerprint'])) {
            if ($_SESSION['fingerprint'] !== $fingerprint) {
                // Potential session hijacking attempt
                error_log("Session hijacking attempt detected for session: " . session_id());
                $this->logout();
            }
        } else {
            $_SESSION['fingerprint'] = $fingerprint;
        }
    }

    /**
     * Generate session fingerprint based on user agent and IP
     */
    private function generateSessionFingerprint() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Use first 3 octets of IP to allow for dynamic IPs within same network
        $ipParts = explode('.', $ipAddress);
        $ipPrefix = implode('.', array_slice($ipParts, 0, 3));

        return hash('sha256', $userAgent . $ipPrefix);
    }

    /**
     * Register a new business
     */
    public function register($email, $password, $businessName, $businessType = null) {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        // Validate password
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }

        // Check if email already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM businesses WHERE email = ?",
            [$email]
        );

        if ($existing) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Generate unique booking page slug
        $slug = $this->generateSlug($businessName);

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert business
        $businessId = $this->db->insert('businesses', [
            'email' => $email,
            'password' => $hashedPassword,
            'business_name' => $businessName,
            'business_type' => $businessType,
            'booking_page_slug' => $slug,
            'status' => 'active'
        ]);

        if ($businessId) {
            // Create default working hours (9 AM - 6 PM, Monday - Friday)
            for ($day = 1; $day <= 5; $day++) {
                $this->db->insert('working_hours', [
                    'business_id' => $businessId,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'is_closed' => 0
                ]);
            }

            // Weekend - closed
            $this->db->insert('working_hours', [
                'business_id' => $businessId,
                'day_of_week' => 0,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_closed' => 1
            ]);
            $this->db->insert('working_hours', [
                'business_id' => $businessId,
                'day_of_week' => 6,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_closed' => 1
            ]);

            return [
                'success' => true,
                'message' => 'Registration successful! Please login.',
                'business_id' => $businessId
            ];
        }

        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    /**
     * Login a business
     */
    public function login($email, $password) {
        $business = $this->db->fetchOne(
            "SELECT * FROM businesses WHERE email = ?",
            [$email]
        );

        if (!$business) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        if (!password_verify($password, $business['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        if ($business['status'] === 'suspended') {
            return ['success' => false, 'message' => 'Your account has been suspended'];
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Set session
        $_SESSION['business_id'] = $business['id'];
        $_SESSION['business_name'] = $business['business_name'];
        $_SESSION['business_email'] = $business['email'];
        $_SESSION['last_activity'] = time();
        $_SESSION['fingerprint'] = $this->generateSessionFingerprint();

        return ['success' => true, 'message' => 'Login successful'];
    }

    /**
     * Logout
     */
    public function logout() {
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['business_id']);
    }

    /**
     * Get current business ID
     */
    public function getBusinessId() {
        return $_SESSION['business_id'] ?? null;
    }

    /**
     * Get current business info
     */
    public function getBusiness() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return $this->db->fetchOne(
            "SELECT * FROM businesses WHERE id = ?",
            [$this->getBusinessId()]
        );
    }

    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Generate unique booking page slug
     */
    private function generateSlug($businessName) {
        $slug = strtolower(trim($businessName));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Check if slug exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM businesses WHERE booking_page_slug = ?",
            [$slug]
        );

        if ($existing) {
            $slug .= '-' . rand(1000, 9999);
        }

        return $slug;
    }

    /**
     * Update password
     */
    public function updatePassword($businessId, $oldPassword, $newPassword) {
        $business = $this->db->fetchOne(
            "SELECT password FROM businesses WHERE id = ?",
            [$businessId]
        );

        if (!password_verify($oldPassword, $business['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updated = $this->db->update(
            'businesses',
            ['password' => $hashedPassword],
            'id = :id',
            ['id' => $businessId]
        );

        if ($updated) {
            return ['success' => true, 'message' => 'Password updated successfully'];
        }

        return ['success' => false, 'message' => 'Failed to update password'];
    }

    /**
     * Generate email verification token
     */
    public function generateVerificationToken($businessId) {
        $token = bin2hex(random_bytes(32));

        $updated = $this->db->update(
            'businesses',
            [
                'email_verification_token' => $token,
                'email_verification_sent_at' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $businessId]
        );

        return $updated ? $token : false;
    }

    /**
     * Send verification email
     */
    public function sendVerificationEmail($businessId, $email, $businessName) {
        require_once __DIR__ . '/functions.php';

        $token = $this->generateVerificationToken($businessId);

        if (!$token) {
            return ['success' => false, 'message' => 'Failed to generate verification token'];
        }

        $verificationUrl = SITE_URL . '/verify-email.php?token=' . $token;

        $subject = 'Verify Your Email - ' . SITE_NAME;
        $message = "
            <h2>Welcome to " . SITE_NAME . "!</h2>
            <p>Hi $businessName,</p>
            <p>Thank you for registering your business with us. Please verify your email address by clicking the link below:</p>
            <p><a href='$verificationUrl' style='background-color: #6366F1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Verify Email Address</a></p>
            <p>Or copy and paste this link into your browser:</p>
            <p>$verificationUrl</p>
            <p>This link will expire in 24 hours.</p>
            <p>If you didn't create an account with us, please ignore this email.</p>
            <p>Best regards,<br>The " . SITE_NAME . " Team</p>
        ";

        $sent = sendEmail($email, $subject, $message, SITE_NAME);

        if ($sent) {
            return ['success' => true, 'message' => 'Verification email sent'];
        }

        return ['success' => false, 'message' => 'Failed to send verification email'];
    }

    /**
     * Verify email with token
     */
    public function verifyEmail($token) {
        if (empty($token)) {
            return ['success' => false, 'message' => 'Invalid verification token'];
        }

        // Find business with this token
        $business = $this->db->fetchOne(
            "SELECT * FROM businesses WHERE email_verification_token = ?",
            [$token]
        );

        if (!$business) {
            return ['success' => false, 'message' => 'Invalid or expired verification token'];
        }

        // Check if already verified
        if ($business['email_verified']) {
            return ['success' => false, 'message' => 'Email already verified'];
        }

        // Check if token is expired (24 hours)
        $sentAt = strtotime($business['email_verification_sent_at']);
        if (time() - $sentAt > 86400) {
            return ['success' => false, 'message' => 'Verification link has expired. Please request a new one.'];
        }

        // Verify the email
        $updated = $this->db->update(
            'businesses',
            [
                'email_verified' => 1,
                'email_verification_token' => null
            ],
            'id = :id',
            ['id' => $business['id']]
        );

        if ($updated) {
            return ['success' => true, 'message' => 'Email verified successfully!', 'business_id' => $business['id']];
        }

        return ['success' => false, 'message' => 'Failed to verify email'];
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail($email) {
        $business = $this->db->fetchOne(
            "SELECT * FROM businesses WHERE email = ?",
            [$email]
        );

        if (!$business) {
            return ['success' => false, 'message' => 'Email not found'];
        }

        if ($business['email_verified']) {
            return ['success' => false, 'message' => 'Email already verified'];
        }

        return $this->sendVerificationEmail($business['id'], $business['email'], $business['business_name']);
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified($businessId = null) {
        if ($businessId === null) {
            $businessId = $this->getBusinessId();
        }

        if (!$businessId) {
            return false;
        }

        $business = $this->db->fetchOne(
            "SELECT email_verified FROM businesses WHERE id = ?",
            [$businessId]
        );

        return $business && $business['email_verified'];
    }
}
?>
