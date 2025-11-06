<?php
/**
 * Migration: Add user_pinned_categories table
 * This allows users to pin/favorite categories for quick access
 * Run this script once to apply the migration
 */

require_once '../includes/db_connect.php';
require_once '../includes/user_helpers.php';

// Only Super Admins can run migrations
if (!is_super_admin()) {
    die('Error: Only Super Admins can run migrations.');
}

try {
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_pinned_categories'");
    $table_exists = $stmt->rowCount() > 0;

    if ($table_exists) {
        echo "✓ Table user_pinned_categories already exists. No changes needed.";
    } else {
        // Create the table
        $pdo->exec("
            CREATE TABLE user_pinned_categories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                category_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_category (user_id, category_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )
        ");

        echo "✓ Successfully created user_pinned_categories table!<br>";
        echo "This table stores which categories each user has pinned for quick access.<br>";
    }

    echo "<br><strong>Migration complete!</strong>";

} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    error_log("Migration Error: " . $e->getMessage());
}
?>
