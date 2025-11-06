-- Add IT Only Visibility for Categories, Subcategories, and Posts
-- This allows Super Admins to mark content as "Restricted - For IT Only"

-- Modify categories table to add 'it_only' to visibility enum
ALTER TABLE `categories`
MODIFY COLUMN `visibility` enum('public','hidden','restricted','it_only') NOT NULL DEFAULT 'public' COMMENT 'Category visibility: public (everyone), hidden (only admin), restricted (specific users), it_only (Super Admins only)';

-- Modify subcategories table to add 'it_only' to visibility enum
ALTER TABLE `subcategories`
MODIFY COLUMN `visibility` enum('public','hidden','restricted','it_only') NOT NULL DEFAULT 'public' COMMENT 'Subcategory visibility: public (everyone), hidden (only admin), restricted (specific users), it_only (Super Admins only)';

-- Modify posts table to add 'it_only' to privacy enum
ALTER TABLE `posts`
MODIFY COLUMN `privacy` enum('public','private','shared','it_only') NOT NULL DEFAULT 'public' COMMENT 'Post privacy: public (everyone), private (author only), shared (specific users), it_only (Super Admins only)';

-- Add index for it_only filtering if not already exists
ALTER TABLE `categories` ADD INDEX `idx_visibility_it_only` (`visibility`) USING BTREE;
ALTER TABLE `subcategories` ADD INDEX `idx_visibility_it_only` (`visibility`) USING BTREE;
ALTER TABLE `posts` ADD INDEX `idx_privacy_it_only` (`privacy`) USING BTREE;