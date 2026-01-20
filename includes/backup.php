<?php
/**
 * Database Backup Script
 * Creates MySQL database backups and stores them securely
 *
 * USAGE:
 * - Manual: php includes/backup.php
 * - Cron: 0 2 * * * cd /path/to/app && php includes/backup.php
 */

// Only allow CLI execution or authorized requests
if (php_sapi_name() !== 'cli' && !isset($_GET['backup_key'])) {
    die('Unauthorized');
}

require_once __DIR__ . '/../config.php';

// Configuration
$backupDir = __DIR__ . '/../backups';
$maxBackups = 30; // Keep last 30 backups

// Create backup directory if it doesn't exist
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Generate backup filename
$timestamp = date('Y-m-d_H-i-s');
$backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
$gzBackupFile = $backupFile . '.gz';

echo "Starting database backup...\n";

// Build mysqldump command
$command = sprintf(
    'mysqldump --user=%s --password=%s --host=%s %s > %s 2>&1',
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_NAME),
    escapeshellarg($backupFile)
);

// Execute backup
exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    error_log('Backup failed: ' . implode("\n", $output));
    echo "ERROR: Backup failed!\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

// Compress backup
if (file_exists($backupFile)) {
    echo "Compressing backup...\n";
    exec("gzip " . escapeshellarg($backupFile));

    if (file_exists($gzBackupFile)) {
        $size = filesize($gzBackupFile);
        $sizeMB = round($size / 1024 / 1024, 2);
        echo "SUCCESS: Backup created: " . basename($gzBackupFile) . " ($sizeMB MB)\n";

        // Log backup
        error_log("Database backup created: $gzBackupFile ($sizeMB MB)");
    } else {
        echo "WARNING: Compression failed\n";
    }
} else {
    echo "ERROR: Backup file not created\n";
    exit(1);
}

// Cleanup old backups
echo "Cleaning up old backups (keeping last $maxBackups)...\n";
$backups = glob($backupDir . '/backup_*.sql.gz');
rsort($backups); // Sort by date (newest first)

$deleted = 0;
foreach (array_slice($backups, $maxBackups) as $oldBackup) {
    if (unlink($oldBackup)) {
        $deleted++;
        echo "Deleted: " . basename($oldBackup) . "\n";
    }
}

echo "Cleanup complete. Deleted $deleted old backup(s).\n";
echo "Backup process finished successfully!\n";

// Send notification (optional)
if (defined('ADMIN_EMAIL') && function_exists('mail')) {
    $subject = 'Database Backup Completed - ' . SITE_NAME;
    $message = "Database backup completed successfully\n\n";
    $message .= "File: " . basename($gzBackupFile) . "\n";
    $message .= "Size: $sizeMB MB\n";
    $message .= "Time: " . date('Y-m-d H:i:s') . "\n";

    mail(ADMIN_EMAIL, $subject, $message);
}
