-- SQL Script to Add Preview File Support
-- Created: 2025-11-05
-- Author: Claude Code Assistant
--
-- This script adds a column to distinguish between regular download files
-- and inline preview files (PDF/DOCX)

-- Add file_type_category column to files table
ALTER TABLE files
ADD COLUMN file_type_category ENUM('download', 'preview') NOT NULL DEFAULT 'download'
AFTER file_type;

-- Add index for better query performance
CREATE INDEX idx_file_type_category ON files (file_type_category);

-- Update existing files to be categorized as download files
UPDATE files SET file_type_category = 'download' WHERE file_type_category = 'download';

-- Display confirmation
SELECT 'file_type_category column added successfully' as status;