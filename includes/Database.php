<?php
/**
 * Database Connection Class
 * Simple PDO wrapper for database operations
 */

class Database {
    private static $instance = null;
    private $conn;

    // Whitelist of valid table names (SQL injection prevention)
    private $validTables = [
        'businesses', 'staff', 'services', 'staff_services', 'working_hours',
        'clients', 'appointments', 'blocked_times', 'notifications', 'reviews',
        'email_templates', 'settings', 'recurring_appointments', 'waitlist',
        'packages', 'client_packages', 'loyalty_points', 'loyalty_transactions',
        'loyalty_rewards', 'gift_certificates', 'appointment_services',
        'group_classes', 'class_participants', 'message_templates', 'pricing_rules',
        'staff_availability_exceptions', 'payments', 'payment_methods'
    ];

    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    /**
     * Validate table name against whitelist
     */
    private function validateTable($table) {
        if (!in_array($table, $this->validTables, true)) {
            error_log("Invalid table name attempted: $table");
            throw new InvalidArgumentException("Invalid table name: $table");
        }
        return true;
    }

    /**
     * Execute a query and return results
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch a single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Insert a record and return the ID
     */
    public function insert($table, $data) {
        $this->validateTable($table);

        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = ':' . implode(', :', $keys);

        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";

        if ($this->query($sql, $data)) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Update a record
     */
    public function update($table, $data, $where, $whereParams = []) {
        $this->validateTable($table);

        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key = :$key";
        }
        $setString = implode(', ', $set);

        $sql = "UPDATE $table SET $setString WHERE $where";

        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params) !== false;
    }

    /**
     * Delete a record
     */
    public function delete($table, $where, $params = []) {
        $this->validateTable($table);

        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params) !== false;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollBack();
    }
}
?>
