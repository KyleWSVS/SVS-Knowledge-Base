-- Migration: Add user_id to categories and subcategories tables
-- Run this if you have existing categories/subcategories

-- Add user_id to categories
ALTER TABLE categories
ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id,
ADD INDEX idx_user_id (user_id);

-- Add user_id to subcategories
ALTER TABLE subcategories
ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER category_id,
ADD INDEX idx_user_id (user_id);

-- Set all existing categories to belong to Admin Kyle (user_id = 1)
UPDATE categories SET user_id = 1 WHERE user_id = 0;

-- Set all existing subcategories to belong to Admin Kyle (user_id = 1)
UPDATE subcategories SET user_id = 1 WHERE user_id = 0;