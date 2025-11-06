<?php
/**
 * Migration: Add Login Security Features
 * - Hashes existing PINs in database
 * - Adds failed_attempts and locked_until columns
 * - Only accessible by Super Admins
 */

require_once '../includes/db_connect.php';
require_once '../includes/user_helpers.php';

// Only Super Admins can run migrations
if (!is_super_admin()) {
    die('Error: Only Super Admins can run migrations.');
}

try {
    echo "<h2>🔐 Login Security Migration</h2>";

    // Step 1: Add columns if they don't exist
    echo "<p><strong>Step 1: Adding security columns...</strong></p>";

    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `failed_attempts` INT DEFAULT 0");
    echo "✓ Added/verified failed_attempts column<br>";

    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL");
    echo "✓ Added/verified locked_until column<br>";

    // Step 2: Hash existing PINs that aren't already hashed
    echo "<p><strong>Step 2: Hashing existing PINs...</strong></p>";

    $stmt = $pdo->query("SELECT id, pin FROM users WHERE pin IS NOT NULL AND pin != ''");
    $users = $stmt->fetchAll();
    $hashed_count = 0;
    $already_hashed = 0;

    foreach ($users as $user) {
        $pin = $user['pin'];

        // Check if PIN is already hashed (hashes start with $2y$ for bcrypt)
        if (strpos($pin, '$2y$') === 0) {
            $already_hashed++;
            continue;
        }

        // Hash the PIN using bcrypt with cost 10
        $hashed_pin = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]);

        // Update the database with hashed PIN
        $update_stmt = $pdo->prepare("UPDATE users SET pin = ? WHERE id = ?");
        $update_stmt->execute([$hashed_pin, $user['id']]);
        $hashed_count++;
    }

    echo "✓ Hashed $hashed_count PINs<br>";
    if ($already_hashed > 0) {
        echo "✓ $already_hashed PINs were already hashed<br>";
    }

    echo "<p style='color: green; font-weight: bold;'>✓ Migration completed successfully!</p>";
    echo "<p style='background: #d4edda; padding: 12px; border-radius: 4px; margin-top: 20px;'>";
    echo "<strong>Login security features are now active:</strong><br>";
    echo "✓ PINs are now hashed and stored securely<br>";
    echo "✓ Failed login attempts are tracked<br>";
    echo "✓ After 10 failed attempts, account locks for 2 minutes<br>";
    echo "</p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Migration Error: " . $e->getMessage());
}
?>
