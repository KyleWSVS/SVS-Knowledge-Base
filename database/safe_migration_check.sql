-- Safe Migration Script - Check what you have and add missing columns
-- Run each section separately in phpMyAdmin

-- Step 1: Check Categories table
DESCRIBE categories;

-- Step 2: Add user_id to categories if missing (run this if user_id column doesn't exist)
ALTER TABLE categories ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id;

-- Step 3: Check Subcategories table
DESCRIBE subcategories;

-- Step 4: Add user_id to subcategories if missing
ALTER TABLE subcategories ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER category_id;

-- Step 5: Check Posts table
DESCRIBE posts;

-- Step 6: Add missing columns to posts if they don't exist
ALTER TABLE posts ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER subcategory_id;
ALTER TABLE posts ADD COLUMN privacy ENUM('public','private','shared') NOT NULL DEFAULT 'public' AFTER content;
ALTER TABLE posts ADD COLUMN shared_with TEXT NULL DEFAULT NULL AFTER privacy;

-- Step 7: Check Replies table
DESCRIBE replies;

-- Step 8: Add user_id to replies if missing
ALTER TABLE replies ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER post_id;

-- Step 9: Update any existing records to belong to Admin Kyle
UPDATE categories SET user_id = 1 WHERE user_id = 0 OR user_id IS NULL;
UPDATE subcategories SET user_id = 1 WHERE user_id = 0 OR user_id IS NULL;
UPDATE posts SET user_id = 1 WHERE user_id = 0 OR user_id IS NULL;
UPDATE replies SET user_id = 1 WHERE user_id = 0 OR user_id IS NULL;

-- Step 10: Verify the results
SELECT 'Categories' as table_type, COUNT(*) as total FROM categories
UNION ALL
SELECT 'Subcategories', COUNT(*) FROM subcategories
UNION ALL
SELECT 'Posts', COUNT(*) FROM posts
UNION ALL
SELECT 'Replies', COUNT(*) FROM replies;