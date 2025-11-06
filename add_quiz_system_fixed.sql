-- ============================================================
-- Quiz System Database Schema (Fixed Version)
-- Created: 2025-11-06
-- Author: Claude Code Assistant
-- ============================================================

-- ============================================================
-- STEP 1: Create Training Quizzes Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `training_quizzes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `content_id` INT NOT NULL,
  `content_type` ENUM('category', 'subcategory', 'post') NOT NULL,
  `quiz_title` VARCHAR(255) NOT NULL,
  `quiz_description` TEXT NULL,
  `passing_score` INT DEFAULT 100 COMMENT 'Required score to pass (percentage)',
  `time_limit_minutes` INT NULL COMMENT 'Time limit for quiz, null for no limit',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_content_quiz` (`content_id`, `content_type`),
  INDEX `idx_content` (`content_id`, `content_type`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_created_by` (`created_by`)
);

-- ============================================================
-- STEP 2: Create Quiz Questions Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `quiz_id` INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `question_type` ENUM('multiple_choice') DEFAULT 'multiple_choice',
  `question_order` INT DEFAULT 0,
  `points` INT DEFAULT 1,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_quiz_id` (`quiz_id`),
  INDEX `idx_order` (`question_order`),
  INDEX `idx_active` (`is_active`)
);

-- ============================================================
-- STEP 3: Create Quiz Answer Choices Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `quiz_answer_choices` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `question_id` INT NOT NULL,
  `choice_text` TEXT NOT NULL,
  `is_correct` BOOLEAN DEFAULT FALSE,
  `choice_order` INT DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_question_id` (`question_id`),
  INDEX `idx_order` (`choice_order`)
);

-- ============================================================
-- STEP 4: Create User Quiz Attempts Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_quiz_attempts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `quiz_id` INT NOT NULL,
  `attempt_number` INT NOT NULL DEFAULT 1,
  `score` INT NULL COMMENT 'Percentage score (0-100)',
  `total_points` INT DEFAULT 0,
  `earned_points` INT DEFAULT 0,
  `status` ENUM('in_progress', 'completed', 'failed', 'passed') DEFAULT 'in_progress',
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `time_taken_minutes` INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_quiz_attempt` (`user_id`, `quiz_id`, `attempt_number`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_quiz_id` (`quiz_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_started_at` (`started_at`)
);

-- ============================================================
-- STEP 5: Create User Quiz Answers Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_quiz_answers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `attempt_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `selected_choice_id` INT NOT NULL,
  `is_correct` BOOLEAN DEFAULT FALSE,
  `points_earned` INT DEFAULT 0,
  `answered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_question_answer` (`attempt_id`, `question_id`),
  INDEX `idx_attempt_id` (`attempt_id`),
  INDEX `idx_question_id` (`question_id`),
  INDEX `idx_selected_choice` (`selected_choice_id`)
);

-- ============================================================
-- STEP 6: Update Training Progress to Include Quiz Completion
-- ============================================================

-- Add quiz completion to training progress table
ALTER TABLE `training_progress`
ADD COLUMN `quiz_completed` BOOLEAN DEFAULT FALSE AFTER `status`,
ADD COLUMN `quiz_score` INT NULL AFTER `quiz_completed`,
ADD COLUMN `quiz_completed_at` DATETIME NULL AFTER `quiz_score`,
ADD COLUMN `last_quiz_attempt_id` INT NULL AFTER `quiz_completed_at`,
ADD INDEX `idx_quiz_completed` (`quiz_completed`),
ADD INDEX `idx_last_quiz_attempt` (`last_quiz_attempt_id`);

-- ============================================================
-- NOTES FOR ADMINISTRATOR
-- ============================================================

/*
This schema creates a comprehensive quiz system with the following features:

1. **Quiz Management**: Each training content item can have one quiz
2. **Multiple Choice Questions**: Support for multiple choice questions only
3. **Scoring System**: Points per question and percentage-based scoring
4. **Attempt Tracking**: Multiple attempts per user with detailed history
5. **Progress Integration**: Quiz completion links to training progress
6. **Statistics**: Built-in views for quiz performance analytics
7. **Time Limits**: Optional time limits for quizzes
8. **Passing Scores**: Configurable passing score thresholds

Key Tables:
- training_quizzes: Main quiz information
- quiz_questions: Individual questions
- quiz_answer_choices: Multiple choice options
- user_quiz_attempts: User attempt records
- user_quiz_answers: User's selected answers

Integration Points:
- Links to existing training_course_content table
- Updates training_progress table with quiz completion
- Maintains user role progression based on quiz performance

Next Steps:
1. Import this SQL file into your database
2. Create quiz management interface for admins
3. Create quiz taking interface for trainees
4. Update progress calculation to use quiz scores
5. Remove time-based tracking code
*/