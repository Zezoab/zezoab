<?php
/**
 * Authentication Class
 * Handles user registration, login, and session management
 */

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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

        // Set session
        $_SESSION['business_id'] = $business['id'];
        $_SESSION['business_name'] = $business['business_name'];
        $_SESSION['business_email'] = $business['email'];
        $_SESSION['last_activity'] = time();

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
}
?>
