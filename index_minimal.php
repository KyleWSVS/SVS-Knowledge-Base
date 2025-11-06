<?php
/**
 * Minimal Home Page - Category Duplication Fix Test
 * Using simplest possible approach
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = 'Home (Minimal)';

try {
    // Get categories using the simplest possible approach
    $categories_query = "SELECT id, name, icon FROM categories ORDER BY name ASC";
    $categories_stmt = $pdo->query($categories_query);
    $all_categories = $categories_stmt->fetchAll();

    echo "<h1>DEBUG: Raw Categories Query</h1>";
    echo "Query returned " . count($all_categories) . " results:<br>";
    foreach ($all_categories as $cat) {
        echo "ID: {$cat['id']}, Name: {$cat['name']}<br>";
    }

    // Build categories array
    $categories = [];
    foreach ($all_categories as $cat) {
        $categories[$cat['id']] = [
            'id' => $cat['id'],
            'name' => $cat['name'],
            'icon' => $cat['icon'],
            'subcategories' => []
        ];
    }

    echo "<h1>DEBUG: Categories Array After Building</h1>";
    echo "Array has " . count($categories) . " items:<br>";
    foreach ($categories as $category_id => $category) {
        echo "Key: $category_id -> {$category['name']}<br>";
    }

    // Get subcategories for each category
    foreach ($categories as $category_id => &$category) {
        $subcategories_query = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC";
        $subcategories_stmt = $pdo->prepare($subcategories_query);
        $subcategories_stmt->execute([$category_id]);
        $subcategories = $subcategories_stmt->fetchAll();

        $category['subcategories'] = $subcategories;
    }

    echo "<h1>DEBUG: Final Categories Array</h1>";
    echo "Final array has " . count($categories) . " categories:<br>";
    foreach ($categories as $category) {
        echo "Category: {$category['name']} has " . count($category['subcategories']) . " subcategories<br>";
        foreach ($category['subcategories'] as $sub) {
            echo "  - {$sub['name']}<br>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

?>

<hr>

<h1>Actual Display</h1>

<?php if (empty($categories)): ?>
    <p>No categories found.</p>
<?php else: ?>
    <p>Displaying <?php echo count($categories); ?> categories:</p>

    <?php foreach ($categories as $category): ?>
        <div style="border: 2px solid #000; margin: 10px; padding: 10px; background: #f0f0f0;">
            <h2><?php echo htmlspecialchars($category['name']); ?></h2>
            <?php if ($category['icon']): ?>
                <div>Icon: <?php echo htmlspecialchars($category['icon']); ?></div>
            <?php endif; ?>
            <div>ID: <?php echo $category['id']; ?></div>

            <h3>Subcategories:</h3>
            <?php if (empty($category['subcategories'])): ?>
                <p>No subcategories.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($category['subcategories'] as $subcategory): ?>
                        <li><?php echo htmlspecialchars($subcategory['name']); ?> (ID: <?php echo $subcategory['id']; ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>