<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><?php echo SITE_NAME; ?></h1>
            <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
                <?php
                require_once 'user_helpers.php';
                require_once 'db_connect.php';

                // Load training helpers if available
                if (file_exists('includes/training_helpers.php')) {
                    require_once 'includes/training_helpers.php';
                }

                // Fallback function in case training_helpers.php doesn't exist
                if (!function_exists('is_training_user')) {
                    function is_training_user() {
                        return isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'training';
                    }
                }

                // Fallback function for progress calculation
                if (!function_exists('get_overall_training_progress')) {
                    function get_overall_training_progress($pdo, $user_id) {
                        return [
                            'percentage' => 0,
                            'completed_items' => 0,
                            'total_items' => 0,
                            'in_progress_items' => 0,
                            'completed_courses' => 0,
                            'total_courses' => 0
                        ];
                    }
                }

                // Fallback function for progress bar visibility
                if (!function_exists('should_show_training_progress')) {
                    function should_show_training_progress($pdo, $user_id) {
                        return isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'training';
                    }
                }

                // Get admin notification counts
                $pending_edit_requests = 0;
                $unresolved_bugs = 0;

                if (is_admin()) {
                    try {
                        // Get pending edit requests count
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM edit_requests WHERE status = 'pending'");
                        $result = $stmt->fetch();
                        $pending_edit_requests = $result['count'] ?? 0;

                        // Get unresolved bugs count
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM bug_reports WHERE status != 'resolved'");
                        $result = $stmt->fetch();
                        $unresolved_bugs = $result['count'] ?? 0;
                    } catch (PDOException $e) {
                        // If tables don't exist, just show 0
                        $pending_edit_requests = 0;
                        $unresolved_bugs = 0;
                    }
                }
                ?>
                <div class="user-info" style="display: flex; align-items: center; gap: 12px;">
                    <!-- Admin Notifications -->
                    <?php if (is_admin() && $pending_edit_requests > 0): ?>
                    <a href="manage_edit_requests.php" style="display: flex; align-items: center; gap: 4px; background: #ffc107; color: #212529; padding: 4px 8px; border-radius: 12px; text-decoration: none; font-size: 11px; font-weight: 500;" title="Pending Edit Requests">
                        <span>📝</span>
                        <span><?php echo $pending_edit_requests; ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (is_super_admin() && $unresolved_bugs > 0): ?>
                    <a href="bug_report.php" style="display: flex; align-items: center; gap: 4px; background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; text-decoration: none; font-size: 11px; font-weight: 500;" title="Unresolved Bugs">
                        <span>🐛</span>
                        <span><?php echo $unresolved_bugs; ?></span>
                    </a>
                    <?php endif; ?>

                    <!-- Admin Menu Toggle (Admin Only) -->
                    <?php if (is_admin()): ?>
                    <div class="developer-menu-toggle" onclick="toggleDevMenu()" title="Admin Menu">⚙️</div>
                    <?php endif; ?>

                    <!-- User Info -->
                    <span class="user-name" style="color: <?php echo htmlspecialchars(get_user_color()); ?>">
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        <?php
                        $role_colors = [
                            'Super Admin' => '#dc3545',
                            'Admin' => '#28a745',
                            'User' => '#6c757d',
                            'Training' => '#17a2b8'
                        ];
                        $current_role = get_user_role_display();
                        $role_color = $role_colors[$current_role] ?? '#6c757d';
                        ?>
                        <span style="background: <?php echo $role_color; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;"><?php echo htmlspecialchars($current_role); ?></span>
                    </span>

                    <!-- Training Progress Bar (Training Users and Admins with Active Training) -->
                    <?php if (should_show_training_progress($pdo, $_SESSION['user_id'])): ?>
                        <div class="training-progress-header" onclick="window.location='training_dashboard.php'">
                            <div class="progress-info" id="training-progress-info">
                                <span class="progress-icon">🎓</span>
                                <span class="progress-text">Training: <span id="progress-percentage">0</span>%</span>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" id="progress-bar-fill" style="width: 0%;"></div>
                                </div>
                                <span class="progress-detail" id="progress-detail"><span id="completed-items">0</span> of <span id="total-items">0</span> items</span>
                            </div>
                        </div>

                        <script>
                        // Live training progress updates
                        let trainingProgressInterval = null;

                        function updateTrainingProgress() {
                            fetch('includes/training_helpers.php?action=get_training_progress&user_id=<?php echo $_SESSION['user_id']; ?>')
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const percentage = data.percentage || 0;
                                        const completedItems = data.completed_items || 0;
                                        const totalItems = data.total_items || 0;

                                        // Update percentage
                                        document.getElementById('progress-percentage').textContent = percentage;

                                        // Update progress bar
                                        const barFill = document.getElementById('progress-bar-fill');
                                        barFill.style.width = percentage + '%';

                                        // Update progress bar color based on percentage
                                        if (percentage >= 75) {
                                            barFill.style.background = '#28a745';
                                        } else if (percentage >= 50) {
                                            barFill.style.background = '#ffc107';
                                        } else {
                                            barFill.style.background = '#dc3545';
                                        }

                                        // Update progress detail
                                        document.getElementById('completed-items').textContent = completedItems;
                                        document.getElementById('total-items').textContent = totalItems;

                                        // Auto-stop when 100% complete
                                        if (percentage >= 100 && trainingProgressInterval) {
                                            clearInterval(trainingProgressInterval);
                                            trainingProgressInterval = null;
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error updating training progress:', error);
                                });
                        }

                        // Start live updates
                        updateTrainingProgress();
                        trainingProgressInterval = setInterval(updateTrainingProgress, 30000); // Update every 30 seconds

                        // Clean up on page unload
                        window.addEventListener('beforeunload', function() {
                            if (trainingProgressInterval) {
                                clearInterval(trainingProgressInterval);
                            }
                        });
                        </script>
                    <?php endif; ?>

                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

  <?php
  // Include admin menu dropdown logic
  if (is_admin()):

    // Get all PHP files in current directory
    function getPhpFilesDropdown($dir) {
        $files = [];
        $excludeFiles = [
            'developer_menu.php',
            'logout.php',
            'includes/auth_check.php',
            'includes/db_connect.php'
        ];

        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." &&
                    pathinfo($entry, PATHINFO_EXTENSION) === 'php' &&
                    !in_array($entry, $excludeFiles) &&
                    !str_starts_with($entry, '.')) {

                    $filePath = $dir . '/' . $entry;
                    if (is_file($filePath)) {
                        $files[] = [
                            'name' => $entry,
                            'path' => $entry,
                            'type' => getFileTypeDropdown($entry)
                        ];
                    }
                }
            }
            closedir($handle);
        }

        usort($files, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $files;
    }

    function getFileTypeDropdown($filename) {
        $filename = strtolower($filename);

        if (str_contains($filename, 'index') || str_contains($filename, 'home')) {
            return '🏠';
        } elseif (str_contains($filename, 'add_') || str_contains($filename, 'create') || str_contains($filename, 'new')) {
            return '➕';
        } elseif (str_contains($filename, 'edit') || str_contains($filename, 'update') || str_contains($filename, 'modify')) {
            return '✏️';
        } elseif (str_contains($filename, 'delete') || str_contains($filename, 'remove')) {
            return '🗑️';
        } elseif (str_contains($filename, 'search') || str_contains($filename, 'find')) {
            return '🔍';
        } elseif (str_contains($filename, 'export') || str_contains($filename, 'pdf') || str_contains($filename, 'download')) {
            return '📄';
        } elseif (str_contains($filename, 'category') || str_contains($filename, 'sub')) {
            return '📂';
        } elseif (str_contains($filename, 'post') || str_contains($filename, 'reply')) {
            return '📝';
        } elseif (str_contains($filename, 'user') || str_contains($filename, 'login') || str_contains($filename, 'auth')) {
            return '👤';
        } elseif (str_contains($filename, 'file') || str_contains($filename, 'upload') || str_contains($filename, 'attachment')) {
            return '📎';
        } elseif (str_contains($filename, 'admin') || str_contains($filename, 'manage') || str_contains($filename, 'config')) {
            return '⚙️';
        } elseif (str_contains($filename, 'test') || str_contains($filename, 'debug')) {
            return '🧪';
        } else {
            return '📄';
        }
    }

    $mainFiles = getPhpFilesDropdown('.');
    $dbFiles = [];
    if (is_dir('database')) {
        $dbFiles = getPhpFilesDropdown('database');
    }

    $htmlFiles = [];
    if ($handle = opendir('.')) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." &&
                pathinfo($entry, PATHINFO_EXTENSION) === 'html' &&
                !str_starts_with($entry, '.')) {

                $htmlFiles[] = [
                    'name' => $entry,
                    'path' => $entry,
                    'type' => '🌐'
                ];
            }
        }
        closedir($handle);
    }
    sort($htmlFiles);
  ?>

  <!-- LinkedIn-style Developer Menu Dropdown -->
  <div class="dev-dropdown-overlay" id="devDropdownOverlay"></div>
  <div class="dev-dropdown-menu" id="devDropdownMenu" onclick="event.stopPropagation()">
    <div class="dev-dropdown-header">
      <h3>⚙️ Admin Menu</h3>
      <?php if (is_super_admin()): ?>
      <div class="dev-dropdown-stats">
        <span class="stat-badge"><?php echo count($mainFiles); ?> PHP</span>
        <span class="stat-badge"><?php echo count($dbFiles); ?> SQL</span>
        <span class="stat-badge"><?php echo count($htmlFiles); ?> HTML</span>
      </div>
      <?php endif; ?>
    </div>

    <div class="dev-dropdown-content">
      <div class="dev-dropdown-section">
        <div class="dev-section-title">🛠️ Admin Tools</div>
        <div class="dev-file-list">
          <a href="manage_users.php" class="dev-file-item">
            <span class="dev-file-icon">👥</span>
            <span class="dev-file-name">User Management</span>
          </a>
          <a href="manage_training_courses.php" class="dev-file-item">
            <span class="dev-file-icon">🎓</span>
            <span class="dev-file-name">Training Courses</span>
          </a>
          <a href="manage_quizzes.php" class="dev-file-item">
            <span class="dev-file-icon">📝</span>
            <span class="dev-file-name">Manage Quizzes</span>
          </a>
          <a href="training_dashboard.php" class="dev-file-item">
            <span class="dev-file-icon">📊</span>
            <span class="dev-file-name">Training Dashboard</span>
          </a>
          <a href="manage_edit_requests.php" class="dev-file-item">
            <span class="dev-file-icon">📝</span>
            <span class="dev-file-name">Edit Requests</span>
          </a>
          <a href="bug_report.php" class="dev-file-item">
            <span class="dev-file-icon">🐛</span>
            <span class="dev-file-name">Bug Report System</span>
          </a>
        </div>
      </div>

      <?php if (is_super_admin()): ?>
      <?php if (!empty($mainFiles)): ?>
      <div class="dev-dropdown-section">
        <div class="dev-section-title">📄 Main PHP Files</div>
        <div class="dev-file-list">
          <?php foreach ($mainFiles as $file): ?>
          <a href="<?php echo htmlspecialchars($file['path']); ?>" class="dev-file-item">
            <span class="dev-file-icon"><?php echo $file['type']; ?></span>
            <span class="dev-file-name"><?php echo htmlspecialchars($file['name']); ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($dbFiles)): ?>
      <div class="dev-dropdown-section">
        <div class="dev-section-title">🗄️ Database Files</div>
        <div class="dev-file-list">
          <?php foreach ($dbFiles as $file): ?>
          <a href="<?php echo htmlspecialchars($file['path']); ?>" class="dev-file-item">
            <span class="dev-file-icon">🗄️</span>
            <span class="dev-file-name"><?php echo htmlspecialchars($file['name']); ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($htmlFiles)): ?>
      <div class="dev-dropdown-section">
        <div class="dev-section-title">🌐 HTML Test Files</div>
        <div class="dev-file-list">
          <?php foreach ($htmlFiles as $file): ?>
          <a href="<?php echo htmlspecialchars($file['path']); ?>" class="dev-file-item">
            <span class="dev-file-icon">🌐</span>
            <span class="dev-file-name"><?php echo htmlspecialchars($file['name']); ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

