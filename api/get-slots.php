<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$db = Database::getInstance();

$staffId = (int)($_GET['staff_id'] ?? 0);
$date = sanitize($_GET['date'] ?? '');
$duration = (int)($_GET['duration'] ?? 30);
$businessSlug = sanitize($_GET['business'] ?? '');

if (!$staffId || !$date || !$businessSlug) {
    jsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
}

// Get business ID from slug
$business = $db->fetchOne(
    "SELECT id FROM businesses WHERE booking_page_slug = ?",
    [$businessSlug]
);

if (!$business) {
    jsonResponse(['success' => false, 'message' => 'Business not found'], 404);
}

// Validate date
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    jsonResponse(['success' => false, 'message' => 'Invalid date format'], 400);
}

// Check if date is in the past
if ($date < date('Y-m-d')) {
    jsonResponse(['success' => false, 'slots' => []], 200);
}

// Get available slots
$slots = getAvailableSlots($db, $staffId, $date, $duration);

jsonResponse([
    'success' => true,
    'slots' => $slots,
    'date' => $date
], 200);
?>
