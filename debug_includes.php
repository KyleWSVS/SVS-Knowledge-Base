<?php
/**
 * Debug includes to find what's causing duplication
 */

echo "<h1>Debug Include Process</h1>";

// Step 1: Just basic includes
echo "<h2>Step 1: Basic includes only</h2>";
require_once 'includes/db_connect.php';

echo "Database connected successfully<br>";

// Step 2: Add auth check
echo "<h2>Step 2: Adding auth check</h2>";
require_once 'includes/auth_check.php';

echo "Auth check completed<br>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";

// Step 3: Run our category logic
echo "<h2>Step 3: Running category logic</h2>";

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

    echo "Categories after initial processing: " . count($categories) . "<br>";
    foreach ($categories as $index => $cat) {
        echo "Index $index: ID {$cat['id']} = {$cat['name']}<br>";
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
            ORDER BY s.name ASC
        ";
        $subcategories_stmt = $pdo->prepare($subcategories_query);
        $subcategories_stmt->execute([$category_id]);
        $subcategories = $subcategories_stmt->fetchAll();

        $categories[$index]['subcategories'] = $subcategories;
    }

    echo "Categories after subcategories: " . count($categories) . "<br>";
    foreach ($categories as $index => $cat) {
        echo "Index $index: ID {$cat['id']} = {$cat['name']}<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Step 4: Add config include if it exists
echo "<h2>Step 4: Adding config</h2>";
if (file_exists('config.php')) {
    require_once 'config.php';
    echo "Config included<br>";
} else {
    echo "No config.php found<br>";
}

echo "Categories after config: " . count($categories) . "<br>";

// Step 5: Test if something in the main logic is duplicating
echo "<h2>Step 5: Check for any loops or includes that might duplicate</h2>";

// Check if any variables are being overwritten
var_dump($categories);

// Step 6: Display the categories
echo "<h2>Final Display</h2>";
foreach ($categories as $index => $category) {
    echo "<div style='border: 1px solid #000; margin: 10px; padding: 10px;'>";
    echo "<strong>{$category['name']}</strong> (Index: $index, ID: {$category['id']})<br>";
    foreach ($category['subcategories'] as $sub) {
        echo "- {$sub['name']}<br>";
    }
    echo "</div>";
}

?>