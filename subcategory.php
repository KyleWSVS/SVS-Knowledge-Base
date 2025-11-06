<?php
/**
 * Subcategory Page - List Posts
 * Shows all posts in a subcategory with title, preview, and timestamp
 * Updated: 2025-11-05 (Removed hardcoded SQL user fallbacks - database-only users)
 *
 * FIXED: Removed hardcoded SQL user fallbacks that were interfering with database authentication
 * - User display now requires database users table
 * - Complete database-driven user system integration
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

$page_title = 'Posts';
$error_message = '';
$subcategory = null;
$posts = [];

// Get subcategory ID
$subcategory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($subcategory_id <= 0) {
    header('Location: index.php');
    exit;
}

// Check if subcategory visibility columns exist
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

// Fetch subcategory with category info for breadcrumb and visibility check
try {
    $current_user_id = $_SESSION['user_id'];
    $is_super_user = is_super_admin();
    $is_admin = is_admin();

    if ($is_super_user && $subcategory_visibility_columns_exist) {
        // Super Admins can see all subcategories including it_only
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    } elseif ($is_admin && $subcategory_visibility_columns_exist) {
        // Normal Admins can see all subcategories except it_only
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ? AND s.visibility != 'it_only' AND c.visibility != 'it_only'
        ");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    } elseif ($subcategory_visibility_columns_exist && $category_visibility_columns_exist) {
        // Regular users need visibility checks
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
            AND (c.visibility = 'public'
                 OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
            AND (s.visibility = 'public'
                 OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
        ");
        $stmt->execute([$subcategory_id, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%']);
        $subcategory = $stmt->fetch();
    } else {
        // Old database - no visibility checks
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    }

    if (!$subcategory) {
        $error_message = 'Subcategory not found or you do not have permission to access it.';
    } else {
        // Fetch posts in this subcategory with privacy filtering
        $current_user_id = $_SESSION['user_id'];

        if ($is_super_user) {
            // Super Admins can see all posts including it_only - no privacy filtering
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        u.name AS author_name,
                        u.color AS author_color,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.subcategory_id = ?
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            } else {
                // No fallback - require database users table
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    WHERE p.subcategory_id = ?
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            }
            $stmt->execute([$subcategory_id]);
        } elseif ($is_admin) {
            // Normal Admins can see all posts except it_only - no privacy filtering
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        u.name AS author_name,
                        u.color AS author_color,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.subcategory_id = ? AND p.privacy != 'it_only'
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            } else {
                // No fallback - require database users table
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    WHERE p.subcategory_id = ? AND p.privacy != 'it_only'
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            }
            $stmt->execute([$subcategory_id]);
        } else {
            // Regular users get privacy filtering
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        u.name AS author_name,
                        u.color AS author_color,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.subcategory_id = ? AND (
                        p.privacy = 'public' OR
                        p.user_id = ? OR
                        (p.privacy = 'shared' AND p.shared_with LIKE ?)
                    )
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            } else {
                // No fallback - require database users table
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    WHERE p.subcategory_id = ? AND (
                        p.privacy = 'public' OR
                        p.user_id = ? OR
                        (p.privacy = 'shared' AND p.shared_with LIKE ?)
                    )
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            }
            $shared_with_pattern = '%"' . $current_user_id . '"%';
            $stmt->execute([$subcategory_id, $current_user_id, $shared_with_pattern]);
        }
        $posts = $stmt->fetchAll();

        // Sort posts alphabetically by title (case-insensitive)
        usort($posts, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Helper function to format timestamp
function format_timestamp($timestamp) {
    return date('M j, Y \a\t g:i A', strtotime($timestamp));
}

// Helper function to create text preview (strip HTML, limit to 200 chars)
function create_preview($html_content, $length = 200) {
    // Convert HTML entities first
    $text = html_entity_decode($html_content, ENT_QUOTES, 'UTF-8');
    // Strip HTML tags
    $text = strip_tags($text);
    // Replace multiple whitespace with single space
    $text = preg_replace('/\s+/', ' ', $text);
    // Trim whitespace
    $text = trim($text);
    // Handle empty content
    if (empty($text)) {
        return '[No text content - attachments only]';
    }
    // Truncate if too long
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

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

    <?php if ($subcategory): ?>
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span>></span>
            <span><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
            <span>></span>
            <span class="current"><?php echo htmlspecialchars($subcategory['name']); ?></span>
        </div>

        <div class="flex-between mb-20">
            <div>
                <h2 style="font-size: 24px; color: #2d3748; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($subcategory['name']); ?>
                </h2>
                <div class="subcategory-actions">
                    <?php if ($is_admin): ?>
                        <a href="edit_subcategory.php?id=<?php echo $subcategory_id; ?>" class="btn btn-warning btn-small">Edit Subcategory</a>
                        <a href="delete_subcategory.php?id=<?php echo $subcategory_id; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure? This will delete the subcategory and ALL posts and replies under it. This cannot be undone.');">Delete Subcategory</a>
                    <?php else: ?>
                        <a href="request_edit_subcategory.php?id=<?php echo $subcategory_id; ?>" class="btn btn-warning btn-small">Request Edit</a>
                    <?php endif; ?>
                </div>
            </div>
            <a href="add_post.php?subcategory_id=<?php echo $subcategory_id; ?>" class="btn btn-success">+ Add Post</a>
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
                'post_added' => 'Post created successfully!',
                'post_updated' => 'Post updated successfully!',
                'post_deleted' => 'Post deleted successfully!'
            ];
            $success_key = $_GET['success'];
            echo isset($success_messages[$success_key]) ? $success_messages[$success_key] : 'Action completed successfully!';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($subcategory && empty($posts)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <div class="empty-state-text">No posts yet. Click "Add Post" to create one.</div>
        </div>
    <?php elseif ($subcategory): ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <div class="post-item">
                    <div class="post-header">
                        <a href="post.php?id=<?php echo $post['id']; ?>">
                            <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                        </a>
                        <div class="post-privacy-indicator">
                            <?php
                            $privacy_icons = [
                                'public' => '🌐',
                                'private' => '🔒',
                                'shared' => '👥',
                                'it_only' => '🔐'
                            ];
                            echo $privacy_icons[$post['privacy']] ?? '📝';
                            ?>
                        </div>
                    </div>
                    <div class="post-meta">
                        <span style="color: <?php echo htmlspecialchars($post['author_color']); ?>">
                            <?php echo htmlspecialchars($post['author_name']); ?>
                        </span>
                        <span><?php echo format_timestamp($post['created_at']); ?></span>
                        <span><?php echo $post['reply_count']; ?> update(s)</span>
                    </div>
                    <?php if (!empty($post['content'])): ?>
                        <div class="post-preview">
                            <?php echo htmlspecialchars(create_preview($post['content'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    // Show edit/delete buttons only for post owners or admins
                    if ($is_admin || $post['user_id'] == $current_user_id):
                    ?>
                        <div class="post-actions" style="margin-top: 8px; display: flex; gap: 8px;">
                            <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-small" style="background: #ffc107; color: black; text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 12px;">✏️ Edit</a>
                            <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="btn btn-small" style="background: #dc3545; color: white; text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 12px;" onclick="return confirm('Are you sure you want to delete this post? This cannot be undone.');">🗑️ Delete</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
