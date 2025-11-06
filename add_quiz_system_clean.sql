-- ============================================================
-- Quiz System Database Schema - Clean Version
-- Created: 2025-11-06
-- Author: Claude Code Assistant
-- ============================================================

-- Create Training Quizzes Table
CREATE TABLE IF NOT EXISTS `training_quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `content_type` enum('category','subcategory','post') NOT NULL,
  `quiz_title` varchar(255) NOT NULL,
  `quiz_description` text DEFAULT NULL,
  `passing_score` int(11) DEFAULT 100 COMMENT 'Required score to pass (percentage)',
  `time_limit_minutes` int(11) DEFAULT NULL COMMENT 'Time limit for quiz, null for no limit',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_content_quiz` (`content_id`,`content_type`),
  KEY `idx_content` (`content_id`,`content_type`),
  KEY `idx_active` (`is_active`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Quiz Questions Table
CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice') DEFAULT 'multiple_choice',
  `question_order` int(11) DEFAULT 0,
  `points` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_order` (`question_order`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Quiz Answer Choices Table
CREATE TABLE IF NOT EXISTS `quiz_answer_choices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `choice_order` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_order` (`choice_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create User Quiz Attempts Table
CREATE TABLE IF NOT EXISTS `user_quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `score` int(11) DEFAULT NULL COMMENT 'Percentage score (0-100)',
  `total_points` int(11) DEFAULT 0,
  `earned_points` int(11) DEFAULT 0,
  `status` enum('in_progress','completed','failed','passed') DEFAULT 'in_progress',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `time_taken_minutes` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_quiz_attempt` (`user_id`,`quiz_id`,`attempt_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_status` (`status`),
  KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create User Quiz Answers Table
CREATE TABLE IF NOT EXISTS `user_quiz_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_choice_id` int(11) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `answered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_question_answer` (`attempt_id`,`question_id`),
  KEY `idx_attempt_id` (`attempt_id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_selected_choice` (`selected_choice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update Training Progress Table to Include Quiz Completion
ALTER TABLE `training_progress`
ADD COLUMN `quiz_completed` tinyint(1) DEFAULT 0 COMMENT 'Completed via quiz' AFTER `status`,
ADD COLUMN `quiz_score` int(11) DEFAULT NULL COMMENT 'Last quiz score percentage' AFTER `quiz_completed`,
ADD COLUMN `quiz_completed_at` datetime DEFAULT NULL COMMENT 'When quiz was completed' AFTER `quiz_score`,
ADD COLUMN `last_quiz_attempt_id` int(11) DEFAULT NULL COMMENT 'Reference to last attempt' AFTER `quiz_completed_at`,
ADD INDEX `idx_quiz_completed` (`quiz_completed`),
ADD INDEX `idx_last_quiz_attempt` (`last_quiz_attempt_id`);

-- ============================================================
-- Schema Complete
-- ============================================================

/*
Features Implemented:
1. Quiz Management - Each training content can have one quiz
2. Multiple Choice Questions - Support for multiple choice only
3. Scoring System - Points per question and percentage scoring
4. Attempt Tracking - Multiple attempts with detailed history
5. Progress Integration - Quiz completion links to training progress
6. Time Limits - Optional time limits for quizzes
7. Passing Scores - Configurable passing score thresholds (default 100%)

Usage:
1. Import this SQL file to create quiz tables
2. Use manage_quizzes.php to create quizzes for training content
3. Use manage_quiz_questions.php to add questions to quizzes
4. Training users take quizzes via take_quiz.php
5. Progress is automatically updated when quizzes are passed
6. Header progress bar shows quiz-based completion percentage
*/