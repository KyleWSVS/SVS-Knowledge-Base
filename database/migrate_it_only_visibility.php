<?php
/**
 * Database Migration Script for IT Only Visibility
 * Adds 'it_only' visibility option to categories, subcategories, and posts
 * Only Super Admins can see and assign this visibility level
 * Run this script once to update the database schema
 */

require_once '../includes/db_connect.php';
require_once '../includes/user_helpers.php';
require_once '../includes/auth_check.php';

// Only allow Super Admins to run this migration
if (!is_super_admin()) {
    die('<h2>Access Denied</h2><p>Only Super Admins can run this migration.</p>');
}

echo "<h2>IT Only Visibility Migration</h2>";

$errors = [];
$successes = [];

try {
    // Migration 1: Update categories visibility enum
    echo "<h3>1. Updating categories table...</h3>";
    try {
        $pdo->exec("ALTER TABLE `categories` MODIFY COLUMN `visibility` enum('public','hidden','restricted','it_only') NOT NULL DEFAULT 'public'");
        $successes[] = "✅ Categories visibility enum updated";
        echo "<p>✅ Categories visibility enum updated</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            $successes[] = "✅ Categories already have 'it_only' visibility";
            echo "<p>✅ Categories already have 'it_only' visibility</p>";
        } else {
            throw $e;
        }
    }

    // Migration 2: Update subcategories visibility enum
    echo "<h3>2. Updating subcategories table...</h3>";
    try {
        $pdo->exec("ALTER TABLE `subcategories` MODIFY COLUMN `visibility` enum('public','hidden','restricted','it_only') NOT NULL DEFAULT 'public'");
        $successes[] = "✅ Subcategories visibility enum updated";
        echo "<p>✅ Subcategories visibility enum updated</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            $successes[] = "✅ Subcategories already have 'it_only' visibility";
            echo "<p>✅ Subcategories already have 'it_only' visibility</p>";
        } else {
            throw $e;
        }
    }

    // Migration 3: Update posts privacy enum
    echo "<h3>3. Updating posts table...</h3>";
    try {
        $pdo->exec("ALTER TABLE `posts` MODIFY COLUMN `privacy` enum('public','private','shared','it_only') NOT NULL DEFAULT 'public'");
        $successes[] = "✅ Posts privacy enum updated";
        echo "<p>✅ Posts privacy enum updated</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            $successes[] = "✅ Posts already have 'it_only' privacy";
            echo "<p>✅ Posts already have 'it_only' privacy</p>";
        } else {
            throw $e;
        }
    }

    // Migration 4: Add indexes for performance
    echo "<h3>4. Adding performance indexes...</h3>";
    try {
        $pdo->exec("ALTER TABLE `categories` ADD INDEX `idx_visibility_it_only` (`visibility`) USING BTREE");
        echo "<p>✅ Categories index added (or already exists)</p>";
    } catch (PDOException $e) {
        // Index might already exist, that's fine
        echo "<p>ℹ️ Categories index check completed</p>";
    }

    try {
        $pdo->exec("ALTER TABLE `subcategories` ADD INDEX `idx_visibility_it_only` (`visibility`) USING BTREE");
        echo "<p>✅ Subcategories index added (or already exists)</p>";
    } catch (PDOException $e) {
        // Index might already exist, that's fine
        echo "<p>ℹ️ Subcategories index check completed</p>";
    }

    try {
        $pdo->exec("ALTER TABLE `posts` ADD INDEX `idx_privacy_it_only` (`privacy`) USING BTREE");
        echo "<p>✅ Posts index added (or already exists)</p>";
    } catch (PDOException $e) {
        // Index might already exist, that's fine
        echo "<p>ℹ️ Posts index check completed</p>";
    }

    echo "<h2>✅ Migration Completed Successfully!</h2>";
    echo "<p><strong>The 'Restricted - For IT Only' visibility option is now available for:</strong></p>";
    echo "<ul>";
    echo "<li>Posts (privacy setting)</li>";
    echo "<li>Categories (visibility setting)</li>";
    echo "<li>Subcategories (visibility setting)</li>";
    echo "</ul>";
    echo "<p><strong>Only Super Admins can see and assign this visibility level.</strong></p>";
    echo "<p><a href='../index.php' style='display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Return to Home</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ Migration Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>