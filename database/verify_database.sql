-- Database Verification Script - Check your current setup
-- Run this in phpMyAdmin SQL tab

-- Step 1: List all tables
SHOW TABLES;

-- Step 2: Check each table structure
DESCRIBE categories;
DESCRIBE subcategories;
DESCRIBE posts;
DESCRIBE replies;
DESCRIBE files;

-- Step 3: Check total records in each table
SELECT 'Categories' as table_name, COUNT(*) as total_records FROM categories
UNION ALL
SELECT 'Subcategories', COUNT(*) FROM subcategories
UNION ALL
SELECT 'Posts', COUNT(*) FROM posts
UNION ALL
SELECT 'Replies', COUNT(*) FROM replies
UNION ALL
SELECT 'Files', COUNT(*) FROM files;

-- Step 4: Check privacy settings in posts (if column exists)
SELECT 'Privacy Distribution' as info,
       COUNT(*) as total_posts,
       SUM(CASE WHEN privacy = 'public' THEN 1 ELSE 0 END) as public_posts,
       SUM(CASE WHEN privacy = 'private' THEN 1 ELSE 0 END) as private_posts,
       SUM(CASE WHEN privacy = 'shared' THEN 1 ELSE 0 END) as shared_posts
FROM posts;

-- Step 5: Check user_id distribution (if columns exist)
SELECT 'User Distribution' as info,
       COUNT(*) as total,
       SUM(CASE WHEN user_id = 1 THEN 1 ELSE 0 END) as admin_posts
FROM posts
WHERE user_id IS NOT NULL;