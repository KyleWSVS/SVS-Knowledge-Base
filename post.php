<?php
/**
 * Post Detail Page
 * Displays post content, attachments, and replies
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Get post ID first (needed for training progress tracking)
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Load training helpers if available
if (file_exists('includes/training_helpers.php')) {
    require_once 'includes/training_helpers.php';

    // If this is training content, check for quiz availability
    if (function_exists('is_training_user') && is_training_user() && $post_id > 0) {
        try {
            // First check if there's a quiz for this post
            $stmt = $pdo->prepare("
                SELECT tq.id as quiz_id, tq.quiz_title, tp.quiz_completed, tp.last_quiz_attempt_id,
                       CASE WHEN uta.user_id IS NOT NULL THEN 'assigned' ELSE 'unassigned' END as training_status
                FROM training_quizzes tq
                LEFT JOIN training_progress tp ON tq.content_id = ? AND tq.content_type = 'post'
                    AND tp.user_id = ? AND tp.content_type = 'post' AND tp.content_id = ?
                LEFT JOIN user_training_assignments uta ON tq.content_id = ? AND tq.content_type = 'post'
                    AND uta.user_id = ? AND uta.status != 'completed'
                WHERE tq.content_id = ? AND tq.content_type = 'post' AND tq.is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute([$post_id, $_SESSION['user_id'], $post_id, $post_id, $_SESSION['user_id'], $post_id]);
            $training_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($training_data && $training_data['quiz_id']) {
                // Create/update progress record for this content
                $stmt = $pdo->prepare("
                    INSERT INTO training_progress (user_id, course_id, content_type, content_id, status)
                    VALUES (?, 0, 'post', ?, 'in_progress')
                    ON DUPLICATE KEY UPDATE
                    status = 'in_progress',
                    updated_at = NOW()
                ");
                $stmt->execute([$_SESSION['user_id'], $post_id]);

                // Show quiz availability banner
                if ($training_data['training_status'] === 'assigned') {
                    // Content is assigned to user's training
                    $quiz_completed = $training_data['quiz_completed'] ?? false;
                    $quiz_url = "take_quiz.php?quiz_id=" . $training_data['quiz_id'] . "&content_type=post&content_id=" . $post_id;

                    echo "<div class='training-quiz-banner' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15);'>";
                    echo "<div style='display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap;'>";

                    if ($quiz_completed) {
                        echo "<span style='font-size: 24px;'>✅</span>";
                        echo "<div>";
                        echo "<h3 style='margin: 0 0 5px 0; font-size: 18px;'>Quiz Completed!</h3>";
                        echo "<p style='margin: 0; opacity: 0.9;'>You have successfully completed the quiz for this content.</p>";
                        echo "</div>";
                        echo "<a href='quiz_results.php?attempt_id=" . ($training_data['last_quiz_attempt_id'] ?? '') . "' class='btn' style='background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);'>View Results</a>";
                    } else {
                        echo "<span style='font-size: 24px;'>📝</span>";
                        echo "<div>";
                        echo "<h3 style='margin: 0 0 5px 0; font-size: 18px;'>Quiz Available: " . htmlspecialchars($training_data['quiz_title']) . "</h3>";
                        echo "<p style='margin: 0; opacity: 0.9;'>After reading this content, take the quiz to mark it as complete.</p>";
                        echo "</div>";
                        echo "<a href='" . $quiz_url . "' class='btn' style='background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);'>Take Quiz</a>";
                    }

                    echo "</div>";
                    echo "</div>";
                } else {
                    // Quiz exists but not assigned to this user's training
                    echo "<div class='training-quiz-banner' style='background: #17a2b8; color: white; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;'>";
                    echo "<p style='margin: 0;'><strong>📝 Quiz Available:</strong> This content has a quiz, but it's not currently assigned to your training.</p>";
                    echo "</div>";
                }
            } else {
                // No quiz available for this content
                echo "<div class='training-quiz-banner' style='background: #6c757d; color: white; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;'>";
                echo "<p style='margin: 0;'><strong>📚 Training Content:</strong> This is part of your training materials. No quiz is available for this content yet.</p>";
                echo "</div>";
            }
        } catch (PDOException $e) {
            error_log("Error checking training quiz availability: " . $e->getMessage());
        }
    }
}

$error_message = '';
$post = null;
$files = [];
$replies = [];

if ($post_id <= 0) {
    header('Location: index.php');
    exit;
}

// Check if visibility columns exist
$subcategory_visibility_columns_exist = false;
$category_visibility_columns_exist = false;
try {
    $test_query = $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
    $subcategory_visibility_columns_exist = true;
} catch (PDOException $e) {
    // Subcategory visibility columns don't exist yet
}

try {
    $test_query = $pdo->query("SELECT visibility FROM categories LIMIT 1");
    $category_visibility_columns_exist = true;
} catch (PDOException $e) {
    // Category visibility columns don't exist yet
}

// Fetch post with subcategory and category info and visibility checks
try {
    $current_user_id = $_SESSION['user_id'];
    $is_super_user = is_super_admin();
    $is_admin = is_admin();

    if ($is_super_user && $subcategory_visibility_columns_exist) {
        // Super Admins can see all posts including it_only
        $stmt = $pdo->prepare("
            SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                s.visibility AS subcategory_visibility,
                s.allowed_users AS subcategory_allowed_users,
                c.name AS category_name,
                c.visibility AS category_visibility,
                c.allowed_users AS category_allowed_users
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
    } elseif ($is_admin && $subcategory_visibility_columns_exist) {
        // Normal Admins can see all posts except it_only
        $stmt = $pdo->prepare("
            SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                s.visibility AS subcategory_visibility,
                s.allowed_users AS subcategory_allowed_users,
                c.name AS category_name,
                c.visibility AS category_visibility,
                c.allowed_users AS category_allowed_users
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ? AND p.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
        ");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
    } elseif ($subcategory_visibility_columns_exist && $category_visibility_columns_exist) {
        // Regular users need visibility and privacy checks
        $stmt = $pdo->prepare("
            SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                s.visibility AS subcategory_visibility,
                s.allowed_users AS subcategory_allowed_users,
                c.name AS category_name,
                c.visibility AS category_visibility,
                c.allowed_users AS category_allowed_users
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ?
            AND (c.visibility = 'public'
                 OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
            AND (s.visibility = 'public'
                 OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
            AND (p.privacy = 'public'
                 OR p.user_id = ?
                 OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
        ");
        $stmt->execute([
            $post_id,
            '%"' . $current_user_id . '"%',
            '%"' . $current_user_id . '"%',
            $current_user_id,
            '%"' . $current_user_id . '"%'
        ]);
        $post = $stmt->fetch();
    } else {
        // Old database - basic query with just privacy checks
        $stmt = $pdo->prepare("
            SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                c.name AS category_name
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ?
            AND (p.privacy = 'public'
                 OR p.user_id = ?
                 OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
        ");
        $stmt->execute([$post_id, $current_user_id, '%"' . $current_user_id . '"%']);
        $post = $stmt->fetch();
    }

    if (!$post) {
        $error_message = 'Post not found or you do not have permission to access it.';
    } else {
        // Fetch files attached to this post
        $stmt = $pdo->prepare("SELECT * FROM files WHERE post_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$post_id]);
        $files = $stmt->fetchAll();

        // Fetch replies for this post
        $stmt = $pdo->prepare("SELECT * FROM replies WHERE post_id = ? ORDER BY created_at ASC");
        $stmt->execute([$post_id]);
        $replies = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Helper function to format timestamp
function format_timestamp($timestamp) {
    return date('M j, Y \a\t g:i A', strtotime($timestamp));
}

// Helper function to check if edited (grace period of 1 minute)
function is_edited($created_at, $updated_at) {
    return strtotime($updated_at) > (strtotime($created_at) + 60);
}

// Helper function to format file size
function format_filesize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Helper function to check if file is image
function is_image($file_path) {
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($ext, IMAGE_EXTENSIONS);
}

$page_title = $post ? htmlspecialchars($post['title']) : 'Post';
include 'includes/header.php';
?>

<div class="container">
    <!-- Search Bar -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="search.php" style="display: flex; gap: 10px;">
            <input
                type="text"
                name="q"
                class="form-input"
                placeholder="Search posts, categories, subcategories..."
                style="flex: 1; margin: 0;"
            >
            <button type="submit" class="btn btn-primary" style="margin: 0;">🔍 Search</button>
        </form>
    </div>

    <?php if ($post): ?>
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span>></span>
            <span><?php echo htmlspecialchars($post['category_name']); ?></span>
            <span>></span>
            <a href="subcategory.php?id=<?php echo $post['subcategory_id']; ?>"><?php echo htmlspecialchars($post['subcategory_name']); ?></a>
            <span>></span>
            <span class="current"><?php echo htmlspecialchars($post['title']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <a href="index.php" class="btn btn-primary">Back to Home</a>
    <?php elseif (isset($_GET['success'])): ?>
        <div class="success-message">
            <?php
            $success_messages = [
                'reply_added' => 'Update added successfully!',
                'reply_updated' => 'Update edited successfully!',
                'reply_deleted' => 'Update deleted successfully!'
            ];
            $success_key = $_GET['success'];
            echo isset($success_messages[$success_key]) ? $success_messages[$success_key] : 'Action completed successfully!';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($post): ?>
        <!-- Post Detail -->
        <div class="post-detail">
            <div class="post-detail-header">
                <h1 class="post-detail-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="post-timestamp">Posted <?php echo format_timestamp($post['created_at']); ?></div>
            </div>

            <!-- Action Buttons -->
            <div class="form-actions" style="margin-bottom: 20px;">
                <?php
                // Check if user can edit posts (only admins and super admins)
                $can_edit = true;
                $can_delete = true;

                if (function_exists('can_create_posts')) {
                    $can_edit = can_create_posts();
                    $can_delete = can_create_posts();
                }
                ?>

                <?php if ($can_edit): ?>
                    <a href="edit_post.php?id=<?php echo $post_id; ?>" class="btn btn-warning">Edit Post</a>
                <?php endif; ?>

                <!-- PDF Export disabled for now -->
                <!-- <a href="export_pdf.php?id=<?php echo $post_id; ?>" class="btn btn-primary">Export to PDF</a> -->

                <?php if ($can_delete): ?>
                    <a href="delete_post.php?id=<?php echo $post_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure? This will delete the post and ALL replies. This cannot be undone.');">Delete Post</a>
                <?php endif; ?>

                <?php if (!$can_edit && !$can_delete): ?>
                    <div style="color: #6c757d; font-style: italic; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                        📚 Training users have read-only access to content.
                    </div>
                <?php endif; ?>
            </div>

            <div class="post-content">
                <?php echo $post['content']; ?>
            </div>

            <?php if (is_edited($post['created_at'], $post['updated_at']) || $post['edited'] == 1): ?>
                <div class="edited-indicator">edited</div>
            <?php endif; ?>

            <!-- Attachments -->
            <?php if (!empty($files)): ?>
                <div class="post-attachments">

                    <!-- Preview Files (PDF/DOCX for inline viewing) -->
                    <?php
                    // Check if file_type_category column exists and filter preview files
                    $has_file_type_category = false;
                    try {
                        $test_query = $pdo->query("SELECT file_type_category FROM files LIMIT 1");
                        $has_file_type_category = true;
                    } catch (PDOException $e) {
                        // Column doesn't exist yet
                    }

                    $preview_files = [];
                    if ($has_file_type_category) {
                        $preview_files = array_filter($files, function($f) {
                            return isset($f['file_type_category']) && $f['file_type_category'] === 'preview';
                        });
                    }

                    if (!empty($preview_files)):
                    ?>
                        <div class="preview-files-section" style="margin-bottom: 30px;">
                            <h3 class="attachments-title" style="display: flex; align-items: center; gap: 8px;">
                                📄 Document Preview
                                <span style="font-size: 14px; font-weight: normal; color: #666;">(Click to expand/collapse)</span>
                            </h3>
                            <?php foreach ($preview_files as $file): ?>
                                <div class="preview-file-container" style="border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; background: white;">
                                    <div class="preview-file-header"
                                         style="padding: 12px 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; cursor: pointer; display: flex; justify-content: between; align-items: center;"
                                         onclick="togglePreview('preview_<?php echo $file['id']; ?>')">
                                        <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                                            <span style="font-size: 18px;">📄</span>
                                            <div>
                                                <div style="font-weight: 500; color: #333;"><?php echo htmlspecialchars($file['original_filename']); ?></div>
                                                <div style="font-size: 12px; color: #666;"><?php echo format_filesize($file['file_size']); ?> • Click to view inline</div>
                                            </div>
                                        </div>
                                        <span id="preview_<?php echo $file['id']; ?>_arrow" style="font-size: 14px; color: #666;">▼</span>
                                    </div>
                                    <div id="preview_<?php echo $file['id']; ?>_content" style="display: none; padding: 0; border: none; background: #f9f9f9;">
                                        <?php
                                        $file_ext = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));
                                        if ($file_ext === 'pdf'): ?>
                                            <iframe src="<?php echo htmlspecialchars($file['file_path']); ?>"
                                                    style="width: 100%; height: 600px; border: none; display: block;"
                                                    loading="lazy">
                                                <p>Your browser does not support PDF viewing.
                                                   <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download>Download the PDF</a> instead.</p>
                                            </iframe>
                                        <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                                            <div style="padding: 20px; text-align: center; background: #f9f9f9;">
                                                <div style="margin-bottom: 20px;">
                                                    <span style="font-size: 48px;">📄</span>
                                                    <h4 style="margin: 10px 0; color: #333;"><?php echo htmlspecialchars($file['original_filename']); ?></h4>
                                                    <p style="color: #666; margin-bottom: 20px;">Word Document Preview</p>
                                                </div>
                                                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 30px; margin-bottom: 20px;">
                                                    <p style="color: #666; font-size: 16px; line-height: 1.6;">
                                                        <strong>📋 Document Information</strong><br>
                                                        File Name: <?php echo htmlspecialchars($file['original_filename']); ?><br>
                                                        File Size: <?php echo format_filesize($file['file_size']); ?><br>
                                                        Type: Microsoft Word Document
                                                    </p>
                                                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">
                                                    <p style="color: #666; font-size: 14px;">
                                                        <strong>💡 Note:</strong> Word documents cannot be previewed directly in the browser for security reasons.<br>
                                                        Please download the file to view its contents.
                                                    </p>
                                                </div>
                                                <a href="<?php echo htmlspecialchars($file['file_path']); ?>"
                                                   download
                                                   class="btn btn-primary"
                                                   style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
                                                    <span>⬇</span> Download Word Document
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Regular Download Files -->
                    <?php
                    $download_files = array_filter($files, function($f) use ($has_file_type_category) {
                        // If file_type_category column exists, filter out preview files
                        if ($has_file_type_category) {
                            return !isset($f['file_type_category']) || $f['file_type_category'] !== 'preview';
                        }
                        // If column doesn't exist, treat all as download files (except images)
                        return !is_image($f['file_path']);
                    });

                    if (!empty($download_files)):
                    ?>
                        <div class="download-files-section">
                            <h3 class="attachments-title">📎 Files for Download</h3>
                            <div class="attachment-files">
                                <?php foreach ($download_files as $file): ?>
                                    <div class="attachment-file">
                                        <span class="file-icon">📄</span>
                                        <div class="file-info">
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download class="file-name">
                                                <?php echo htmlspecialchars($file['original_filename']); ?>
                                            </a>
                                            <div class="file-size"><?php echo format_filesize($file['file_size']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Images -->
                    <?php
                    $images = array_filter($files, function($f) { return is_image($f['file_path']); });
                    if (!empty($images)):
                    ?>
                        <div class="attachment-images-section" style="margin-top: 20px;">
                            <h3 class="attachments-title">🖼️ Images</h3>
                            <div class="attachment-images">
                                <?php foreach ($images as $file): ?>
                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                        <img src="<?php echo htmlspecialchars($file['file_path']); ?>"
                                             alt="<?php echo htmlspecialchars($file['original_filename']); ?>"
                                             class="attachment-image">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Replies Section -->
        <div class="replies-section">
            <div class="replies-header" onclick="toggleReplies()">
                <h2 class="replies-title">
                    Updates (<?php echo count($replies); ?>)
                    <span class="toggle-arrow" id="repliesArrow">▼</span>
                </h2>
            </div>

            <div id="repliesContent" style="display: none;">
                <?php if (empty($replies)): ?>
                    <div class="no-replies">
                        No updates yet. Be the first to add one!
                    </div>
                <?php else: ?>
                    <div class="replies-list">
                        <?php foreach ($replies as $reply): ?>
                            <?php
                            // Fetch files for this reply
                            $stmt = $pdo->prepare("SELECT * FROM files WHERE reply_id = ? ORDER BY uploaded_at ASC");
                            $stmt->execute([$reply['id']]);
                            $reply_files = $stmt->fetchAll();
                            ?>
                            <div class="reply-bubble">
                                <div class="reply-content">
                                    <?php echo $reply['content']; ?>

                                    <!-- Reply Attachments -->
                                    <?php if (!empty($reply_files)): ?>
                                        <div style="margin-top: 10px;">
                                            <?php foreach ($reply_files as $file): ?>
                                                <?php if (is_image($file['file_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                                        <img src="<?php echo htmlspecialchars($file['file_path']); ?>"
                                                             alt="<?php echo htmlspecialchars($file['original_filename']); ?>"
                                                             style="max-width: 150px; border-radius: 6px; margin: 5px;">
                                                    </a>
                                                <?php else: ?>
                                                    <div style="font-size: 12px; margin: 5px 0;">
                                                        📎 <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download>
                                                            <?php echo htmlspecialchars($file['original_filename']); ?>
                                                        </a>
                                                        (<?php echo format_filesize($file['file_size']); ?>)
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="reply-meta">
                                    <span class="reply-timestamp"><?php echo format_timestamp($reply['created_at']); ?></span>
                                    <div class="reply-actions">
                                        <a href="edit_reply.php?id=<?php echo $reply['id']; ?>" class="btn btn-warning btn-small">Edit</a>
                                        <a href="delete_reply.php?id=<?php echo $reply['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure? This will delete this update. This cannot be undone.');">Delete</a>
                                    </div>
                                </div>
                                <?php if (is_edited($reply['created_at'], $reply['updated_at']) || $reply['edited'] == 1): ?>
                                    <div class="reply-edited">edited</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Add Reply Form -->
                <div class="card" style="margin-top: 20px;">
                    <h3 style="margin-bottom: 15px; font-size: 18px;">Add Update</h3>
                    <form method="POST" action="add_reply.php" enctype="multipart/form-data">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">

                        <div class="form-group">
                            <label for="reply_content" class="form-label">Content</label>
                            <textarea id="reply_content" name="content" class="form-textarea" style="min-height: 150px;"></textarea>
                            <div class="form-hint">Use the toolbar to format your content. (Required unless attaching files)</div>
                        </div>

                        <div class="form-group">
                            <label for="reply_files" class="form-label">Attach Files (Optional)</label>
                            <input type="file" id="reply_files" name="files[]" class="form-file" multiple>
                            <div class="form-hint">Max 20 MB per file</div>
                        </div>

                        <button type="submit" class="btn btn-success">Add Update</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- TinyMCE for reply form -->
<script src="vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#reply_content',
        license_key: 'gpl',
        height: 200,
        menubar: false,
        plugins: 'lists link code table textcolor colorpicker image',
        toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link | table | image | code | removeformat',
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
        branding: false,
        promotion: false,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        images_upload_url: 'upload_image.php',
        automatic_uploads: true
    });

    // Toggle function for Updates section
    function toggleReplies() {
        const content = document.getElementById('repliesContent');
        const arrow = document.getElementById('repliesArrow');

        if (content.style.display === 'none') {
            content.style.display = 'block';
            arrow.textContent = '▼';
            arrow.style.transform = 'rotate(0deg)';
        } else {
            content.style.display = 'none';
            arrow.textContent = '▶';
            arrow.style.transform = 'rotate(0deg)';
        }
    }

    // Toggle function for preview windows
    function togglePreview(previewId) {
        const content = document.getElementById(previewId + '_content');
        const arrow = document.getElementById(previewId + '_arrow');

        if (content.style.display === 'none') {
            content.style.display = 'block';
            arrow.textContent = '▲';
        } else {
            content.style.display = 'none';
            arrow.textContent = '▼';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
