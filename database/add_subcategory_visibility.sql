-- Add Subcategory Visibility Controls
-- Extends visibility system to subcategories for work page access control

-- Add visibility control fields to subcategories table
ALTER TABLE `subcategories`
ADD COLUMN `visibility` enum('public','hidden','restricted') NOT NULL DEFAULT 'public' COMMENT 'Subcategory visibility: public (everyone), hidden (only admin), restricted (specific users)',
ADD COLUMN `allowed_users` text DEFAULT NULL COMMENT 'JSON array of user IDs who can see restricted subcategories',
ADD COLUMN `visibility_note` varchar(255) DEFAULT NULL COMMENT 'Admin note about visibility restrictions';

-- Update existing subcategories to be public by default
UPDATE `subcategories` SET `visibility` = 'public' WHERE `visibility` IS NULL OR `visibility` = '';

-- Add index for performance
ALTER TABLE `subcategories` ADD INDEX `idx_visibility` (`visibility`);