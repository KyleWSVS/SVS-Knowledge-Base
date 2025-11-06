<?php
/**
 * Simple Array Approach - Avoids associative array issues
 * Uses indexed array instead of ID-keyed array
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = 'Home (Simple Array)';

try {
    // Get categories
    $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
    $categories_stmt = $pdo->query($categories_query);
    $raw_categories = $categories_stmt->fetchAll();

    // Use simple indexed array and manual deduplication
    $categories = [];
    $seen_ids = [];

    foreach ($raw_categories as $category_row) {
        $category_id = $category_row['id'];

        // Manual deduplication check
        if (!in_array($category_id, $seen_ids)) {
            $categories[] = [
                'id' => $category_id,
                'name' => $category_row['name'],
                'icon' => $category_row['icon'],
                'creator_id' => $category_row['category_creator_id'],
                'subcategories' => []
            ];
            $seen_ids[] = $category_id;
        }
    }

    // Get subcategories for each category
    foreach ($categories as $index => &$category) {
        $category_id = $category['id'];

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

        $categories[$index]['subcategories'] = $subcategories;
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
    $categories = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="flex-between mb-20">
        <h2 style="font-size: 24px; color: #2d3748;">
            Knowledge Categories (Simple Array - <?php echo count($categories); ?> total)
        </h2>
        <a href="add_category.php" class="btn btn-success">+ Add Category</a>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔒</div>
            <div class="empty-state-text">
                No categories found.
            </div>
        </div>
    <?php else: ?>
        <div class="category-list">
            <?php foreach ($categories as $index => $category): ?>
                <div class="category-item">
                    <div class="category-header">
                        <div class="category-name">
                            <?php if ($category['icon']): ?>
                                <span><?php echo htmlspecialchars($category['icon']); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                            <span style="color: #666; font-size: 12px;">(Array Index: <?php echo $index; ?>, ID: <?php echo $category['id']; ?>)</span>
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