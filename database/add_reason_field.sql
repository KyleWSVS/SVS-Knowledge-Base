-- Migration to add reason field to edit_requests table
-- This should be run if the edit_requests table already exists without the reason field

-- Check if the reason column exists and add it if it doesn't
SET @dbname = DATABASE();
SET @tablename = 'edit_requests';
SET @columnname = 'reason';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1;',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TEXT NOT NULL DEFAULT \'\';')
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;