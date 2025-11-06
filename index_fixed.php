<?php
/**
 * Fixed Home Page - Eliminates ALL category duplication
 * Uses array_unique and explicit deduplication
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = 'Home';

try {
    // Get all categories
    $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
    $categories_stmt = $pdo->query($categories_query);
    $raw_categories = $categories_stmt->fetchAll();

    // Create deduplicated categories array using ID as key
    $categories = [];
    foreach ($raw_categories as $category_row) {
        $category_id = $category_row['id'];

        // Ensure we only add each category once
        if (!isset($categories[$category_id])) {
            $categories[$category_id] = [
                'id' => $category_id,
                'name' => $category_row['name'],
                'icon' => $category_row['icon'],
                'creator_id' => $category_row['category_creator_id'],
                'subcategories' => []
            ];
        }
    }

    // Get subcategories for each category
    foreach ($categories as $category_id => &$category) {
        $subcategories_query = "
            SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count
            FROM subcategories s
            LEFT JOIN posts p ON s.id = p.subcategory_id
            WHERE s.category_id = ?
            GROUP BY s.id
            ORDER BY
                CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                s.name ASC
        ";
        $subcategories_stmt = $pdo->prepare($subcategories_query);
        $subcategories_stmt->execute([$category_id]);
        $subcategories = $subcategories_stmt->fetchAll();

        $category['subcategories'] = $subcategories;
    }

    // Final deduplication pass (redundant but safe)
    $categories = array_values($categories); // Reset array keys to 0,1,2...

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
    $categories = [];
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

    <div class="flex-between mb-20">
        <h2 style="font-size: 24px; color: #2d3748;">
            Knowledge Categories (Fixed - <?php echo count($categories); ?> total)
        </h2>
        <a href="add_category.php" class="btn btn-success">+ Add Category</a>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">
            <?php
            $success_messages = [
                'category_added' => 'Category added successfully!',
                'category_updated' => 'Category updated successfully!',
                'category_deleted' => 'Category deleted successfully!',
                'subcategory_added' => 'Subcategory added successfully!',
                'subcategory_updated' => 'Subcategory updated successfully!',
                'subcategory_deleted' => 'Subcategory deleted successfully!'
            ];
            $success_key = $_GET['success'];
            echo isset($success_messages[$success_key]) ? $success_messages[$success_key] : 'Action completed successfully!';
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔒</div>
            <div class="empty-state-text">
                <?php
                // Check if there are any categories in the system at all
                $total_categories_stmt = $pdo->query("SELECT COUNT(*) FROM categories");
                $total_categories = $total_categories_stmt->fetchColumn();

                if ($total_categories > 0) {
                    echo "No accessible categories found. You don't have permission to view any posts in the available categories.";
                } else {
                    echo "No categories yet. Click \"Add Category\" to get started.";
                }
                ?>
            </div>
        </div>
    <?php else: ?>
        <div class="category-list">
            <?php
            // Debug: Show what we're about to render
            error_log("RENDERING: " . count($categories) . " categories");
            foreach ($categories as $cat) {
                error_log("RENDERING CATEGORY: {$cat['id']} - {$cat['name']}");
            }
            ?>

            <?php foreach ($categories as $category): ?>
                <div class="category-item">
                    <div class="category-header">
                        <div class="category-name">
                            <?php if ($category['icon']): ?>
                                <span><?php echo htmlspecialchars($category['icon']); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                            <span style="color: #666; font-size: 12px;">(ID: <?php echo $category['id']; ?>)</span>
                        </div>
                        <div class="card-actions">
                            <a href="add_subcategory.php?category_id=<?php echo $category['id']; ?>" class="btn btn-primary btn-small">+ Add Subcategory</a>
                            <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-warning btn-small">Edit</a>
                            <a href="delete_category.php?id=<?php echo $category['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure? This will delete the category and ALL subcategories, posts, and replies under it. This cannot be undone.');">Delete</a>
                        </div>
                    </div>

                    <?php if (empty($category['subcategories'])): ?>
                        <div style="color: #a0aec0; font-style: italic; font-size: 14px;">
                            No subcategories yet. Click "Add Subcategory" to create one.
                        </div>
                    <?php else: ?>
                        <div class="subcategory-list">
                            <?php foreach ($category['subcategories'] as $subcategory): ?>
                                <div class="subcategory-item">
                                    <a href="subcategory.php?id=<?php echo $subcategory['id']; ?>" class="subcategory-name">
                                        <?php echo htmlspecialchars($subcategory['name']); ?>
                                    </a>
                                    <span class="post-count"><?php echo $subcategory['post_count']; ?> post(s)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>