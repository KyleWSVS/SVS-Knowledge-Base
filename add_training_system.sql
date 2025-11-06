-- SQL Script to Add Training System
-- Created: 2025-11-05
-- Author: Claude Code Assistant
--
-- This script adds a comprehensive training system with:
-- - Training role support
-- - Training course management
-- - Progress tracking
-- - Training history preservation
-- - User role reversion support

-- ============================================================
-- STEP 1: Update User Role Enum
-- ============================================================

-- Modify the role column to support training role
ALTER TABLE users
MODIFY COLUMN role ENUM('super admin', 'admin', 'user', 'training') NOT NULL DEFAULT 'user';

-- Add fields for training reversion tracking (if they don't exist)
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'previous_role';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE users ADD COLUMN previous_role ENUM(\'super admin\', \'admin\', \'user\', \'training\') NULL AFTER role')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'training_revert_reason';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE users ADD COLUMN training_revert_reason VARCHAR(255) NULL AFTER previous_role')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'original_training_completion';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE users ADD COLUMN original_training_completion DATETIME NULL AFTER training_revert_reason')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================
-- STEP 2: Create Training Courses Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `training_courses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `department` VARCHAR(100) NULL,
  `estimated_hours` DECIMAL(4,1) DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_department` (`department`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_created_by` (`created_by`),
  CONSTRAINT `fk_training_courses_creator`
    FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
);

-- ============================================================
-- STEP 3: Create Training Course Content Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `training_course_content` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `course_id` INT NOT NULL,
  `content_type` ENUM('category', 'subcategory', 'post') NOT NULL,
  `content_id` INT NOT NULL,
  `is_required` BOOLEAN DEFAULT TRUE,
  `training_order` INT DEFAULT 0,
  `time_required_minutes` INT DEFAULT 0,
  `admin_notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_content` (`course_id`, `content_type`, `content_id`),
  INDEX `idx_course_id` (`course_id`),
  INDEX `idx_content_type` (`content_type`),
  INDEX `idx_training_order` (`training_order`),
  CONSTRAINT `fk_training_content_course`
    FOREIGN KEY (`course_id`)
    REFERENCES `training_courses` (`id`)
    ON DELETE CASCADE
);

-- ============================================================
-- STEP 4: Create User Training Assignments Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_training_assignments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `assigned_by` INT NOT NULL,
  `assigned_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_date` DATETIME NULL,
  `status` ENUM('not_started', 'in_progress', 'completed', 'expired') DEFAULT 'not_started',
  `completion_date` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_course` (`user_id`, `course_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_course_id` (`course_id`),
  INDEX `idx_assigned_by` (`assigned_by`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `fk_user_assignments_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_user_assignments_course`
    FOREIGN KEY (`course_id`)
    REFERENCES `training_courses` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_user_assignments_assigner`
    FOREIGN KEY (`assigned_by`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
);

-- ============================================================
-- STEP 5: Create Enhanced Training Progress Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `training_progress` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `content_type` ENUM('category', 'subcategory', 'post') NOT NULL,
  `content_id` INT NOT NULL,
  `status` ENUM('required', 'in_progress', 'completed', 'skipped') DEFAULT 'required',
  `completion_date` DATETIME NULL,
  `time_spent_minutes` INT DEFAULT 0,
  `time_started` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_progress` (`user_id`),
  INDEX `idx_course_progress` (`course_id`),
  INDEX `idx_content_progress` (`content_type`, `content_id`),
  INDEX `idx_status_progress` (`status`),
  INDEX `idx_user_content` (`user_id`, `content_type`, `content_id`),
  CONSTRAINT `fk_training_progress_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_training_progress_course`
    FOREIGN KEY (`course_id`)
    REFERENCES `training_courses` (`id`)
    ON DELETE CASCADE
);

-- ============================================================
-- STEP 6: Create Training History Table (Permanent Records)
-- ============================================================

CREATE TABLE IF NOT EXISTS `training_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `content_type` ENUM('category', 'subcategory', 'post') NOT NULL,
  `content_id` INT NOT NULL,
  `completion_date` DATETIME NOT NULL,
  `time_spent_minutes` INT NOT NULL,
  `course_completed_date` DATETIME NULL,
  `original_assignment_date` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_history_user` (`user_id`),
  INDEX `idx_history_course` (`course_id`),
  INDEX `idx_history_completion` (`completion_date`),
  INDEX `idx_history_content` (`content_type`, `content_id`),
  CONSTRAINT `fk_training_history_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_training_history_course`
    FOREIGN KEY (`course_id`)
    REFERENCES `training_courses` (`id`)
    ON DELETE CASCADE
);

-- ============================================================
-- STEP 7: Create Training Sessions Table (Time Tracking)
-- ============================================================

CREATE TABLE IF NOT EXISTS `training_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `content_type` ENUM('category', 'subcategory', 'post') NOT NULL,
  `content_id` INT NOT NULL,
  `session_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `session_end` DATETIME NULL,
  `duration_minutes` INT DEFAULT 0,
  `is_completed` BOOLEAN DEFAULT FALSE,
  PRIMARY KEY (`id`),
  INDEX `idx_sessions_user` (`user_id`),
  INDEX `idx_sessions_content` (`content_type`, `content_id`),
  INDEX `idx_sessions_start` (`session_start`),
  CONSTRAINT `fk_training_sessions_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
);

-- ============================================================
-- STEP 8: Update Existing Users to Default Role
-- ============================================================

-- Set existing non-admin users to 'user' role (training role is opt-in)
UPDATE users
SET role = 'user'
WHERE role NOT IN ('super admin', 'admin');

-- ============================================================
-- STEP 9: Create Sample Training Course (Optional)
-- ============================================================

-- Create a sample "General Staff Training" course
INSERT INTO training_courses (name, description, department, estimated_hours, created_by)
SELECT 'General Staff Training',
       'Basic training for all new staff members including company policies, safety procedures, and basic IT usage.',
       'General',
       2.5,
       id
FROM users
WHERE role = 'super admin'
LIMIT 1;

-- ============================================================
-- STEP 10: Add Indexes for Performance
-- ============================================================

-- Additional performance indexes
CREATE INDEX idx_users_role_training ON users(role);
CREATE INDEX idx_users_previous_role ON users(previous_role);
CREATE INDEX idx_training_courses_active_dept ON training_courses(is_active, department);
CREATE INDEX idx_assignments_status_user ON user_training_assignments(status, user_id);
CREATE INDEX idx_progress_user_course ON training_progress(user_id, course_id);
CREATE INDEX idx_history_user_completion ON training_history(user_id, completion_date);

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================

-- Verify tables were created
SELECT 'Training courses table' as table_name, COUNT(*) as record_count FROM training_courses
UNION ALL
SELECT 'Training course content table' as table_name, COUNT(*) as record_count FROM training_course_content
UNION ALL
SELECT 'User training assignments table' as table_name, COUNT(*) as record_count FROM user_training_assignments
UNION ALL
SELECT 'Training progress table' as table_name, COUNT(*) as record_count FROM training_progress
UNION ALL
SELECT 'Training history table' as table_name, COUNT(*) as record_count FROM training_history
UNION ALL
SELECT 'Training sessions table' as table_name, COUNT(*) as record_count FROM training_sessions;

-- Verify role enum update
DESCRIBE users;

-- Display success message
SELECT 'Training system database setup completed successfully!' as status;