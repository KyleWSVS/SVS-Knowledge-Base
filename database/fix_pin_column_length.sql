-- Fix PIN Column Length
-- Updated: 2025-11-05
-- Issue: PIN column is VARCHAR(4) but bcrypt hashes are 60 characters
-- This causes PIN verification to fail because hashes get truncated

-- Increase the pin column to accommodate full bcrypt hashes (60 characters)
ALTER TABLE users MODIFY COLUMN pin VARCHAR(255);

-- Verify the change
DESCRIBE users;

-- Test: After running this migration, PIN resets should work correctly
-- because full bcrypt hashes will be stored without truncation