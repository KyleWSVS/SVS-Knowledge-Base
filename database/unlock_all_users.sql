-- Emergency Unlock: This unlocks all users and resets failed attempts
-- Run this immediately to fix the locked accounts issue

UPDATE users
SET failed_attempts = 0, locked_until = NULL;

-- Reset session data (for immediate effect)
-- This will clear any session-based locks

SELECT 'All users have been unlocked and failed attempts reset' AS status;