<?php
/**
 * Database Migration Script for Edit Requests
 * Adds the 'reason' field to the edit_requests table if it doesn't exist
 * Run this script once to update the database schema
 */

require_once '../includes/db_connect.php';

echo "<h2>Edit Requests Table Migration</h2>";

try {
    // Check if edit_requests table exists
    $pdo->query("SELECT id FROM edit_requests LIMIT 1");
    echo "<p>✅ Edit requests table exists</p>";

    // Check if reason column exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as column_exists
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'edit_requests'
        AND COLUMN_NAME = 'reason'
    ");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result['column_exists'] > 0) {
        echo "<p>✅ Reason column already exists</p>";
    } else {
        // Add the reason column
        $pdo->exec("ALTER TABLE edit_requests ADD COLUMN reason TEXT NOT NULL DEFAULT ''");
        echo "<p>✅ Added reason column to edit_requests table</p>";
    }

    echo "<p><strong>Migration completed successfully!</strong></p>";
    echo "<p><a href='../index.php'>Return to Home</a></p>";

} catch (PDOException $e) {
    echo "<p>❌ Migration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>