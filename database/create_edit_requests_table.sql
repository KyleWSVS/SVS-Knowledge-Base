-- Edit Requests Table
-- Stores user-submitted edit requests for categories and subcategories
-- Normal users can suggest changes, admins can approve or decline

CREATE TABLE IF NOT EXISTS `edit_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_type` enum('category','subcategory') NOT NULL,
  `item_id` int(11) NOT NULL,
  `current_name` varchar(255) NOT NULL,
  `requested_name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_type`, `item_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX `idx_edit_requests_status_date` ON `edit_requests` (`status`, `created_at`);
CREATE INDEX `idx_edit_requests_type_status` ON `edit_requests` (`item_type`, `status`);