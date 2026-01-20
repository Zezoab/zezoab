<?php
/**
 * CSRF Protection Class
 * Generates and validates CSRF tokens for forms
 */

class CSRF {

    /**
     * Generate a CSRF token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate token if it doesn't exist or is older than 1 hour
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Get CSRF token input field HTML
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Validate CSRF token
     */
    public static function validateToken($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get token from parameter or POST data
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        }

        // Check if token exists in session
        if (!isset($_SESSION['csrf_token'])) {
            error_log("CSRF validation failed: No token in session");
            return false;
        }

        // Check if token has expired (1 hour)
        if (!isset($_SESSION['csrf_token_time']) || (time() - $_SESSION['csrf_token_time']) > 3600) {
            error_log("CSRF validation failed: Token expired");
            return false;
        }

        // Validate token using timing-safe comparison
        $valid = hash_equals($_SESSION['csrf_token'], $token);

        if (!$valid) {
            error_log("CSRF validation failed: Token mismatch");
        }

        return $valid;
    }

    /**
     * Validate and die if invalid
     */
    public static function validateOrDie($errorMessage = 'Invalid security token. Please try again.') {
        if (!self::validateToken()) {
            http_response_code(403);
            die($errorMessage);
        }
    }

    /**
     * Refresh the CSRF token (useful after successful form submission)
     */
    public static function refreshToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();

        return $_SESSION['csrf_token'];
    }
}
