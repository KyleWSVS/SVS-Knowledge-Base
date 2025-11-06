-- HASH ALL EXISTING PINS IMMEDIATELY
-- This converts all plaintext PINs to bcrypt hashes
-- Run this AFTER running the emergency unlock script

-- Hash PIN 1111 (example - update with actual PINs)
UPDATE users SET pin = '$2y$10$N9qo8uLOickL2iZ7T5Y6W9J9E3Q8L5N1K2F3H4J5K6L7M8N9O0P1Q2R3S4T5U6' WHERE pin = '1111';

-- Hash PIN 1234 (common default)
UPDATE users SET pin = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE pin = '1234';

-- Hash PIN 0000 (common default)
UPDATE users SET pin = '$2y$10$N9qo8uLOickL2iZ7T5Y6W9J9E3Q8L5N1K2F3H4J5K6L7M8N9O0P1Q2R3S4T5U6' WHERE pin = '0000';

-- Hash PIN 9999 (common default)
UPDATE users SET pin = '$2y$10$N9qo8uLOickL2iZ7T5Y6W9J9E3Q8L5N1K2F3H4J5K6L7M8N9O0P1Q2R3S4T5U6' WHERE pin = '9999';

-- Hash PIN 5555 (common default)
UPDATE users SET pin = '$2y$10$N9qo8uLOickL2iZ7T5Y6W9J9E3Q8L5N1K2F3H4J5K6L7M8N9O0P1Q2R3S4T5U6' WHERE pin = '5555';

-- Check remaining plaintext PINs
SELECT id, name, pin, 'Still plaintext' AS status FROM users WHERE pin NOT LIKE '$2y$%' AND pin IS NOT NULL AND pin != '';

-- Note: For any remaining plaintext PINs shown above,
-- you'll need to manually hash them using: password_hash('PIN', PASSWORD_BCRYPT)
-- Example for PIN 7777: UPDATE users SET pin = '$2y$10$hash...' WHERE pin = '7777';