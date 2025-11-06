<?php
/**
 * Training Course Management Page
 * Admin interface for managing training courses, content, and assignments
 * Only accessible by admin users
 *
 * Created: 2025-11-05
 * Author: Claude Code Assistant
 * Version: 2.4.4
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';
require_once 'includes/training_helpers.php';

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'Training Course Management';
$success_message = '';
$error_message = '';

// Check if training tables exist
$training_tables_exist = false;
try {
    $pdo->query("SELECT id FROM training_courses LIMIT 1");
    $training_tables_exist = true;
} catch (PDOException $e) {
    $error_message = "Training tables don't exist. Please import the add_training_system.sql file first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $training_tables_exist) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'create_course':
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $department = isset($_POST['department']) ? trim($_POST['department']) : '';
            $estimated_hours = isset($_POST['estimated_hours']) ? floatval($_POST['estimated_hours']) : 0;

            // Validation
            if (empty($name)) {
                $error_message = 'Course name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'Course name must be 255 characters or less.';
            } elseif ($estimated_hours < 0 || $estimated_hours > 999.9) {
                $error_message = 'Estimated hours must be between 0 and 999.9.';
            } else {
                $course_id = create_training_course($pdo, $name, $description, $department, $_SESSION['user_id']);
                if ($course_id) {
                    $success_message = 'Training course created successfully!';
                } else {
                    $error_message = 'Error creating training course.';
                }
            }
            break;

        case 'edit_course':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $department = isset($_POST['department']) ? trim($_POST['department']) : '';
            $estimated_hours = isset($_POST['estimated_hours']) ? floatval($_POST['estimated_hours']) : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($course_id <= 0) {
                $error_message = 'Invalid course ID.';
            } elseif (empty($name)) {
                $error_message = 'Course name is required.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE training_courses
                        SET name = ?, description = ?, department = ?, estimated_hours = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $department, $estimated_hours, $is_active, $course_id]);
                    $success_message = 'Course updated successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Error updating course: ' . $e->getMessage();
                }
            }
            break;

        case 'delete_course':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

            if ($course_id <= 0) {
                $error_message = 'Invalid course ID.';
            } else {
                try {
                    // Check if users are assigned
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_training_assignments WHERE course_id = ?");
                    $stmt->execute([$course_id]);
                    $assignment_count = $stmt->fetchColumn();

                    if ($assignment_count > 0) {
                        $error_message = 'Cannot delete course with assigned users. Deactivate the course instead.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM training_courses WHERE id = ?");
                        $stmt->execute([$course_id]);
                        $success_message = 'Course deleted successfully!';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error deleting course: ' . $e->getMessage();
                }
            }
            break;

        case 'add_content':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $content_ids = isset($_POST['content_ids']) ? $_POST['content_ids'] : [];
            $time_required = isset($_POST['time_required']) ? intval($_POST['time_required']) : 0;
            $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
            $training_order = isset($_POST['training_order']) ? intval($_POST['training_order']) : 0;

            if ($course_id <= 0) {
                $error_message = 'Invalid course ID.';
            } elseif (empty($content_ids)) {
                $error_message = 'Please select at least one content item.';
            } else {
                $success_count = 0;
                foreach ($content_ids as $content_id) {
                    list($content_type, $content_id_num) = explode('_', $content_id);
                    if (add_content_to_course($pdo, $course_id, $content_type, $content_id_num, $time_required, $admin_notes, $training_order)) {
                        $success_count++;
                    }
                }

                if ($success_count > 0) {
                    $success_message = "Successfully added {$success_count} content item(s) to course.";

                    // Handle user reversion if course is already completed
                    $reverted_count = handle_new_training_content($pdo, $course_id);
                    if ($reverted_count > 0) {
                        $success_message .= " {$reverted_count} user(s) reverted to training status.";
                    }
                } else {
                    $error_message = 'Error adding content to course.';
                }
            }
            break;

        case 'assign_course':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];

            if ($course_id <= 0) {
                $error_message = 'Invalid course ID.';
            } elseif (empty($user_ids)) {
                $error_message = 'Please select at least one user.';
            } else {
                try {
                    // Check if course exists
                    $course_check = $pdo->prepare("SELECT id FROM training_courses WHERE id = ?");
                    $course_check->execute([$course_id]);
                    if ($course_check->rowCount() === 0) {
                        $error_message = 'Course not found.';
                    } else {
                        $assigned_count = assign_course_to_users($pdo, $course_id, $user_ids, $_SESSION['user_id']);
                        if ($assigned_count > 0) {
                            $success_message = 'Course assigned to ' . $assigned_count . ' user(s) successfully!';
                        } else {
                            $error_message = 'Error assigning course to users. Users may already be assigned to this course.';
                        }
                    }
                } catch (PDOException $e) {
                    $error_message = 'Database error: ' . $e->getMessage();
                }
            }
            break;

        case 'remove_content':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $content_type = isset($_POST['content_type']) ? $_POST['content_type'] : '';
            $content_id = isset($_POST['content_id']) ? intval($_POST['content_id']) : 0;

            if ($course_id <= 0 || empty($content_type) || $content_id <= 0) {
                $error_message = 'Invalid content parameters.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        DELETE FROM training_course_content
                        WHERE course_id = ? AND content_type = ? AND content_id = ?
                    ");
                    $stmt->execute([$course_id, $content_type, $content_id]);
                    $success_message = 'Content removed from course successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Error removing content: ' . $e->getMessage();
                }
            }
            break;

        case 'remove_multiple_content':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $content_ids = isset($_POST['remove_content_ids']) ? $_POST['remove_content_ids'] : [];

            if ($course_id <= 0 || empty($content_ids)) {
                $error_message = 'Please select content to remove.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $removed_count = 0;

                    foreach ($content_ids as $content_id) {
                        list($content_type, $content_id_num) = explode('_', $content_id);
                        $stmt = $pdo->prepare("
                            DELETE FROM training_course_content
                            WHERE course_id = ? AND content_type = ? AND content_id = ?
                        ");
                        $stmt->execute([$course_id, $content_type, $content_id_num]);
                        $removed_count++;
                    }

                    $pdo->commit();
                    $success_message = "Successfully removed {$removed_count} content item(s) from the course.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error_message = 'Error removing content: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Handle AJAX requests
if (isset($_GET['action']) && $training_tables_exist) {
    $action = $_GET['action'];

    if ($action === 'get_assigned_users') {
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

        header('Content-Type: application/json');

        if ($course_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
            exit;
        }

        try {
            // Get assigned users for this course
            $stmt = $pdo->prepare("
                SELECT user_id
                FROM user_training_assignments
                WHERE course_id = ? AND status != 'completed'
            ");
            $stmt->execute([$course_id]);
            $assigned_users = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            echo json_encode([
                'success' => true,
                'assigned_user_ids' => $assigned_users
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if ($action === 'get_content') {
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

        if ($course_id > 0) {
            try {
                $stmt = $pdo->prepare("
                SELECT tcc.*,
                       CASE tcc.content_type
                           WHEN 'category' THEN c.name
                           WHEN 'subcategory' THEN s.name
                           WHEN 'post' THEN p.title
                       END as content_name
                FROM training_course_content tcc
                LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
                LEFT JOIN subcategories s ON tcc.content_type = 'subcategory' AND tcc.content_id = s.id
                LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
                WHERE tcc.course_id = ?
                ORDER BY tcc.training_order ASC
            ");
            $stmt->execute([$course_id]);
            $content_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($content_items)) {
                echo '<p style="color: #6c757d; text-align: center; padding: 20px;">No content assigned to this course yet.</p>';
            } else {
                echo '<form id="removeContentForm" method="POST" action="manage_training_courses.php">';
                echo '<input type="hidden" name="action" value="remove_multiple_content">';
                echo '<input type="hidden" name="course_id" value="' . $course_id . '">';

                echo '<div style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">';
                echo '<input type="checkbox" id="selectAllContent" onchange="toggleAllContentCheckboxes()">';
                echo '<label for="selectAllContent" style="margin: 0; font-size: 12px; font-weight: 500;">Select All</label>';
                echo '<button type="submit" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">Remove Selected</button>';
                echo '</div>';

                foreach ($content_items as $item) {
                    echo '<div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; margin-bottom: 4px; background: #f8f9fa; border-radius: 4px; border-left: 3px solid #17a2b8;">';
                    echo '<div style="display: flex; align-items: center; gap: 8px;">';
                    echo '<input type="checkbox" name="remove_content_ids[]" value="' . $item['content_type'] . '_' . $item['content_id'] . '" class="content_checkbox">';
                    echo '<div>';
                    echo '<div style="font-weight: 500;">' . htmlspecialchars($item['content_name']) . '</div>';
                    echo '<div style="font-size: 11px; color: #6c757d;">';
                    echo 'Type: ' . ucfirst($item['content_type']);
                    if ($item['time_required_minutes'] > 0) {
                        echo ' • Time: ' . $item['time_required_minutes'] . ' min';
                    }
                    if ($item['training_order'] > 0) {
                        echo ' • Order: ' . $item['training_order'];
                    }
                    echo '</div>';
                    if (!empty($item['admin_notes'])) {
                        echo '<div style="font-size: 10px; color: #6c757d; margin-top: 2px;">Notes: ' . htmlspecialchars($item['admin_notes']) . '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }

                echo '</form>';

                echo '<script>
                function toggleAllContentCheckboxes() {
                    const selectAll = document.getElementById("selectAllContent");
                    const checkboxes = document.querySelectorAll(".content_checkbox");
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                }
                </script>';
            }
        } catch (PDOException $e) {
            echo '<p style="color: #dc3545;">Error loading content.</p>';
        }
    } else {
        echo '<p style="color: #dc3545;">Invalid course ID.</p>';
    }
    exit; // Stop execution to prevent full page from loading
}

// Fetch data
$courses = [];
$all_users = [];
$categories = [];
$subcategories = [];
$posts = [];

if ($training_tables_exist) {
    try {
        // Get courses with stats
        $courses = get_training_courses($pdo);

        // Get all users for assignment using the same approach as category visibility controls
        $all_users = get_all_users($pdo);

        // Get content for assignment with proper relationships
        $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get subcategories with their category_id
        $stmt = $pdo->query("SELECT id, name, category_id FROM subcategories ORDER BY name ASC");
        $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get posts with their subcategory_id
        $stmt = $pdo->query("SELECT id, title, subcategory_id FROM posts ORDER BY title ASC");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = 'Error fetching data: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span class="current">Training Course Management</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🎓 Training Course Management</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-primary" onclick="showCreateCourseModal()">➕ Create New Course</button>
                <a href="index.php" class="btn btn-secondary">← Back to Home</a>
            </div>
        </div>

        <?php if (!is_super_admin()): ?>
        <div style="margin: 20px; padding: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; color: #856404;">
            <strong>🎓 Training Admin Access:</strong> As an admin, you can create training courses, assign content, and manage trainees. Only super admins can modify course assignments for other admins.
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message" style="margin: 20px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; color: #155724;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="error-message" style="margin: 20px; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$training_tables_exist): ?>
            <div class="card-content" style="padding: 20px;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Database Setup Required</h3>
                    <p style="color: #856404; margin: 0;">The training tables don't exist in your database. Please import the following SQL file first:</p>
                    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; margin: 8px 0; font-family: monospace; font-size: 12px;">
                        add_training_system.sql
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card-content" style="padding: 0;">
                <?php if (empty($courses)): ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🎓</div>
                        <h3>No Training Courses Found</h3>
                        <p>There are no training courses in the database. Click "Create New Course" to get started.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Course Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Department</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Assigned Users</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Completion Rate</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Status</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr style="border-bottom: 1px solid #dee2e6; <?php echo !$course['is_active'] ? 'background: #f8f9fa;' : ''; ?>">
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($course['name']); ?></div>
                                            <?php if (!empty($course['description'])): ?>
                                                <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">
                                                    <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($course['estimated_hours'] > 0): ?>
                                                <div style="font-size: 11px; color: #17a2b8; margin-top: 2px;">
                                                    ⏱️ <?php echo $course['estimated_hours']; ?> hours
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php if (!empty($course['department'])): ?>
                                                <span style="background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 12px; font-size: 11px;">
                                                    <?php echo htmlspecialchars($course['department']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic;">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 500;"><?php echo $course['assigned_users']; ?> users</div>
                                            <div style="font-size: 11px; color: #28a745;">
                                                <?php echo $course['completed_users']; ?> completed
                                            </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php
                                            $completion_rate = $course['assigned_users'] > 0
                                                ? round(($course['completed_users'] / $course['assigned_users']) * 100)
                                                : 0;
                                            ?>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="flex: 1; background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden;">
                                                    <div style="background: <?php echo $completion_rate >= 75 ? '#28a745' : ($completion_rate >= 50 ? '#ffc107' : '#dc3545'); ?>; height: 100%; width: <?php echo $completion_rate; ?>%; transition: width 0.3s ease;"></div>
                                                </div>
                                                <span style="font-size: 12px; color: #6c757d; min-width: 40px;"><?php echo $completion_rate; ?>%</span>
                                            </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: <?php echo $course['is_active'] ? '#28a745' : '#6c757d'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                                <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <button type="button" class="btn btn-sm" style="background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showEditCourseModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['name']); ?>', '<?php echo htmlspecialchars($course['description']); ?>', '<?php echo htmlspecialchars($course['department']); ?>', <?php echo $course['estimated_hours']; ?>, <?php echo $course['is_active']; ?>)">Edit</button>

                                                <button type="button" class="btn btn-sm" style="background: #17a2b8; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showManageContentModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['name']); ?>')">Content</button>

                                                <button type="button" class="btn btn-sm" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showAssignUsersModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['name']); ?>')">Assign</button>

                                                <?php if ($course['assigned_users'] == 0): ?>
                                                    <button type="button" class="btn btn-sm" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['name']); ?>')">Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Course Modal -->
<div id="createCourseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 600px;">
        <h3 style="margin: 0 0 16px 0;">➕ Create New Training Course</h3>
        <form method="POST" action="manage_training_courses.php">
            <input type="hidden" name="action" value="create_course">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Course Name *</label>
                <input type="text" name="name" required maxlength="255" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Description</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Department</label>
                <input type="text" name="department" maxlength="100" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="e.g., General Staff, IT, HR, Sales">
                <div style="margin-top: 4px; font-size: 12px; color: #6c757d; font-style: italic;">
                    Enter department name (free text - you control the organization)
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Estimated Hours</label>
                <input type="number" name="estimated_hours" step="0.1" min="0" max="999.9" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="2.5">
                <div style="margin-top: 4px; font-size: 12px; color: #6c757d; font-style: italic;">
                    Estimated time to complete this course
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideCreateCourseModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Create Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editCourseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 600px;">
        <h3 style="margin: 0 0 16px 0;">✏️ Edit Training Course</h3>
        <form method="POST" action="manage_training_courses.php">
            <input type="hidden" name="action" value="edit_course">
            <input type="hidden" id="edit_course_id" name="course_id">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Course Name *</label>
                <input type="text" id="edit_name" name="name" required maxlength="255" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Description</label>
                <textarea id="edit_description" name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Department</label>
                <input type="text" id="edit_department" name="department" maxlength="100" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Estimated Hours</label>
                <input type="number" id="edit_estimated_hours" name="estimated_hours" step="0.1" min="0" max="999.9" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="edit_is_active" name="is_active" checked>
                    <span style="font-weight: 500;">Active</span>
                </label>
                <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">
                    Inactive courses won't be assigned to new users
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideEditCourseModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Update Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Content Modal -->
<div id="manageContentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto;">
        <h3 style="margin: 0 0 16px 0;">📚 Manage Course Content</h3>
        <div style="margin-bottom: 16px; padding: 12px; background: #f8f9fa; border-radius: 4px;">
            <strong>Course:</strong> <span id="manage_course_name"></span>
        </div>

        <!-- Current Content -->
        <div style="margin-bottom: 24px;">
            <h4 style="margin: 0 0 12px 0;">Current Content</h4>
            <div id="current_content" style="border: 1px solid #ddd; border-radius: 4px; padding: 12px; max-height: 200px; overflow-y: auto;">
                <p style="color: #6c757d; text-align: center; padding: 20px;">Loading content...</p>
            </div>
        </div>

        <!-- Add New Content -->
        <div style="margin-bottom: 24px;">
            <h4 style="margin: 0 0 12px 0;">Add New Content</h4>
            <form id="addContentForm" method="POST" action="manage_training_courses.php">
                <input type="hidden" name="action" value="add_content">
                <input type="hidden" id="content_course_id" name="course_id">

                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Select Content (Category → Subcategory → Post)</label>

                    <!-- Step 1: Select Category -->
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;">1. Select Category</label>
                        <select id="content_category_select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">-- Choose a category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Step 2: Select Subcategory (filtered by category) -->
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;">2. Select Subcategory</label>
                        <select id="content_subcategory_select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" disabled>
                            <option value="">-- Select a category first --</option>
                        </select>
                    </div>

                    <!-- Step 3: Select Post (filtered by subcategory) -->
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;">3. Select Posts</label>
                        <div id="content_posts_list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 8px; background: #f9f9f9;">
                            <p style="color: #999; text-align: center; margin: 0;">Select a subcategory to see posts</p>
                        </div>
                    </div>

                    <!-- Hidden input to store selected content -->
                    <div id="selected_content_display" style="margin-bottom: 12px; padding: 8px; background: #e7f3ff; border-radius: 4px; display: none;">
                        <strong>Selected Content:</strong>
                        <div id="selected_content_list" style="margin-top: 4px; font-size: 12px;"></div>
                    </div>
                </div>

                <!-- Store category/subcategory/post relationships in data attributes -->
                <script>
                const categoryData = <?php echo json_encode($categories); ?>;
                const subcategoryData = <?php echo json_encode($subcategories); ?>;
                const postData = <?php echo json_encode($posts); ?>;
                const selectedContent = new Set();

                document.getElementById('content_category_select').addEventListener('change', function() {
                    const categoryId = this.value;
                    const subcategorySelect = document.getElementById('content_subcategory_select');

                    subcategorySelect.innerHTML = '';
                    selectedContent.clear();
                    document.getElementById('content_posts_list').innerHTML = '<p style="color: #999; text-align: center; margin: 0;">Select a subcategory to see posts</p>';
                    updateSelectedContentDisplay();

                    if (categoryId) {
                        // Filter subcategories by category_id
                        subcategorySelect.disabled = false;
                        subcategorySelect.innerHTML = '<option value="">-- Choose a subcategory --</option>';

                        const filteredSubcategories = subcategoryData.filter(sub => sub.category_id == categoryId);
                        filteredSubcategories.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.id;
                            option.textContent = sub.name;
                            subcategorySelect.appendChild(option);
                        });

                        if (filteredSubcategories.length === 0) {
                            subcategorySelect.innerHTML = '<option value="">-- No subcategories in this category --</option>';
                        }
                    } else {
                        subcategorySelect.disabled = true;
                        subcategorySelect.innerHTML = '<option value="">-- Select a category first --</option>';
                    }
                });

                document.getElementById('content_subcategory_select').addEventListener('change', function() {
                    const subcategoryId = this.value;
                    const postsContainer = document.getElementById('content_posts_list');

                    selectedContent.clear();
                    document.getElementById('content_posts_list').innerHTML = '';
                    updateSelectedContentDisplay();

                    if (subcategoryId) {
                        const filteredPosts = postData.filter(post => post.subcategory_id == subcategoryId);

                        if (filteredPosts.length === 0) {
                            postsContainer.innerHTML = '<p style="color: #999; text-align: center; margin: 0;">No posts in this subcategory</p>';
                        } else {
                            let html = '';
                            filteredPosts.forEach(post => {
                                html += '<label style="display: block; margin-bottom: 6px; padding: 6px; background: white; border-radius: 3px; cursor: pointer;">';
                                html += '<input type="checkbox" value="post_' + post.id + '" class="post_checkbox" onchange="updateSelectedContent(this)">';
                                html += '<span style="margin-left: 6px;">' + post.title + '</span>';
                                html += '</label>';
                            });
                            postsContainer.innerHTML = html;
                        }
                    } else {
                        postsContainer.innerHTML = '<p style="color: #999; text-align: center; margin: 0;">Select a subcategory to see posts</p>';
                    }
                });

                function updateSelectedContent(checkbox) {
                    if (checkbox.checked) {
                        selectedContent.add(checkbox.value);
                    } else {
                        selectedContent.delete(checkbox.value);
                    }
                    updateSelectedContentDisplay();
                }

                function updateSelectedContentDisplay() {
                    const display = document.getElementById('selected_content_display');
                    const list = document.getElementById('selected_content_list');

                    if (selectedContent.size === 0) {
                        display.style.display = 'none';
                        list.innerHTML = '';
                    } else {
                        display.style.display = 'block';
                        list.innerHTML = Array.from(selectedContent).map(id => {
                            const parts = id.split('_');
                            const type = parts[0];
                            const itemId = parts[1];
                            let name = '';
                            if (type === 'post') {
                                const post = postData.find(p => p.id == itemId);
                                name = post ? post.title : 'Unknown';
                            }
                            return '<div>📄 ' + name + ' <button type="button" style="background: none; border: none; color: #dc3545; cursor: pointer; padding: 0; margin-left: 4px;" onclick="removeSelectedContent(\'' + id + '\')">×</button></div>';
                        }).join('');
                    }
                }

                function removeSelectedContent(contentId) {
                    selectedContent.delete(contentId);
                    document.querySelectorAll('.post_checkbox').forEach(cb => {
                        if (cb.value === contentId) {
                            cb.checked = false;
                        }
                    });
                    updateSelectedContentDisplay();
                }

                // Before form submission, populate hidden inputs
                document.getElementById('addContentForm').addEventListener('submit', function(e) {
                    // Clear existing inputs
                    const existingInputs = this.querySelectorAll('input[name="content_ids[]"]');
                    existingInputs.forEach(input => input.remove());

                    // Add selected content as hidden inputs
                    selectedContent.forEach(contentId => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'content_ids[]';
                        input.value = contentId;
                        this.appendChild(input);
                    });
                });
                </script>

                <div style="display: flex; gap: 16px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;">Time Required (minutes)</label>
                        <input type="number" name="time_required" min="0" max="999" value="0" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;">Training Order</label>
                        <input type="number" name="training_order" min="0" max="999" value="0" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500;">Admin Notes</label>
                    <textarea name="admin_notes" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;" placeholder="Optional notes about this content..."></textarea>
                </div>

                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" onclick="hideManageContentModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 8px 16px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">Add Content</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Users Modal -->
<div id="assignUsersModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
        <h3 style="margin: 0 0 16px 0;">👥 Assign Users to Course</h3>
        <div style="margin-bottom: 16px; padding: 12px; background: #f8f9fa; border-radius: 4px;">
            <strong>Course:</strong> <span id="assign_course_name"></span>
        </div>

        <form id="assignUsersForm" method="POST" action="manage_training_courses.php">
            <input type="hidden" name="action" value="assign_course">
            <input type="hidden" id="assign_course_id" name="course_id">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Select Users to Assign</label>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 12px;">
                    <?php foreach ($all_users as $user): ?>
                        <label style="display: block; margin-bottom: 8px; cursor: pointer; padding: 4px; border-radius: 3px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f0f0f0'" onmouseout="this.style.backgroundColor='transparent'">
                            <input
                                type="checkbox"
                                name="user_ids[]"
                                value="<?php echo $user['id']; ?>"
                                style="margin-right: 8px;"
                            >
                            <span style="display: inline-block; width: 12px; height: 12px; background: <?php echo htmlspecialchars($user['color']); ?>; border-radius: 50%; margin-right: 6px; vertical-align: middle;"></span>
                            <?php echo htmlspecialchars($user['name']); ?>
                            <span style="color: #666; font-size: 12px; margin-left: 4px;">(ID: <?php echo $user['id']; ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideAssignUsersModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Assign Users</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreateCourseModal() {
    document.getElementById('createCourseModal').style.display = 'block';
}

function hideCreateCourseModal() {
    document.getElementById('createCourseModal').style.display = 'none';
}

function showEditCourseModal(courseId, name, description, department, estimatedHours, isActive) {
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_department').value = department;
    document.getElementById('edit_estimated_hours').value = estimatedHours;
    document.getElementById('edit_is_active').checked = isActive;
    document.getElementById('editCourseModal').style.display = 'block';
}

function hideEditCourseModal() {
    document.getElementById('editCourseModal').style.display = 'none';
}

function showManageContentModal(courseId, courseName) {
    document.getElementById('manage_course_name').textContent = courseName;
    document.getElementById('content_course_id').value = courseId;
    loadCourseContent(courseId);
    document.getElementById('manageContentModal').style.display = 'block';
}

function hideManageContentModal() {
    document.getElementById('manageContentModal').style.display = 'none';
}

function showAssignUsersModal(courseId, courseName) {
    document.getElementById('assign_course_name').textContent = courseName;
    document.getElementById('assign_course_id').value = courseId;

    // Load current user assignments for this course
    fetch(`manage_training_courses.php?action=get_assigned_users&course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear all checkboxes first
                const checkboxes = document.querySelectorAll('input[name="user_ids[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });

                // Check boxes for currently assigned users
                data.assigned_user_ids.forEach(userId => {
                    const checkbox = document.querySelector(`input[name="user_ids[]"][value="${userId}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading assigned users:', error);
        });

    document.getElementById('assignUsersModal').style.display = 'block';
}

function hideAssignUsersModal() {
    document.getElementById('assignUsersModal').style.display = 'none';
}

function loadCourseContent(courseId) {
    fetch(`manage_training_courses.php?action=get_content&course_id=${courseId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('current_content').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('current_content').innerHTML = '<p style="color: #dc3545;">Error loading content.</p>';
        });
}

function removeContent(courseId, contentType, contentId) {
    if (confirm('Are you sure you want to remove this content from the course?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_training_courses.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'remove_content';

        const courseIdInput = document.createElement('input');
        courseIdInput.type = 'hidden';
        courseIdInput.name = 'course_id';
        courseIdInput.value = courseId;

        const contentTypeInput = document.createElement('input');
        contentTypeInput.type = 'hidden';
        contentTypeInput.name = 'content_type';
        contentTypeInput.value = contentType;

        const contentIdInput = document.createElement('input');
        contentIdInput.type = 'hidden';
        contentIdInput.name = 'content_id';
        contentIdInput.value = contentId;

        form.appendChild(actionInput);
        form.appendChild(courseIdInput);
        form.appendChild(contentTypeInput);
        form.appendChild(contentIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteCourse(courseId, courseName) {
    if (confirm(`Are you sure you want to delete the course "${courseName}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_training_courses.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_course';

        const courseIdInput = document.createElement('input');
        courseIdInput.type = 'hidden';
        courseIdInput.name = 'course_id';
        courseIdInput.value = courseId;

        form.appendChild(actionInput);
        form.appendChild(courseIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.id === 'createCourseModal') {
        hideCreateCourseModal();
    } else if (event.target.id === 'editCourseModal') {
        hideEditCourseModal();
    } else if (event.target.id === 'manageContentModal') {
        hideManageContentModal();
    } else if (event.target.id === 'assignUsersModal') {
        hideAssignUsersModal();
    }
}
</script>

<?php include 'includes/footer.php'; ?>