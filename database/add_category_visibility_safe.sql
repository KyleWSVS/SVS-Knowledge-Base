-- Add Category Visibility Controls (Safe Version)
-- Only adds columns that don't already exist

-- Add visibility column if it doesn't exist
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'categories'
AND COLUMN_NAME = 'visibility';

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `categories` ADD COLUMN `visibility` enum(''public',''hidden',''restricted'') NOT NULL DEFAULT 'public' COMMENT 'Category visibility: public (everyone), hidden (only admin), restricted (specific users)'',
    'SELECT "Visibility column already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add allowed_users column if it doesn't exist
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'categories'
AND COLUMN_NAME = 'allowed_users';

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `categories` ADD COLUMN `allowed_users` text DEFAULT NULL COMMENT 'JSON array of user IDs who can see restricted categories''',
    'SELECT "Allowed_users column already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add visibility_note column if it doesn't exist
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'categories'
AND COLUMN_NAME = 'visibility_note';

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `categories` ADD COLUMN `visibility_note` varchar(255) DEFAULT NULL COMMENT 'Admin note about visibility restrictions''',
    'SELECT "Visibility_note column already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index if it doesn't exist
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'categories'
AND INDEX_NAME = 'idx_visibility';

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `categories` ADD INDEX `idx_visibility` (`visibility`)',
    'SELECT "Visibility index already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show current table structure
SELECT 'Current categories table structure:' as info;
DESCRIBE categories;