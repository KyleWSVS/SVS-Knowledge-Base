-- Users Table
-- Creates a database-driven user system to replace hardcoded users

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `pin` varchar(4) NOT NULL,
  `color` varchar(7) DEFAULT '#4A90E2',
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pin` (`pin`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert existing hardcoded users
-- Kyle Walker - Super Admin (cannot be edited/deleted via interface)
INSERT INTO `users` (`id`, `name`, `pin`, `color`, `role`, `is_active`) VALUES
(1, 'Kyle Walker', '7982', '#4A90E2', 'admin', 1),
(2, 'Cody Kirsten', '1234', '#7B68EE', 'user', 1),
(3, 'Deegan Begovich', '5678', '#E74C3C', 'user', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `pin` = VALUES(`pin`),
  `color` = VALUES(`color`),
  `role` = VALUES(`role`),
  `is_active` = VALUES(`is_active`);

-- Add indexes for better performance on user-related queries
CREATE INDEX `idx_users_name` ON `users` (`name`);
CREATE INDEX `idx_users_last_login` ON `users` (`last_login`);