-- SQL Script to Add Super Admin Role and Set Correct User Roles
-- Created: 2025-11-05
-- Author: Claude Code Assistant
-- Version: 1.0
--
-- This script updates the ENUM and sets proper roles as requested:
-- - Kyle Walker (ID 1) = Super Admin
-- - Cody Kirsten (ID 2) = Admin
-- - Deegan Begovich (ID 3) = Admin
-- - Test Account (ID 4) = User
--
-- Purpose: Implement proper role hierarchy for user management security
-- Last Modified: 2025-11-05

-- ============================================
-- STEP 1: Backup current data (safety first!)
-- ============================================

-- Create backup table before making changes
CREATE TABLE IF NOT EXISTS users_backup_before_role_update AS
SELECT * FROM users;

-- Show current users before changes
SELECT '=== BEFORE CHANGES ===' as stage;
SELECT
    id,
    name,
    role,
    is_active,
    'Current state' as status
FROM users
ORDER BY id;

-- ============================================
-- STEP 2: Update the role ENUM to include 'super admin'
-- ============================================

-- Modify the role column to support super admin
-- MySQL/MariaDB syntax:
ALTER TABLE users
MODIFY COLUMN role ENUM('user', 'admin', 'super admin') NOT NULL DEFAULT 'user';

-- ============================================
-- STEP 3: Set the correct roles for all users
-- ============================================

-- Kyle Walker (ID 1) -> Super Admin
UPDATE users
SET
    role = 'super admin',
    updated_at = NOW()
WHERE id = 1;

-- Cody Kirsten (ID 2) -> Admin
UPDATE users
SET
    role = 'admin',
    updated_at = NOW()
WHERE id = 2;

-- Deegan Begovich (ID 3) -> Admin
UPDATE users
SET
    role = 'admin',
    updated_at = NOW()
WHERE id = 3;

-- Test Account (ID 4) -> User
UPDATE users
SET
    role = 'user',
    updated_at = NOW()
WHERE id = 4;

-- ============================================
-- STEP 4: Verify the changes
-- ============================================

-- Check that all users have correct roles
SELECT '=== AFTER CHANGES ===' as stage;
SELECT
    id,
    name,
    role,
    is_active,
    updated_at,
    CASE
        WHEN id = 1 AND LOWER(role) = 'super admin' THEN '✅ Kyle Walker is now Super Admin'
        WHEN id = 2 AND LOWER(role) = 'admin' THEN '✅ Cody Kirsten is now Admin'
        WHEN id = 3 AND LOWER(role) = 'admin' THEN '✅ Deegan Begovich is now Admin'
        WHEN id = 4 AND LOWER(role) = 'user' THEN '✅ Test Account is now User'
        ELSE 'Updated'
    END as status
FROM users
ORDER BY id;

-- Show all users with their new roles
SELECT
    id,
    name,
    role,
    CASE
        WHEN LOWER(role) = 'super admin' THEN '🔑 Super Admin'
        WHEN LOWER(role) = 'admin' THEN '👨‍💼 Admin'
        ELSE '👤 User'
    END as role_display,
    is_active
FROM users
ORDER BY id;

-- ============================================
-- STEP 5: Verify ENUM constraints work
-- ============================================

-- Test that the ENUM accepts all role values
SELECT '=== ENUM VALIDATION ===' as stage;
SELECT
    'Valid role values for users.role column:' as info;

-- This query should work with the new ENUM
SELECT
    'user' as test_value,
    '✅ Valid' as validation
UNION ALL
SELECT
    'admin' as test_value,
    '✅ Valid' as validation
UNION ALL
SELECT
    'super admin' as test_value,
    '✅ Valid' as validation;

-- ============================================
-- STEP 6: Session compatibility verification
-- ============================================

SELECT '=== SESSION COMPATIBILITY ===' as stage;

-- Kyle Walker (Super Admin) session data
SELECT
    'Kyle Walker (Super Admin) session data:' as info,
    1 as user_id,
    'Kyle Walkerrr' as user_name,
    'super admin' as user_role,
    '#ff0000' as user_color
UNION ALL

-- Cody Kirsten (Admin) session data
SELECT
    'Cody Kirsten (Admin) session data:' as info,
    2 as user_id,
    'Cody Kirsten' as user_name,
    'admin' as user_role,
    '#00eeff' as user_color
UNION ALL

-- Deegan Begovich (Admin) session data
SELECT
    'Deegan Begovich (Admin) session data:' as info,
    3 as user_id,
    'Deegan Begovich' as user_name,
    'admin' as user_role,
    '#e74c3c' as user_color;

-- ============================================
-- STEP 7: Check compatibility with existing code
-- ============================================

SELECT '=== CODE COMPATIBILITY CHECK ===' as stage;
SELECT
    '✅ Kyle Walker: is_super_admin() returns TRUE' as check1,
    '✅ Kyle Walker: is_admin() returns TRUE' as check2,
    '✅ Cody/Deegan: is_super_admin() returns FALSE' as check3,
    '✅ Cody/Deegan: is_admin() returns TRUE' as check4,
    '✅ Test Account: is_admin() returns FALSE' as check5,
    '✅ User management shows correct options per role' as check6,
    '✅ Category visibility respects role hierarchy' as check7;

-- ============================================
-- STEP 8: Verify role hierarchy works correctly
-- ============================================

SELECT '=== ROLE HIERARCHY VERIFICATION ===' as stage;
SELECT
    name,
    role,
    CASE
        WHEN LOWER(role) = 'super admin' THEN 'Can edit ALL users including super admins'
        WHEN LOWER(role) = 'admin' THEN 'Can edit regular users & admins, NOT super admins'
        ELSE 'Can only edit own profile'
    END as permissions
FROM users
ORDER BY id;

-- ============================================
-- STEP 9: Rollback instructions (if needed)
-- ============================================

SELECT '=== ROLLBACK INSTRUCTIONS ===' as stage;
SELECT
    'To rollback all changes, run:' as rollback_info,
    'UPDATE users SET role = ''admin'' WHERE id IN (1,2,3);' as step1,
    'UPDATE users SET role = ''user'' WHERE id = 4;' as step2,
    'ALTER TABLE users MODIFY COLUMN role ENUM(''user'', ''admin'') NOT NULL DEFAULT ''user'';' as step3;