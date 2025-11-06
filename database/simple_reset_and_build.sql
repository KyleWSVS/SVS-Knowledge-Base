-- =====================================================
-- Work Knowledge Base - Simple Database Reset and Build
-- =====================================================
-- Database: if0_40307645_knowledgebase
-- Run this entire script in phpMyAdmin SQL tab
-- =====================================================

-- Step 1: Drop existing tables (in reverse dependency order)
DROP TABLE IF EXISTS `files`;
DROP TABLE IF EXISTS `replies`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `subcategories`;
DROP TABLE IF EXISTS `categories`;

-- Step 2: Create Categories Table
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create Subcategories Table
CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_subcategories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Create Posts Table (with User and Privacy Support)
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subcategory_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `title` varchar(500) NOT NULL,
  `content` text NOT NULL,
  `privacy` enum('public','private','shared') NOT NULL DEFAULT 'public',
  `shared_with` text DEFAULT NULL COMMENT 'JSON array of user IDs',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `edited` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_subcategory_id` (`subcategory_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_privacy` (`privacy`),
  KEY `idx_created_at` (`created_at`),
  FULLTEXT KEY `ft_title_content` (`title`,`content`),
  CONSTRAINT `fk_posts_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Create Replies Table (with User Support)
CREATE TABLE `replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `edited` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_replies_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Create Files Table
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) DEFAULT NULL,
  `reply_id` int(11) DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_reply_id` (`reply_id`),
  CONSTRAINT `fk_files_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_files_reply` FOREIGN KEY (`reply_id`) REFERENCES `replies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 7: Insert Sample Categories
INSERT INTO `categories` (`id`, `name`, `icon`) VALUES
(1, 'Technical Documentation', '📚'),
(2, 'Company Policies', '📋'),
(3, 'Project Management', '📊'),
(4, 'Training Materials', '🎓'),
(5, 'IT Support', '💻');

-- Step 8: Insert Sample Subcategories
INSERT INTO `subcategories` (`id`, `category_id`, `name`) VALUES
(1, 1, 'API Documentation'),
(2, 1, 'Database Schemas'),
(3, 1, 'Code Standards'),
(4, 2, 'HR Policies'),
(5, 2, 'Security Policies'),
(6, 2, 'Work Procedures'),
(7, 3, 'Active Projects'),
(8, 3, 'Completed Projects'),
(9, 3, 'Project Templates'),
(10, 4, 'New Employee Onboarding'),
(11, 4, 'Software Training'),
(12, 4, 'Safety Training'),
(13, 5, 'Troubleshooting Guides'),
(14, 5, 'Hardware Setup'),
(15, 5, 'Software Installation');

-- Step 9: Insert Sample Posts for Testing Privacy System
INSERT INTO `posts` (`id`, `subcategory_id`, `user_id`, `title`, `content`, `privacy`, `shared_with`) VALUES
-- Public posts (everyone can see)
(1, 1, 1, 'API Authentication Guide', 'This guide explains how to use our API authentication system with JWT tokens and API keys.', 'public', NULL),
(2, 4, 1, 'Employee Handbook Overview', 'Key points from the employee handbook that all staff should know.', 'public', NULL),
(3, 13, 2, 'Common Printer Issues', 'Troubleshooting steps for the most common printer problems in the office.', 'public', NULL),

-- Private posts (only authors can see)
(4, 2, 1, 'Admin Database Credentials', 'Confidential database access information for system administrators only.', 'private', NULL),
(5, 7, 2, 'Cody\'s Project Notes', 'Personal notes and progress updates for Cody\'s current project.', 'private', NULL),
(6, 10, 3, 'Deegan\'s Training Draft', 'Draft training materials that Deegan is currently working on.', 'private', NULL),

-- Shared posts (only selected users can see)
(7, 3, 1, 'Code Review Standards', 'Internal coding standards that should be followed during code reviews. Shared with Cody and Deegan.', 'shared', '[2,3]'),
(8, 8, 2, 'Project Completion Report', 'Details about a recently completed project that needs to be shared with Admin Kyle only.', 'shared', '[1]'),
(9, 14, 3, 'Hardware Setup Guide', 'Step-by-step guide for setting up new workstations. Shared with Admin Kyle.', 'shared', '[1]');

-- Step 10: Insert Sample Replies
INSERT INTO `replies` (`post_id`, `user_id`, `content`) VALUES
-- Replies to public posts
(1, 2, 'Great guide! The examples really helped me understand the authentication flow.'),
(1, 3, 'I added this to my bookmarks. Very useful documentation.'),
(3, 1, 'Thanks for creating this Cody. It\'s helped resolve several support tickets.'),

-- Replies to shared posts (only visible to shared users)
(7, 2, 'I\'ve updated my team to follow these standards. The code quality has improved.'),
(7, 3, 'Can we add a section about security best practices?'),
(8, 1, 'Excellent work on this project Cody. The client was very happy with the results.');

-- Step 11: Set database timezone to Auckland
SET time_zone = '+13:00';

-- =====================================================
-- SCRIPT COMPLETE - Database is ready!
-- =====================================================