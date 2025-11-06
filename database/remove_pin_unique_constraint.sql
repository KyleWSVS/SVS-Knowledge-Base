-- Remove Unique Constraint from PIN field
-- Hashed PINs should not have unique constraints since multiple users can have the same PIN
-- This will fix the "Duplicate entry" error when hashing PINs

-- Step 1: Remove the unique constraint if it exists
ALTER TABLE `users` DROP INDEX IF EXISTS `unique_pin`;
ALTER TABLE `users` DROP INDEX IF EXISTS `pin`;

-- Step 2: Check if the constraint was removed
SELECT
    'Unique PIN constraint check' AS check_type,
    IF(COUNT(*) = 0, '✓ No unique PIN constraints found', '✗ Unique PIN constraints still exist') AS status
FROM information_schema.STATISTICS
WHERE TABLE_NAME = 'users'
AND INDEX_NAME = 'unique_pin';

-- Step 3: Show current user PINs for verification
SELECT id, name,
       CASE
           WHEN pin LIKE '$2y$%' THEN '✓ Hashed'
           ELSE '✗ Plaintext'
       END AS pin_status,
       LEFT(pin, 20) AS pin_sample
FROM users
ORDER BY id;

-- Note: After removing the constraint, you can now reset PINs for multiple users
-- without getting the "Duplicate entry" error