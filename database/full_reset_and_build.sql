-- =====================================================
-- Work Knowledge Base - Full Database Reset and Build
-- =====================================================
--
-- This script will:
-- 1. Drop all existing tables (IF EXISTS)
-- 2. Create all tables with correct structure
-- 3. Set proper indexes and foreign keys
-- 4. Ready for use with the multi-user privacy system
--
-- Database: if0_40307645_knowledgebase
-- Timezone: Pacific/Auckland (+13:00)
-- Users: Admin Kyle, Cody Kirsten, Deegan Begovich
-- =====================================================

-- Drop all existing tables (in reverse order of dependencies)
DROP TABLE IF EXISTS `files`;
DROP TABLE IF EXISTS `replies`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `subcategories`;
DROP TABLE IF EXISTS `categories`;

-- =====================================================
-- Create Categories Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(50) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Create Subcategories Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `subcategories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_category_id` (`category_id`),
  CONSTRAINT `fk_subcategories_category`
    FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Create Posts Table (with User and Privacy Support)
-- =====================================================
CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `subcategory_id` INT NOT NULL,
  `user_id` INT NOT NULL DEFAULT 1,
  `title` VARCHAR(500) NOT NULL,
  `content` TEXT NOT NULL,
  `privacy` ENUM('public', 'private', 'shared') NOT NULL DEFAULT 'public',
  `shared_with` TEXT NULL DEFAULT NULL COMMENT 'JSON array of user IDs',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `edited` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_subcategory_id` (`subcategory_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_privacy` (`privacy`),
  INDEX `idx_created_at` (`created_at`),
  FULLTEXT INDEX `ft_title_content` (`title`, `content`),
  CONSTRAINT `fk_posts_subcategory`
    FOREIGN KEY (`subcategory_id`)
    REFERENCES `subcategories` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Create Replies Table (with User Support)
-- =====================================================
CREATE TABLE IF NOT EXISTS `replies` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `post_id` INT NOT NULL,
  `user_id` INT NOT NULL DEFAULT 1,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `edited` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_post_id` (`post_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_replies_post`
    FOREIGN KEY (`post_id`)
    REFERENCES `posts` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Create Files Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `files` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `post_id` INT NULL DEFAULT NULL,
  `reply_id` INT NULL DEFAULT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT NOT NULL,
  `file_type` VARCHAR(100) NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_post_id` (`post_id`),
  INDEX `idx_reply_id` (`reply_id`),
  CONSTRAINT `fk_files_post`
    FOREIGN KEY (`post_id`)
    REFERENCES `posts` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_files_reply`
    FOREIGN KEY (`reply_id`)
    REFERENCES `replies` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert Sample Categories (for testing)
-- =====================================================
INSERT INTO `categories` (`id`, `name`, `icon`) VALUES
(1, 'Technical Documentation', '📚'),
(2, 'Company Policies', '📋'),
(3, 'Project Management', '📊'),
(4, 'Training Materials', '🎓'),
(5, 'IT Support', '💻');

-- =====================================================
-- Insert Sample Subcategories (for testing)
-- =====================================================
INSERT INTO `subcategories` (`id`, `category_id`, `name`) VALUES
-- Technical Documentation
(1, 1, 'API Documentation'),
(2, 1, 'Database Schemas'),
(3, 1, 'Code Standards'),
-- Company Policies
(4, 2, 'HR Policies'),
(5, 2, 'Security Policies'),
(6, 2, 'Work Procedures'),
-- Project Management
(7, 3, 'Active Projects'),
(8, 3, 'Completed Projects'),
(9, 3, 'Project Templates'),
-- Training Materials
(10, 4, 'New Employee Onboarding'),
(11, 4, 'Software Training'),
(12, 4, 'Safety Training'),
-- IT Support
(13, 5, 'Troubleshooting Guides'),
(14, 5, 'Hardware Setup'),
(15, 5, 'Software Installation');

-- =====================================================
-- Create Sample Posts (for testing privacy system)
-- =====================================================
INSERT INTO `posts` (`id`, `subcategory_id`, `user_id`, `title`, `content`, `privacy`, `shared_with`) VALUES
-- Public posts (everyone can see)
(1, 1, 1, 'API Authentication Guide', 'This guide explains how to use our API authentication system with JWT tokens and API keys.', 'public', NULL),
(2, 4, 1, 'Employee Handbook Overview', 'Key points from the employee handbook that all staff should know.', 'public', NULL),
(3, 13, 2, 'Common Printer Issues', 'Troubleshooting steps for the most common printer problems in the office.', 'public', NULL),

-- Private posts (only authors can see)
(4, 2, 1, 'Admin Database Credentials', 'Confidential database access information for system administrators only.', 'private', NULL),
(5, 7, 2, 'Cody''s Project Notes', 'Personal notes and progress updates for Cody''s current project.', 'private', NULL),
(6, 10, 3, 'Deegan''s Training Draft', 'Draft training materials that Deegan is currently working on.', 'private', NULL),

-- Shared posts (only selected users can see)
(7, 3, 1, 'Code Review Standards', 'Internal coding standards that should be followed during code reviews. Shared with Cody and Deegan.', 'shared', '[2,3]'),
(8, 8, 2, 'Project Completion Report', 'Details about a recently completed project that needs to be shared with Admin Kyle only.', 'shared', '[1]'),
(9, 14, 3, 'Hardware Setup Guide', 'Step-by-step guide for setting up new workstations. Shared with Admin Kyle.', 'shared', '[1]');

-- =====================================================
-- Create Sample Replies (for testing)
-- =====================================================
INSERT INTO `replies` (`post_id`, `user_id`, `content`) VALUES
-- Replies to public posts
(1, 2, 'Great guide! The examples really helped me understand the authentication flow.'),
(1, 3, 'I added this to my bookmarks. Very useful documentation.'),
(3, 1, 'Thanks for creating this Cody. It''s helped resolve several support tickets.'),

-- Replies to shared posts (only visible to shared users)
(7, 2, 'I''ve updated my team to follow these standards. The code quality has improved.'),
(7, 3, 'Can we add a section about security best practices?'),
(8, 1, 'Excellent work on this project Cody. The client was very happy with the results.');

-- =====================================================
-- Database Configuration Verification
-- =====================================================

-- Set the database timezone to Auckland (NZST/NZDT)
SET time_zone = '+13:00';

-- Display current database time (should be Auckland time)
SELECT NOW() AS 'Current Database Time (Auckland)';

-- Verify tables were created correctly
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    AUTO_INCREMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'if0_40307645_knowledgebase'
ORDER BY TABLE_NAME;

-- =====================================================
-- Summary
-- =====================================================
SELECT
    'Database Setup Complete!' AS Status,
    COUNT(*) AS Total_Categories
FROM categories
UNION ALL
SELECT
    'Subcategories Created',
    COUNT(*)
FROM subcategories
UNION ALL
SELECT
    'Sample Posts Created',
    COUNT(*)
FROM posts
UNION ALL
SELECT
    'Sample Replies Created',
    COUNT(*)
FROM replies;

-- =====================================================
-- User Reference for Testing
-- =====================================================
SELECT
    'User Reference' AS Information,
    'PIN 7982 = Admin Kyle (Blue)' AS Details
UNION ALL
SELECT '', 'PIN 1234 = Cody Kirsten (Purple)'
UNION ALL
SELECT '', 'PIN 5678 = Deegan Begovich (Red)'
UNION ALL
SELECT '', 'All times are in Auckland timezone (GMT+13)';

-- =====================================================
-- END OF SCRIPT
-- =====================================================