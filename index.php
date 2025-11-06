<?php
/**
 * Home Page - Categories and Subcategories Display
 * Fixed version without PHP references to eliminate duplication issues
 * Last updated: 2025-11-03 (Subcategory visibility controls added)
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Load training helpers if available
if (file_exists('includes/training_helpers.php')) {
    require_once 'includes/training_helpers.php';
}

// Fallback functions for training permissions
if (!function_exists('can_create_categories')) {
    function can_create_categories() {
        return is_admin() || is_super_admin();
    }
}
if (!function_exists('can_create_subcategories')) {
    function can_create_subcategories() {
        return is_admin() || is_super_admin();
    }
}

$page_title = 'Home';

try {
    // Check if user is super user
    $is_super_user = is_super_admin();
    $current_user_id = $_SESSION['user_id'];

    // Check if visibility columns exist in database
    $visibility_columns_exist = false;
    $subcategory_visibility_columns_exist = false;
    $pinned_categories_table_exists = false;
    try {
        $test_query = $pdo->query("SELECT visibility FROM categories LIMIT 1");
        $visibility_columns_exist = true;
    } catch (PDOException $e) {
        // Visibility columns don't exist yet
        $visibility_columns_exist = false;
    }
    try {
        $test_query = $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
        $subcategory_visibility_columns_exist = true;
    } catch (PDOException $e) {
        // Subcategory visibility columns don't exist yet
        $subcategory_visibility_columns_exist = false;
    }
    try {
        $test_query = $pdo->query("SHOW TABLES LIKE 'user_pinned_categories'");
        $pinned_categories_table_exists = $test_query->rowCount() > 0;
    } catch (PDOException $e) {
        // Pinned categories table doesn't exist yet
        $pinned_categories_table_exists = false;
    }

    // Fetch pinned category IDs for current user
    $pinned_category_ids = [];
    try {
        // Try to fetch pinned categories (table will be created on first pin action if doesn't exist)
        $stmt = $pdo->prepare("SELECT category_id FROM user_pinned_categories WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$current_user_id]);
        $pinned_results = $stmt->fetchAll();
        foreach ($pinned_results as $row) {
            $pinned_category_ids[] = $row['category_id'];
        }
        $pinned_categories_table_exists = true;
    } catch (PDOException $e) {
        // Table doesn't exist yet, that's okay - it will be created on first pin
        $pinned_categories_table_exists = true; // Always show the button
        $pinned_category_ids = [];
    }

    if ($is_super_user) {
        // Super Admins can see everything including it_only categories
        if ($visibility_columns_exist) {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note FROM categories ORDER BY name ASC";
        } else {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
        }
        $categories_stmt = $pdo->query($categories_query);
        $raw_categories = $categories_stmt->fetchAll();
    } elseif (is_admin()) {
        // Normal Admins can see everything except it_only categories
        if ($visibility_columns_exist) {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note FROM categories WHERE visibility != 'it_only' ORDER BY name ASC";
        } else {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
        }
        $categories_stmt = $pdo->query($categories_query);
        $raw_categories = $categories_stmt->fetchAll();
    } else {
        // Regular users can only see public categories or restricted categories they have access to
        // They cannot see 'it_only' or 'hidden' categories
        if ($visibility_columns_exist) {
            $categories_query = "
                SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note
                FROM categories
                WHERE visibility = 'public'
                OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL))
                ORDER BY name ASC
            ";
            $categories_stmt = $pdo->prepare($categories_query);
            $categories_stmt->execute(['%"' . $current_user_id . '"%']);
        } else {
            // Old database - show all categories (assume all are public)
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
            $categories_stmt = $pdo->query($categories_query);
        }
        $raw_categories = $categories_stmt->fetchAll();
    }

    // Build categories array without using references
    $categories = [];
    $seen_ids = [];

    foreach ($raw_categories as $category_row) {
        $category_id = $category_row['id'];

        // Skip if we've already seen this ID
        if (in_array($category_id, $seen_ids)) {
            error_log("SKIPPING DUPLICATE CATEGORY ID: $category_id");
            continue;
        }

        // Add to categories array
        $categories[] = [
            'id' => $category_id,
            'name' => $category_row['name'],
            'icon' => $category_row['icon'],
            'creator_id' => $category_row['category_creator_id'],
            'visibility' => $category_row['visibility'] ?? 'public',
            'allowed_users' => $category_row['allowed_users'] ?? null,
            'visibility_note' => $category_row['visibility_note'] ?? null,
            'subcategories' => []
        ];
        $seen_ids[] = $category_id;
    }

    // Get subcategories without using references
    $final_categories = [];
    foreach ($categories as $index => $category) {
        $category_id = $category['id'];

        // Get subcategories with visibility filtering
        if ($is_super_user) {
            // Super Admins can see all subcategories including it_only
            if ($subcategory_visibility_columns_exist) {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count,
                           s.visibility, s.allowed_users, s.visibility_note
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
            } else {
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
            }
        } elseif (is_admin()) {
            // Normal Admins can see all subcategories except it_only
            if ($subcategory_visibility_columns_exist) {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count,
                           s.visibility, s.allowed_users, s.visibility_note
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.category_id = ? AND s.visibility != 'it_only'
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id]);
                $subcategories = $subcategories_stmt->fetchAll();
            } else {
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
            }
        } else {
            // Regular users can only see accessible subcategories
            // They cannot see 'it_only' or 'hidden' subcategories
            if ($subcategory_visibility_columns_exist && $visibility_columns_exist) {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(DISTINCT p.id) AS post_count,
                           s.visibility, s.allowed_users, s.visibility_note
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    JOIN categories c ON s.category_id = c.id
                    WHERE s.category_id = ?
                    AND (c.visibility = 'public'
                         OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (s.visibility = 'public'
                         OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%']);
                $subcategories = $subcategories_stmt->fetchAll();
            } else {
                // Old database - show all subcategories
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
            }
        }

        // Create completely new category object
        $final_categories[] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'icon' => $category['icon'],
            'creator_id' => $category['creator_id'],
            'visibility' => $category['visibility'] ?? 'public',
            'allowed_users' => $category['allowed_users'] ?? null,
            'visibility_note' => $category['visibility_note'] ?? null,
            'subcategories' => $subcategories
        ];
    }

    $categories = $final_categories;

    // TRAINING CONTENT FILTERING
    // If user is a training user, only show assigned training content
    if (function_exists('is_training_user') && is_training_user()) {
        // Get the user's assigned content IDs
        $assigned_content = function_exists('get_user_assigned_content_ids')
            ? get_user_assigned_content_ids($pdo, $current_user_id)
            : ['category' => [], 'subcategory' => [], 'post' => []];

        $assigned_categories = $assigned_content['category'] ?? [];
        $assigned_subcategories = $assigned_content['subcategory'] ?? [];
        $assigned_posts = $assigned_content['post'] ?? [];

        // If posts are assigned, also include their parent categories and subcategories
        if (!empty($assigned_posts)) {
            try {
                $post_ids_str = implode(',', array_map('intval', $assigned_posts));
                $parent_query = $pdo->query("
                    SELECT DISTINCT s.id as subcategory_id, s.category_id
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    WHERE p.id IN ($post_ids_str)
                ");
                $parent_results = $parent_query->fetchAll();

                foreach ($parent_results as $parent) {
                    // Add subcategory to assigned list if not already there
                    if (!in_array($parent['subcategory_id'], $assigned_subcategories)) {
                        $assigned_subcategories[] = $parent['subcategory_id'];
                    }
                    // Add category to assigned list if not already there
                    if (!in_array($parent['category_id'], $assigned_categories)) {
                        $assigned_categories[] = $parent['category_id'];
                    }
                }
            } catch (PDOException $e) {
                // If query fails, just use assigned categories/subcategories as-is
            }
        }

        // Filter categories to only show assigned ones
        $categories = array_filter($categories, function($category) use ($assigned_categories) {
            return in_array($category['id'], $assigned_categories);
        });

        // Filter subcategories within each category
        foreach ($categories as &$category) {
            if (!empty($category['subcategories'])) {
                $category['subcategories'] = array_filter($category['subcategories'], function($subcategory) use ($assigned_subcategories) {
                    return in_array($subcategory['id'], $assigned_subcategories);
                });
            }
        }

        // Re-index array
        $categories = array_values($categories);
    }

    // Sort categories: pinned first (by pin order), then alphabetically
    uasort($categories, function($a, $b) use ($pinned_category_ids) {
        $a_pinned = in_array($a['id'], $pinned_category_ids);
        $b_pinned = in_array($b['id'], $pinned_category_ids);

        // If one is pinned and the other isn't, pinned comes first
        if ($a_pinned !== $b_pinned) {
            return $b_pinned <=> $a_pinned;
        }

        // If both are pinned, sort by pin order
        if ($a_pinned && $b_pinned) {
            $a_pin_index = array_search($a['id'], $pinned_category_ids);
            $b_pin_index = array_search($b['id'], $pinned_category_ids);
            return $a_pin_index <=> $b_pin_index;
        }

        // If neither is pinned, sort alphabetically
        return strcasecmp($a['name'], $b['name']);
    });

    // Reset array keys to sequential indices
    $categories = array_values($categories);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
    $categories = [];
}

include 'includes/header.php';
?>

<div class="container">
    <!-- Enhanced Search Bar -->
    <div class="card" style="margin-bottom: 20px; position: relative;">
        <form id="searchForm" method="GET" action="search_working.php" style="display: flex; gap: 10px;">
            <input
                type="text"
                id="searchInput"
                name="q"
                class="form-input"
                placeholder="Search posts, categories, subcategories..."
                style="flex: 1; margin: 0;"
                autocomplete="off"
            >
            <button type="submit" class="btn btn-primary" style="margin: 0;">🔍 Search</button>
        </form>

        <!-- Autocomplete Dropdown -->
        <div id="autocompleteDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; max-height: 300px; overflow-y: auto;">
            <!-- Results will be inserted here by JavaScript -->
        </div>
    </div>

    <div class="flex-between mb-20">
        <h2 style="font-size: 24px; color: #2d3748;">Knowledge Categories</h2>
        <?php if (can_create_categories()): ?>
            <a href="add_category.php" class="btn btn-success">+ Add Category</a>
        <?php endif; ?>
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
            $first_unpinned = true;
            foreach ($categories as $category):
                $is_pinned = $pinned_categories_table_exists && in_array($category['id'], $pinned_category_ids);

                // Add a "Pinned Categories" divider before first unpinned category
                if (!$is_pinned && $first_unpinned && count($pinned_category_ids) > 0):
                    $first_unpinned = false;
                    echo '<div style="margin: 20px 0 10px 0; padding: 10px 0; border-top: 2px solid #e2e8f0; text-align: center; color: #a0aec0; font-size: 12px; font-weight: 500; text-transform: uppercase;">Other Categories</div>';
                endif;
                if ($is_pinned):
                    $first_unpinned = false;
                endif;
            ?>
                <div class="category-item" <?php echo $is_pinned ? 'style="border-left: 4px solid #fbbf24; background: rgba(251, 191, 36, 0.02);"' : ''; ?>>
                    <div class="category-header">
                        <div class="category-name">
                            <?php if ($category['icon']): ?>
                                <span><?php echo htmlspecialchars($category['icon']); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                            <?php if (($is_super_user || is_admin()) && $visibility_columns_exist): ?>
                                <?php
                                $visibility_colors = [
                                    'public' => '#48bb78',
                                    'hidden' => '#f56565',
                                    'restricted' => '#ed8936',
                                    'it_only' => '#dc3545'
                                ];
                                $visibility_labels = [
                                    'public' => '🌐 Public',
                                    'hidden' => '🚫 Hidden',
                                    'restricted' => '👥 Restricted',
                                    'it_only' => '🔒 IT Only'
                                ];
                                $cat_visibility = $category['visibility'] ?? 'public';
                                ?>
                                <span style="color: <?php echo $visibility_colors[$cat_visibility] ?? '#666'; ?>; font-size: 11px; margin-left: 8px; padding: 2px 6px; background: rgba(0,0,0,0.1); border-radius: 3px;">
                                    <?php echo $visibility_labels[$cat_visibility] ?? 'Unknown'; ?>
                                </span>
                                <?php if (!empty($category['visibility_note'])): ?>
                                    <span style="color: #666; font-size: 10px; margin-left: 4px;" title="<?php echo htmlspecialchars($category['visibility_note']); ?>">📝</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="card-actions">
                            <?php if ($pinned_categories_table_exists): ?>
                                <button class="btn btn-small" style="background: <?php echo $is_pinned ? '#fbbf24' : '#e2e8f0'; ?>; color: <?php echo $is_pinned ? 'black' : '#4a5568'; ?>; border: none; cursor: pointer;" onclick="togglePinCategory(<?php echo $category['id']; ?>, this)" title="<?php echo $is_pinned ? 'Unpin category' : 'Pin category'; ?>">
                                    <?php echo $is_pinned ? '📌' : '📍'; ?> <?php echo $is_pinned ? 'Unpin' : 'Pin'; ?>
                                </button>
                            <?php endif; ?>
                            <?php if (can_create_subcategories()): ?>
                                <a href="add_subcategory.php?category_id=<?php echo $category['id']; ?>" class="btn btn-primary btn-small">+ Add Subcategory</a>
                            <?php endif; ?>
                            <?php if ($is_super_user || is_admin()): ?>
                                <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-warning btn-small">Edit</a>
                                <a href="delete_category.php?id=<?php echo $category['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure? This will delete the category and ALL subcategories, posts, and replies under it. This cannot be undone.');">Delete</a>
                            <?php else: ?>
                                <a href="request_edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-warning btn-small">Request Edit</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (empty($category['subcategories'])): ?>
                        <div style="color: #a0aec0; font-style: italic; font-size: 14px;">
                            <?php if (can_create_subcategories()): ?>
                                No subcategories yet. Click "Add Subcategory" to create one.
                            <?php else: ?>
                                No subcategories yet.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="subcategory-list">
                            <?php foreach ($category['subcategories'] as $subcategory): ?>
                                <div class="subcategory-item">
                                    <a href="subcategory.php?id=<?php echo $subcategory['id']; ?>" class="subcategory-name">
                                        <?php echo htmlspecialchars($subcategory['name']); ?>
                                        <?php if (($is_super_user || is_admin()) && $subcategory_visibility_columns_exist): ?>
                                            <?php
                                            $visibility_colors = [
                                                'public' => '#48bb78',
                                                'hidden' => '#f56565',
                                                'restricted' => '#ed8936',
                                                'it_only' => '#dc3545'
                                            ];
                                            $visibility_labels = [
                                                'public' => '🌐',
                                                'hidden' => '🚫',
                                                'restricted' => '👥',
                                                'it_only' => '🔒'
                                            ];
                                            $subcat_visibility = $subcategory['visibility'] ?? 'public';
                                            ?>
                                            <span style="color: <?php echo $visibility_colors[$subcat_visibility] ?? '#666'; ?>; font-size: 10px; margin-left: 6px;" title="<?php echo ucfirst($subcat_visibility); ?> subcategory">
                                                <?php echo $visibility_labels[$subcat_visibility] ?? '?'; ?>
                                            </span>
                                            <?php if (!empty($subcategory['visibility_note'])): ?>
                                                <span style="color: #666; font-size: 9px; margin-left: 2px;" title="<?php echo htmlspecialchars($subcategory['visibility_note']); ?>">📝</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
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

<script>
// Basic Search Autocomplete
let searchTimeout;
let currentAutocompleteResults = [];

// Search Autocomplete
document.getElementById('searchInput').addEventListener('input', function(e) {
    const query = e.target.value.trim();
    const dropdown = document.getElementById('autocompleteDropdown');

    // Clear previous timeout
    clearTimeout(searchTimeout);

    if (query.length < 2) {
        dropdown.style.display = 'none';
        return;
    }

    // Debounce search (wait 300ms after user stops typing)
    searchTimeout = setTimeout(() => {
        performAutocompleteSearch(query);
    }, 300);
});

// Hide autocomplete when clicking outside
document.addEventListener('click', function(e) {
    const searchContainer = document.querySelector('.card[style*="position: relative"]');
    if (!searchContainer.contains(e.target)) {
        document.getElementById('autocompleteDropdown').style.display = 'none';
    }
});

// Keyboard navigation for autocomplete
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    const dropdown = document.getElementById('autocompleteDropdown');
    const results = currentAutocompleteResults;

    if (dropdown.style.display === 'none' || results.length === 0) return;

    let selectedIndex = -1;
    const items = dropdown.querySelectorAll('.autocomplete-item');

    // Find currently selected item
    for (let i = 0; i < items.length; i++) {
        if (items[i].classList.contains('selected')) {
            selectedIndex = i;
            break;
        }
    }

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % items.length;
            updateSelectedAutocompleteItem(items, selectedIndex);
            break;

        case 'ArrowUp':
            e.preventDefault();
            selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
            updateSelectedAutocompleteItem(items, selectedIndex);
            break;

        case 'Enter':
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                items[selectedIndex].click();
            } else {
                document.getElementById('searchForm').submit();
            }
            break;

        case 'Escape':
            dropdown.style.display = 'none';
            break;
    }
});

function updateSelectedAutocompleteItem(items, selectedIndex) {
    // Remove selected class from all items
    items.forEach(item => item.classList.remove('selected'));

    // Add selected class to current item
    if (items[selectedIndex]) {
        items[selectedIndex].classList.add('selected');

        // Update input value with selected item's title
        const titleElement = items[selectedIndex].querySelector('.autocomplete-title');
        if (titleElement) {
            document.getElementById('searchInput').value = titleElement.textContent;
        }
    }
}

function performAutocompleteSearch(query) {
    fetch('search_autocomplete.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            currentAutocompleteResults = data.results || [];
            displayAutocompleteResults(currentAutocompleteResults);
        })
        .catch(error => {
            console.error('Autocomplete search error:', error);
            document.getElementById('autocompleteDropdown').style.display = 'none';
        });
}

function displayAutocompleteResults(results) {
    const dropdown = document.getElementById('autocompleteDropdown');

    if (results.length === 0) {
        dropdown.style.display = 'none';
        return;
    }

    let html = '';
    results.forEach(result => {
        const typeIcon = getTypeIcon(result.type);
        const typeColor = getTypeColor(result.type);

        html += `
            <div class="autocomplete-item" onclick="selectAutocompleteResult('${result.url}')" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; transition: background-color 0.2s;">
                <div style="font-size: 18px; color: ${typeColor};">${typeIcon}</div>
                <div style="flex: 1;">
                    <div class="autocomplete-title" style="font-weight: 500; color: #2d3748; margin-bottom: 2px;">${result.title}</div>
                    <div style="font-size: 12px; color: #718096;">${result.subtitle}</div>
                </div>
                <div style="font-size: 12px; color: #a0aec0; text-transform: uppercase; font-weight: 500;">${result.type}</div>
            </div>
        `;
    });

    dropdown.innerHTML = html;
    dropdown.style.display = 'block';

    // Add hover effects
    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f7fafc';
        });
        item.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
        });
    });
}

function getTypeIcon(type) {
    const icons = {
        'category': '📁',
        'subcategory': '📂',
        'post': '📄'
    };
    return icons[type] || '📄';
}

function getTypeColor(type) {
    const colors = {
        'category': '#667eea',
        'subcategory': '#4299e1',
        'post': '#48bb78'
    };
    return colors[type] || '#718096';
}

function selectAutocompleteResult(url) {
    window.location.href = url;
}

// Original pin toggle function
function togglePinCategory(categoryId, buttonElement) {
    const isPinned = buttonElement.textContent.includes('Unpin');
    const action = isPinned ? 'unpin' : 'pin';

    // Show loading state
    const originalText = buttonElement.textContent;
    buttonElement.textContent = '⏳ Loading...';
    buttonElement.disabled = true;

    // Send AJAX request
    fetch('toggle_pin_category.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'category_id=' + categoryId + '&action=' + action
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to reflect changes
            window.location.reload();
        } else {
            // Show error and restore button
            alert('Error: ' + (data.error || 'Failed to toggle pin'));
            buttonElement.textContent = originalText;
            buttonElement.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: Failed to toggle pin');
        buttonElement.textContent = originalText;
        buttonElement.disabled = false;
    });
}
</script>

<style>
.autocomplete-item.selected {
    background-color: #e6f3ff !important;
}

.autocomplete-item:hover {
    background-color: #f7fafc;
}

#autocompleteDropdown {
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php include 'includes/footer.php'; ?>