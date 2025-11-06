-- ============================================================
-- Quiz Statistics Table - Simplified Version (No Procedures/Triggers)
-- Created: 2025-11-06
-- Compatible with hosting providers that don't allow stored procedures
-- ============================================================

-- Create Quiz Statistics Table for manage_quizzes.php
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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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

-- Update statistics for all existing quizzes (one-time update)
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
-- Manual Statistics Update Instructions
-- ============================================================

/*
This simplified version creates the quiz_statistics table without stored procedures or triggers.

To keep statistics updated, you can:

1. **Manual Updates**: Run the UPDATE query below whenever needed:
   UPDATE quiz_statistics qs
   SET
     total_attempts = (SELECT COUNT(*) FROM user_quiz_attempts uqa WHERE uqa.quiz_id = qs.quiz_id),
     total_users = (SELECT COUNT(DISTINCT uqa.user_id) FROM user_quiz_attempts uqa WHERE uqa.quiz_id = qs.quiz_id),
     average_score = (SELECT COALESCE(AVG(uqa.score), 0) FROM user_quiz_attempts uqa WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')),
     highest_score = (SELECT COALESCE(MAX(uqa.score), 0) FROM user_quiz_attempts uqa WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')),
     lowest_score = (SELECT COALESCE(MIN(uqa.score), 0) FROM user_quiz_attempts uqa WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')),
     pass_rate = (
       SELECT COALESCE((SUM(CASE WHEN uqa.score >= tq.passing_score THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 0)
       FROM user_quiz_attempts uqa
       JOIN training_quizzes tq ON uqa.quiz_id = tq.id
       WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
     );

2. **Automatic Updates**: The quiz management pages can include this update logic

3. **Scheduled Updates**: Run the update query periodically via cron job or admin maintenance

Features Included:
- Quiz statistics table with all necessary columns
- Initial population for existing quizzes
- One-time statistics calculation
- Manual update query available
- Compatible with restricted hosting environments
*/