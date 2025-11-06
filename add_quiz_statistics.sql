-- ============================================================
-- Quiz Statistics Table - Missing Table Fix
-- Created: 2025-11-06
-- Fixes missing quiz_statistics table error in manage_quizzes.php
-- ============================================================

-- Create Quiz Statistics View for manage_quizzes.php
CREATE TABLE IF NOT EXISTS `quiz_statistics` (
  `quiz_id` int(11) NOT NULL,
  `quiz_title` varchar(255) NOT NULL,
  `total_attempts` int(11) DEFAULT 0,
  `total_users` int(11) DEFAULT 0,
  `average_score` decimal(5,2) DEFAULT 0.00,
  `highest_score` int(11) DEFAULT 0,
  `lowest_score` int(11) DEFAULT 0,
  `pass_rate` decimal(5,2) DEFAULT 0.00,
  `total_questions` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`quiz_id`),
  KEY `idx_attempts` (`total_attempts`),
  KEY `idx_average_score` (`average_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial statistics for existing quizzes
INSERT IGNORE INTO quiz_statistics (
  quiz_id,
  quiz_title,
  total_attempts,
  total_users,
  average_score,
  highest_score,
  lowest_score,
  pass_rate,
  total_questions
)
SELECT
  tq.id,
  tq.quiz_title,
  0 as total_attempts,
  0 as total_users,
  0.00 as average_score,
  0 as highest_score,
  0 as lowest_score,
  0.00 as pass_rate,
  (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = tq.id AND qq.is_active = 1) as total_questions
FROM training_quizzes tq;

-- Create procedure to update quiz statistics
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS update_quiz_statistics(IN p_quiz_id INT)
BEGIN
    -- Update statistics for a specific quiz
    UPDATE quiz_statistics qs
    SET
        total_attempts = (
            SELECT COUNT(*)
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = p_quiz_id
        ),
        total_users = (
            SELECT COUNT(DISTINCT uqa.user_id)
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = p_quiz_id
        ),
        average_score = (
            SELECT COALESCE(AVG(uqa.score), 0)
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = p_quiz_id AND uqa.status IN ('completed', 'passed')
        ),
        highest_score = (
            SELECT COALESCE(MAX(uqa.score), 0)
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = p_quiz_id AND uqa.status IN ('completed', 'passed')
        ),
        lowest_score = (
            SELECT COALESCE(MIN(uqa.score), 0)
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = p_quiz_id AND uqa.status IN ('completed', 'passed')
        ),
        pass_rate = (
            SELECT COALESCE(
                (SUM(CASE WHEN uqa.score >= tq.passing_score THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 0
            )
            FROM user_quiz_attempts uqa
            JOIN training_quizzes tq ON uqa.quiz_id = tq.id
            WHERE uqa.quiz_id = p_quiz_id AND uqa.status IN ('completed', 'passed')
        )
    WHERE qs.quiz_id = p_quiz_id;
END //
DELIMITER ;

-- Create trigger to automatically update statistics when attempts are made
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_quiz_attempt_insert
AFTER INSERT ON user_quiz_attempts
FOR EACH ROW
BEGIN
    CALL update_quiz_statistics(NEW.quiz_id);
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_quiz_attempt_update
AFTER UPDATE ON user_quiz_attempts
FOR EACH ROW
BEGIN
    CALL update_quiz_statistics(NEW.quiz_id);
END //
DELIMITER ;

-- Update all existing quiz statistics
CALL update_quiz_statistics(NULL);

-- Temporary: Update all quizzes (since NULL doesn't work in all MySQL versions)
UPDATE quiz_statistics qs
SET
    total_attempts = (
        SELECT COUNT(*)
        FROM user_quiz_attempts uqa
        WHERE uqa.quiz_id = qs.quiz_id
    ),
    total_users = (
        SELECT COUNT(DISTINCT uqa.user_id)
        FROM user_quiz_attempts uqa
        WHERE uqa.quiz_id = qs.quiz_id
    ),
    average_score = (
        SELECT COALESCE(AVG(uqa.score), 0)
        FROM user_quiz_attempts uqa
        WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
    ),
    highest_score = (
        SELECT COALESCE(MAX(uqa.score), 0)
        FROM user_quiz_attempts uqa
        WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
    ),
    lowest_score = (
        SELECT COALESCE(MIN(uqa.score), 0)
        FROM user_quiz_attempts uqa
        WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
    ),
    pass_rate = (
        SELECT COALESCE(
            (SUM(CASE WHEN uqa.score >= tq.passing_score THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 0
        )
        FROM user_quiz_attempts uqa
        JOIN training_quizzes tq ON uqa.quiz_id = tq.id
        WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
    );

-- ============================================================
-- Quiz Statistics Table Complete
-- ============================================================

/*
This file creates the missing quiz_statistics table that manage_quizzes.php expects.

Features:
1. **Statistics Table**: Stores aggregated quiz performance data
2. **Auto-update Triggers**: Automatically updates when quiz attempts are made
3. **Stored Procedure**: Efficiently calculates statistics for individual quizzes
4. **Initial Data**: Populates statistics for existing quizzes
5. **Performance Optimized**: Uses indexes for fast queries

The table includes:
- Total attempts and unique users
- Average, highest, and lowest scores
- Pass rate calculation
- Total questions count
- Real-time updates via triggers

Import this file to fix the "quiz_statistics table not found" error.
*/