-- Bug Reports Table
-- Stores bug reports submitted through the bug report system

CREATE TABLE IF NOT EXISTS `bug_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Brief title of the bug',
  `description` text NOT NULL COMMENT 'Detailed description of the bug',
  `page_url` varchar(500) NOT NULL COMMENT 'URL where the bug was found',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium' COMMENT 'Bug priority level',
  `steps_to_reproduce` text DEFAULT NULL COMMENT 'Detailed steps to reproduce the bug',
  `expected_behavior` text DEFAULT NULL COMMENT 'What should have happened',
  `actual_behavior` text DEFAULT NULL COMMENT 'What actually happened',
  `user_id` int(11) NOT NULL COMMENT 'ID of user who reported the bug',
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open' COMMENT 'Current status of the bug',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the bug was reported',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When the bug was last updated',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bug reports submitted through the system';

-- Add foreign key constraint if users table exists
-- ALTER TABLE `bug_reports` ADD CONSTRAINT `fk_bug_reports_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;