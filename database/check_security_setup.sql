-- Check Login Security Setup
-- Run this to verify everything is set up correctly

-- Check 1: Are PINs hashed? (Hashed PINs start with $2y$)
-- Shows how many users have hashed vs plaintext PINs
SELECT
    CONCAT(
        'Total Users: ', COUNT(*), ' | ',
        'Hashed (Bcrypt): ', SUM(CASE WHEN pin LIKE '$2y$%' THEN 1 ELSE 0 END), ' | ',
        'Plaintext: ', SUM(CASE WHEN pin NOT LIKE '$2y$%' THEN 1 ELSE 0 END)
    ) AS 'PIN Status'
FROM users;

-- Check 2: Sample of user data with security columns
-- Shows current state of each user
SELECT
    id,
    name,
    CASE
        WHEN pin LIKE '$2y$%' THEN '✓ Hashed'
        ELSE '✗ Plaintext'
    END AS pin_status,
    COALESCE(failed_attempts, 0) AS failed_attempts,
    CASE
        WHEN locked_until IS NULL THEN 'Not locked'
        WHEN locked_until > NOW() THEN CONCAT('Locked until ', locked_until)
        ELSE 'Lock expired'
    END AS lock_status,
    is_active
FROM users
ORDER BY id;
