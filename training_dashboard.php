<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html><html><head><title>Training Dashboard</title>";
echo "<link rel='stylesheet' href='assets/css/style.css'>";
echo "<style>
.main-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 8px; margin-bottom: 20px; }
.header h1 { margin: 0 0 8px 0; font-size: 24px; }
.header p { margin: 0; opacity: 0.9; }
.progress-section { margin: 20px 0; }
.progress-bar { background: #e9ecef; border-radius: 8px; padding: 4px; overflow: hidden; margin: 10px 0; }
.progress-fill { background: linear-gradient(90deg, #667eea, #764ba2); height: 20px; border-radius: 6px; transition: width 0.3s ease; }
.course-card { border: 1px solid #ddd; padding: 16px; margin: 10px 0; border-radius: 8px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.course-card h3 { margin: 0 0 8px 0; color: #333; }
.course-card p { margin: 4px 0; color: #666; }
.error { color: #dc3545; background: #f8d7da; padding: 12px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
.success { color: #155724; background: #d4edda; padding: 12px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
.debug { background: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; margin: 10px 0; font-family: monospace; font-size: 12px; border-radius: 4px; }
.btn { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
.btn:hover { background: #5a6fd8; }
.btn-secondary { background: #6c757d; }
.btn-secondary:hover { background: #5a6268; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0; }
.stat-card { background: white; padding: 16px; border-radius: 8px; border-left: 4px solid #667eea; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.stat-number { font-size: 24px; font-weight: bold; color: #667eea; }
.stat-label { color: #666; font-size: 14px; margin-top: 4px; }
</style></head><body>";

echo "<div class='main-container'>";

// Load required files
require_once 'includes/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='error'>❌ No user ID in session</div>";
    echo "<a href='login.php' class='btn'>Login</a>";
    echo "</div></body></html>";
    exit;
}

// Load training helpers
if (!file_exists('includes/training_helpers.php')) {
    // Define fallback functions
    function is_training_user() {
        return isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'training';
    }
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
} else {
    require_once 'includes/training_helpers.php';
}

// Check if user is training user
if (!function_exists('is_training_user') || !is_training_user()) {
    echo "<div class='error'>❌ User is not a training user</div>";
    echo "<a href='index.php' class='btn'>Back to Home</a>";
    echo "</div></body></html>";
    exit;
}

// Database connection
require_once 'includes/db_connect.php';
global $pdo;

// Check training tables
$tables_exist = true;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM training_courses");
    $course_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM user_training_assignments");
    $assignment_count = $stmt->fetchColumn();

} catch (Exception $e) {
    $tables_exist = false;
}

// Main dashboard content
echo "<div class='header'>";
echo "<h1>🎓 Training Dashboard</h1>";
echo "<p>Welcome back, " . htmlspecialchars($_SESSION['user_name']) . "!</p>";
echo "</div>";

if (!$tables_exist) {
    echo "<div class='error'>";
    echo "<h3>⚠️ Training System Not Set Up</h3>";
    echo "<p>The training system tables are not available. Please contact an administrator to set up the training system.</p>";
    echo "</div>";
    echo "<a href='index.php' class='btn'>← Back to Home</a>";
    echo "</div></body></html>";
    exit;
}

// Get overall progress
try {
    $progress = get_overall_training_progress($pdo, $_SESSION['user_id']);

    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>" . $progress['percentage'] . "%</div>";
    echo "<div class='stat-label'>Overall Progress</div>";
    echo "</div>";

    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>" . $progress['completed_items'] . "</div>";
    echo "<div class='stat-label'>Items Completed</div>";
    echo "</div>";

    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>" . $progress['total_items'] . "</div>";
    echo "<div class='stat-label'>Total Items</div>";
    echo "</div>";

    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>" . $progress['completed_courses'] . "</div>";
    echo "<div class='stat-label'>Courses Completed</div>";
    echo "</div>";
    echo "</div>";

    echo "<div class='progress-section'>";
    echo "<h2>📊 Progress Details</h2>";
    echo "<div class='progress-bar'>";
    echo "<div class='progress-fill' style='width: " . $progress['percentage'] . "%;'></div>";
    echo "</div>";
    echo "<p><strong>" . $progress['percentage'] . "%</strong> Complete - " . $progress['completed_items'] . " of " . $progress['total_items'] . " items completed</p>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='error'>Error calculating progress: " . $e->getMessage() . "</div>";
}

// Get assigned courses
try {
    $stmt = $pdo->prepare("
        SELECT uta.*, tc.name, tc.description, tc.estimated_hours
        FROM user_training_assignments uta
        JOIN training_courses tc ON uta.course_id = tc.id
        WHERE uta.user_id = ? AND uta.status != 'completed'
        ORDER BY tc.name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>📚 Your Training Courses</h2>";

    if (empty($courses)) {
        echo "<p>No training courses assigned. Please contact an administrator.</p>";
    } else {
        foreach ($courses as $course) {
            echo "<div class='course-card'>";
            echo "<h3>" . htmlspecialchars($course['name']) . "</h3>";
            if (!empty($course['description'])) {
                echo "<p>" . htmlspecialchars($course['description']) . "</p>";
            }
            echo "<p><strong>Estimated Time:</strong> " . $course['estimated_hours'] . " hours</p>";
            echo "<p><strong>Status:</strong> " . ucfirst($course['status']) . "</p>";
            echo "<a href='index.php' class='btn'>View Training Materials</a>";
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>Error loading courses: " . $e->getMessage() . "</div>";
}

// Training history
try {
    $stmt = $pdo->prepare("
        SELECT uta.*, tc.name as course_name
        FROM user_training_assignments uta
        JOIN training_courses tc ON uta.course_id = tc.id
        WHERE uta.user_id = ? AND uta.status = 'completed'
        ORDER BY uta.completion_date DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completed_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($completed_courses)) {
        echo "<h2>✅ Completed Training</h2>";
        foreach ($completed_courses as $course) {
            echo "<div class='course-card' style='border-left: 4px solid #28a745;'>";
            echo "<h4>" . htmlspecialchars($course['course_name']) . "</h4>";
            echo "<p><strong>Completed:</strong> " . date('M j, Y \a\t g:i A', strtotime($course['completion_date'])) . "</p>";
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>Error loading training history: " . $e->getMessage() . "</div>";
}

echo "<div style='margin-top: 30px;'>";
echo "<a href='index.php' class='btn'>← Back to Home</a>";
echo "</div>";

echo "</div>"; // End container

echo "</body></html>";
?>