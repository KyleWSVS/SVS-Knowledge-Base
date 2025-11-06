-- EMERGENCY UNLOCK ALL USERS
-- Run this immediately to unlock all locked accounts

UPDATE users
SET failed_attempts = 0, locked_until = NULL;

SELECT 'SUCCESS: All accounts unlocked and failed attempts reset' AS status;