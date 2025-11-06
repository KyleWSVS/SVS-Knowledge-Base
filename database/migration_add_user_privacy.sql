-- Migration Script: Add User and Privacy Support
-- Run this script on your existing database to add the new functionality

-- Add user_id column to posts table
ALTER TABLE posts
ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER subcategory_id,
ADD COLUMN privacy ENUM('public', 'private', 'shared') NOT NULL DEFAULT 'public' AFTER content,
ADD COLUMN shared_with TEXT NULL DEFAULT NULL AFTER privacy;

-- Add user_id column to replies table
ALTER TABLE replies
ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER post_id;

-- Add indexes for better performance
ALTER TABLE posts ADD INDEX idx_user_id (user_id);
ALTER TABLE posts ADD INDEX idx_privacy (privacy);
ALTER TABLE replies ADD INDEX idx_user_id (user_id);

-- Update existing posts to belong to user 1 (Admin)
UPDATE posts SET user_id = 1 WHERE user_id = 0;

-- Update existing replies to belong to user 1 (Admin)
UPDATE replies SET user_id = 1 WHERE user_id = 0;

-- Set existing posts to public by default
UPDATE posts SET privacy = 'public' WHERE privacy IS NULL OR privacy = '';

-- Optional: Add comment to explain shared_with field
ALTER TABLE posts MODIFY COLUMN shared_with TEXT NULL DEFAULT NULL COMMENT 'JSON array of user IDs';