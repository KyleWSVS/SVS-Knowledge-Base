-- ============================================================
-- Quiz System Database Schema
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
  INDEX `idx_created_by` (`created_by`),
  CONSTRAINT `fk_training_quizzes_content`
    FOREIGN KEY (`content_type`, `content_id`) REFERENCES `training_course_content` (`content_type`, `content_id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_training_quizzes_creator`
    FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
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
  INDEX `idx_active` (`is_active`),
  CONSTRAINT `fk_quiz_questions_quiz`
    FOREIGN KEY (`quiz_id`)
    REFERENCES `training_quizzes` (`id`)
    ON DELETE CASCADE
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
  INDEX `idx_order` (`choice_order`),
  CONSTRAINT `fk_quiz_answer_choices_question`
    FOREIGN KEY (`question_id`)
    REFERENCES `quiz_questions` (`id`)
    ON DELETE CASCADE
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
  `PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_quiz_attempt` (`user_id`, `quiz_id`, `attempt_number`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_quiz_id` (`quiz_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_started_at` (`started_at`),
  CONSTRAINT `fk_user_quiz_attempts_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_user_quiz_attempts_quiz`
    FOREIGN KEY (`quiz_id`)
    REFERENCES `training_quizzes` (`id`)
    ON DELETE CASCADE
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
  `PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_question_answer` (`attempt_id`, `question_id`),
  INDEX `idx_attempt_id` (`attempt_id`),
  INDEX `idx_question_id` (`question_id`),
  INDEX `idx_selected_choice` (`selected_choice_id`),
  CONSTRAINT `fk_user_quiz_answers_attempt`
    FOREIGN KEY (`attempt_id`)
    REFERENCES `user_quiz_attempts` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_user_quiz_answers_question`
    FOREIGN KEY (`question_id`)
    REFERENCES `quiz_questions` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_user_quiz_answers_choice`
    FOREIGN KEY (`selected_choice_id`)
    REFERENCES `quiz_answer_choices` (`id`)
    ON DELETE CASCADE
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

-- Add foreign key constraint for quiz attempt
ALTER TABLE `training_progress`
ADD CONSTRAINT `fk_training_progress_quiz_attempt`
  FOREIGN KEY (`last_quiz_attempt_id`)
  REFERENCES `user_quiz_attempts` (`id`)
  ON DELETE SET NULL;

-- ============================================================
-- STEP 7: Create Views for Easy Quiz Management
-- ============================================================

-- View for quiz statistics
CREATE OR REPLACE VIEW `quiz_statistics` AS
SELECT
    tq.id,
    tq.content_id,
    tq.content_type,
    tq.quiz_title,
    tq.passing_score,
    COUNT(DISTINCT qq.id) as total_questions,
    COUNT(DISTINCT CASE WHEN uqa.status = 'passed' THEN uqa.user_id END) as users_passed,
    COUNT(DISTINCT CASE WHEN uqa.status = 'failed' THEN uqa.user_id END) as users_failed,
    COUNT(DISTINCT uqa.user_id) as total_users_attempted,
    AVG(CASE WHEN uqa.status = 'completed' THEN uqa.score END) as average_score,
    MAX(uqa.score) as highest_score,
    MIN(uqa.score) as lowest_score
FROM training_quizzes tq
LEFT JOIN quiz_questions qq ON tq.id = qq.quiz_id AND qq.is_active = TRUE
LEFT JOIN user_quiz_attempts uqa ON tq.id = uqa.quiz_id AND uqa.status IN ('passed', 'failed')
WHERE tq.is_active = TRUE
GROUP BY tq.id, tq.content_id, tq.content_type, tq.quiz_title, tq.passing_score;

-- View for user quiz history
CREATE OR REPLACE VIEW `user_quiz_history` AS
SELECT
    u.id as user_id,
    u.name as user_name,
    tq.quiz_title,
    tcc.content_type,
    tcc.content_id,
    CASE tcc.content_type
        WHEN 'category' THEN c.name
        WHEN 'subcategory' THEN sc.name
        WHEN 'post' THEN p.title
    END as content_name,
    uqa.attempt_number,
    uqa.score,
    uqa.status,
    uqa.started_at,
    uqa.completed_at,
    uqa.time_taken_minutes,
    CASE
        WHEN uqa.status = 'passed' THEN '✅ Passed'
        WHEN uqa.status = 'failed' THEN '❌ Failed'
        WHEN uqa.status = 'in_progress' THEN '⏳ In Progress'
        ELSE uqa.status
    END as status_display
FROM users u
JOIN user_quiz_attempts uqa ON u.id = uqa.user_id
JOIN training_quizzes tq ON uqa.quiz_id = tq.id
JOIN training_course_content tcc ON tq.content_id = tcc.content_id AND tq.content_type = tcc.content_type
LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
ORDER BY uqa.started_at DESC;

-- ============================================================
-- STEP 8: Insert Sample Quiz Data (Optional)
-- ============================================================

-- Get a sample post ID for demonstration (this would be replaced with actual data)
-- SET @sample_post_id = (SELECT id FROM posts LIMIT 1);
-- SET @sample_admin_id = (SELECT id FROM users WHERE role = 'admin' LIMIT 1);

-- Create a sample quiz if sample data exists
-- INSERT IGNORE INTO training_quizzes (content_id, content_type, quiz_title, quiz_description, passing_score, created_by)
-- VALUES (@sample_post_id, 'post', 'Understanding Post Features', 'Test your knowledge about the features and functionality of posts in this knowledge base.', 80, @sample_admin_id);

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
1. Create quiz management interface for admins
2. Create quiz taking interface for trainees
3. Update progress calculation to use quiz scores
4. Remove time-based tracking code
*/