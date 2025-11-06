-- Create user_pinned_categories table for personalized category pinning
-- Run this script in phpMyAdmin or your MySQL client
-- This allows users to pin/favorite categories for quick access on the home page

CREATE TABLE IF NOT EXISTS `user_pinned_categories` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL,
  `category_id` int NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_category` (`user_id`, `category_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Success! The table has been created.
-- Users can now pin/unpin categories from the index page.
-- Pinned categories will appear at the top of the list in pin order.
-- Each user's pins are stored separately and private to that user.
