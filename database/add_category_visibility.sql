-- Add Category Visibility Controls
-- Allows super users to hide categories from specific users or everyone

-- Add visibility control fields to categories table
ALTER TABLE `categories`
ADD COLUMN `visibility` enum('public','hidden','restricted') NOT NULL DEFAULT 'public' COMMENT 'Category visibility: public (everyone), hidden (only admin), restricted (specific users)',
ADD COLUMN `allowed_users` text DEFAULT NULL COMMENT 'JSON array of user IDs who can see restricted categories',
ADD COLUMN `visibility_note` varchar(255) DEFAULT NULL COMMENT 'Admin note about visibility restrictions';

-- Update existing categories to be public by default
UPDATE `categories` SET `visibility` = 'public' WHERE `visibility` IS NULL OR `visibility` = '';

-- Add index for performance
ALTER TABLE `categories` ADD INDEX `idx_visibility` (`visibility`);