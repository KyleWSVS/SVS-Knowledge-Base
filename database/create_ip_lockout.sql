-- CREATE IP-BASED LOCKOUT SYSTEM
-- This tracks failed login attempts by IP address instead of by user
-- Only the attacking IP gets locked, not all users

-- Create table for tracking IP-based failed attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int DEFAULT 1,
  `first_attempt` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_attempt` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `locked_until` timestamp NULL,
  UNIQUE KEY `unique_ip` (`ip_address`)
);

-- Create table for IP lockouts
CREATE TABLE IF NOT EXISTS `ip_lockouts` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL UNIQUE,
  `attempts` int DEFAULT 1,
  `locked_until` timestamp NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Index for fast lookups
CREATE INDEX IF NOT EXISTS `idx_ip_attempts` ON `login_attempts` (`ip_address`);
CREATE INDEX IF NOT EXISTS `idx_ip_lockouts` ON `ip_lockouts` (`ip_address`);
CREATE INDEX IF NOT EXISTS `idx_locked_ips` ON `login_attempts` (`locked_until`);

-- This system will:
-- 1. Track failed login attempts per IP address
-- 2. Lock only the attacking IP after 10 attempts
-- 3. Allow legitimate users to login normally
-- 4. Auto-unlock IPs after 2 minutes
-- 5. Reset counters on successful login