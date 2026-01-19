<?php
/**
 * Utility Functions
 * Helper functions used throughout the application
 */

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time
 */
function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'USD') {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CAD' => 'CA$',
        'AUD' => 'A$'
    ];

    $symbol = $symbols[$currency] ?? '$';
    return $symbol . number_format($amount, 2);
}

/**
 * Get time slots for a day
 */
function getTimeSlots($startTime, $endTime, $interval = 30) {
    $slots = [];
    $start = strtotime($startTime);
    $end = strtotime($endTime);

    while ($start < $end) {
        $slots[] = date('H:i:s', $start);
        $start = strtotime("+$interval minutes", $start);
    }

    return $slots;
}

/**
 * Get day name from day of week number
 */
function getDayName($dayOfWeek) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$dayOfWeek] ?? '';
}

/**
 * Send email (basic PHP mail or SMTP)
 */
function sendEmail($to, $subject, $message, $fromName = null) {
    $fromName = $fromName ?? SITE_NAME;

    if (SMTP_ENABLED) {
        // TODO: Implement SMTP using PHPMailer or similar
        // For now, use basic PHP mail
    }

    $headers = "From: $fromName <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 1, $length);
}

/**
 * JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Check if time is available
 */
function isTimeAvailable($db, $staffId, $date, $startTime, $endTime, $excludeAppointmentId = null) {
    $sql = "SELECT COUNT(*) as count FROM appointments
            WHERE staff_id = :staff_id
            AND appointment_date = :date
            AND status NOT IN ('cancelled', 'no_show')
            AND (
                (start_time < :end_time AND end_time > :start_time)
            )";

    $params = [
        'staff_id' => $staffId,
        'date' => $date,
        'start_time' => $startTime,
        'end_time' => $endTime
    ];

    if ($excludeAppointmentId) {
        $sql .= " AND id != :exclude_id";
        $params['exclude_id'] = $excludeAppointmentId;
    }

    $result = $db->fetchOne($sql, $params);
    return $result['count'] == 0;
}

/**
 * Get available time slots for booking
 */
function getAvailableSlots($db, $staffId, $date, $serviceDuration) {
    // Get working hours for this day
    $dayOfWeek = date('w', strtotime($date));

    $workingHours = $db->fetchOne(
        "SELECT * FROM working_hours
         WHERE (staff_id = :staff_id OR business_id IN (SELECT business_id FROM staff WHERE id = :staff_id2))
         AND day_of_week = :day
         AND is_closed = 0
         ORDER BY staff_id DESC
         LIMIT 1",
        ['staff_id' => $staffId, 'staff_id2' => $staffId, 'day' => $dayOfWeek]
    );

    if (!$workingHours) {
        return [];
    }

    // Get all time slots
    $allSlots = getTimeSlots($workingHours['start_time'], $workingHours['end_time'], 15);

    // Get booked appointments
    $bookedAppointments = $db->fetchAll(
        "SELECT start_time, end_time FROM appointments
         WHERE staff_id = :staff_id
         AND appointment_date = :date
         AND status NOT IN ('cancelled', 'no_show')",
        ['staff_id' => $staffId, 'date' => $date]
    );

    // Get blocked times
    $blockedTimes = $db->fetchAll(
        "SELECT start_time, end_time FROM blocked_times
         WHERE staff_id = :staff_id
         AND blocked_date = :date",
        ['staff_id' => $staffId, 'date' => $date]
    );

    $availableSlots = [];

    foreach ($allSlots as $slot) {
        $slotTime = strtotime($slot);
        $slotEndTime = strtotime("+$serviceDuration minutes", $slotTime);
        $isAvailable = true;

        // Check if slot conflicts with booked appointments
        foreach ($bookedAppointments as $appointment) {
            $appointmentStart = strtotime($appointment['start_time']);
            $appointmentEnd = strtotime($appointment['end_time']);

            if ($slotTime < $appointmentEnd && $slotEndTime > $appointmentStart) {
                $isAvailable = false;
                break;
            }
        }

        // Check if slot conflicts with blocked times
        if ($isAvailable) {
            foreach ($blockedTimes as $blocked) {
                $blockedStart = strtotime($blocked['start_time']);
                $blockedEnd = strtotime($blocked['end_time']);

                if ($slotTime < $blockedEnd && $slotEndTime > $blockedStart) {
                    $isAvailable = false;
                    break;
                }
            }
        }

        // Check if slot end time is within working hours
        if ($isAvailable && $slotEndTime > strtotime($workingHours['end_time'])) {
            $isAvailable = false;
        }

        if ($isAvailable) {
            $availableSlots[] = $slot;
        }
    }

    return $availableSlots;
}

/**
 * Get or create client
 * CRITICAL FEATURE: Proper client tracking to avoid false "new client" charges
 */
function getOrCreateClient($db, $businessId, $firstName, $lastName, $email, $phone = null, $referralSource = null) {
    // First, try to find existing client by email
    $client = null;

    if ($email) {
        $client = $db->fetchOne(
            "SELECT * FROM clients WHERE business_id = :business_id AND email = :email",
            ['business_id' => $businessId, 'email' => $email]
        );
    }

    // If not found by email, try phone
    if (!$client && $phone) {
        $client = $db->fetchOne(
            "SELECT * FROM clients WHERE business_id = :business_id AND phone = :phone",
            ['business_id' => $businessId, 'phone' => $phone]
        );
    }

    // If client exists, update their info if needed
    if ($client) {
        $updates = [];
        $params = ['id' => $client['id']];

        if ($email && $client['email'] != $email) {
            $updates[] = 'email = :email';
            $params['email'] = $email;
        }

        if ($phone && $client['phone'] != $phone) {
            $updates[] = 'phone = :phone';
            $params['phone'] = $phone;
        }

        if (!empty($updates)) {
            $db->update('clients', $params, 'id = :id', ['id' => $client['id']]);
        }

        return $client['id'];
    }

    // Create new client
    $clientId = $db->insert('clients', [
        'business_id' => $businessId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'first_visit' => date('Y-m-d'),
        'total_visits' => 0,
        'referral_source' => $referralSource
    ]);

    return $clientId;
}

/**
 * Update client stats after appointment
 */
function updateClientStats($db, $clientId, $appointmentPrice) {
    $db->query(
        "UPDATE clients
         SET total_visits = total_visits + 1,
             total_spent = total_spent + :price,
             last_visit = :date
         WHERE id = :id",
        [
            'price' => $appointmentPrice,
            'date' => date('Y-m-d'),
            'id' => $clientId
        ]
    );
}

/**
 * Create notification
 */
function createNotification($db, $businessId, $type, $title, $message, $appointmentId = null) {
    return $db->insert('notifications', [
        'business_id' => $businessId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'related_appointment_id' => $appointmentId,
        'is_read' => 0
    ]);
}

/**
 * Get file upload path
 */
function getUploadPath($type = 'logos') {
    $uploadDir = __DIR__ . '/../uploads/' . $type . '/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    return $uploadDir;
}

/**
 * Upload file
 */
function uploadFile($file, $type = 'logos', $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return ['success' => false, 'message' => 'File too large (max 5MB)'];
    }

    $uploadDir = getUploadPath($type);
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'url' => SITE_URL . '/uploads/' . $type . '/' . $filename
        ];
    }

    return ['success' => false, 'message' => 'Failed to save file'];
}
?>
