<?php
/**
 * Quiz Management Page
 * Admin interface for creating and managing training quizzes
 * Only accessible by admin users
 *
 * Created: 2025-11-06
 * Author: Claude Code Assistant
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'Quiz Management';
$success_message = '';
$error_message = '';

// Check if quiz tables exist
$quiz_tables_exist = false;
try {
    $pdo->query("SELECT id FROM training_quizzes LIMIT 1");
    $quiz_tables_exist = true;
} catch (PDOException $e) {
    $error_message = "Quiz tables don't exist. Please import the add_quiz_system.sql file first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz_tables_exist) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'create_quiz':
            $content_id = isset($_POST['content_id']) ? intval($_POST['content_id']) : 0;
            $content_type = isset($_POST['content_type']) ? $_POST['content_type'] : '';
            $quiz_title = isset($_POST['quiz_title']) ? trim($_POST['quiz_title']) : '';
            $quiz_description = isset($_POST['quiz_description']) ? trim($_POST['quiz_description']) : '';
            $passing_score = isset($_POST['passing_score']) ? intval($_POST['passing_score']) : 100;
            $time_limit = isset($_POST['time_limit']) && !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;

            // Validation
            if (empty($content_type) || $content_id <= 0) {
                $error_message = 'Please select training content for the quiz.';
            } elseif (empty($quiz_title)) {
                $error_message = 'Quiz title is required.';
            } elseif (strlen($quiz_title) > 255) {
                $error_message = 'Quiz title must be 255 characters or less.';
            } elseif ($passing_score < 0 || $passing_score > 100) {
                $error_message = 'Passing score must be between 0 and 100.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO training_quizzes
                        (content_id, content_type, quiz_title, quiz_description, passing_score, time_limit_minutes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$content_id, $content_type, $quiz_title, $quiz_description, $passing_score, $time_limit, $_SESSION['user_id']]);
                    $success_message = 'Quiz created successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Error creating quiz: ' . $e->getMessage();
                }
            }
            break;

        case 'edit_quiz':
            $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
            $quiz_title = isset($_POST['quiz_title']) ? trim($_POST['quiz_title']) : '';
            $quiz_description = isset($_POST['quiz_description']) ? trim($_POST['quiz_description']) : '';
            $passing_score = isset($_POST['passing_score']) ? intval($_POST['passing_score']) : 100;
            $time_limit = isset($_POST['time_limit']) && !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($quiz_id <= 0) {
                $error_message = 'Invalid quiz ID.';
            } elseif (empty($quiz_title)) {
                $error_message = 'Quiz title is required.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE training_quizzes
                        SET quiz_title = ?, quiz_description = ?, passing_score = ?,
                            time_limit_minutes = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$quiz_title, $quiz_description, $passing_score, $time_limit, $is_active, $quiz_id]);
                    $success_message = 'Quiz updated successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Error updating quiz: ' . $e->getMessage();
                }
            }
            break;

        case 'delete_quiz':
            $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;

            if ($quiz_id <= 0) {
                $error_message = 'Invalid quiz ID.';
            } else {
                try {
                    // Check if users have attempted this quiz
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_quiz_attempts WHERE quiz_id = ?");
                    $stmt->execute([$quiz_id]);
                    $attempt_count = $stmt->fetchColumn();

                    if ($attempt_count > 0) {
                        $error_message = 'Cannot delete quiz. Users have already attempted this quiz.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM training_quizzes WHERE id = ?");
                        $stmt->execute([$quiz_id]);
                        $success_message = 'Quiz deleted successfully!';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error deleting quiz: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Get data for display
$quizzes = [];
$training_content = [];

if ($quiz_tables_exist) {
    try {
        // Update quiz statistics manually (since we can't use triggers)
        $update_stats_stmt = $pdo->query("
            UPDATE quiz_statistics qs
            SET
                total_attempts = (
                    SELECT COUNT(*) FROM user_quiz_attempts uqa WHERE uqa.quiz_id = qs.quiz_id
                ),
                total_users = (
                    SELECT COUNT(DISTINCT uqa.user_id) FROM user_quiz_attempts uqa WHERE uqa.quiz_id = qs.quiz_id
                ),
                average_score = (
                    SELECT COALESCE(AVG(uqa.score), 0)
                    FROM user_quiz_attempts uqa
                    WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
                ),
                highest_score = (
                    SELECT COALESCE(MAX(uqa.score), 0)
                    FROM user_quiz_attempts uqa
                    WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
                ),
                lowest_score = (
                    SELECT COALESCE(MIN(uqa.score), 0)
                    FROM user_quiz_attempts uqa
                    WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
                ),
                pass_rate = (
                    SELECT COALESCE(
                        (SUM(CASE WHEN uqa.score >= tq.passing_score THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 0
                    )
                    FROM user_quiz_attempts uqa
                    JOIN training_quizzes tq ON uqa.quiz_id = tq.id
                    WHERE uqa.quiz_id = qs.quiz_id AND uqa.status IN ('completed', 'passed')
                )
        ");

        // Get quizzes with statistics
        $stmt = $pdo->query("
            SELECT tq.*, qs.*,
                   CASE tq.content_type
                       WHEN 'category' THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post' THEN p.title
                   END as content_name,
                   u.name as creator_name
            FROM training_quizzes tq
            LEFT JOIN quiz_statistics qs ON tq.id = qs.quiz_id
            LEFT JOIN training_course_content tcc ON tq.content_id = tcc.content_id AND tq.content_type = tcc.content_type
            LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
            LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
            LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
            LEFT JOIN users u ON tq.created_by = u.id
            ORDER BY tq.created_at DESC
        ");
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get training content for dropdown
        $stmt = $pdo->query("
            SELECT DISTINCT
                tcc.content_type,
                tcc.content_id,
                CASE tcc.content_type
                    WHEN 'category' THEN CONCAT('Category: ', c.name)
                    WHEN 'subcategory' THEN CONCAT('Subcategory: ', sc.name)
                    WHEN 'post' THEN CONCAT('Post: ', p.title)
                END as display_name
            FROM training_course_content tcc
            LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
            LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
            LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
            WHERE NOT EXISTS (
                SELECT 1 FROM training_quizzes tq
                WHERE tq.content_id = tcc.content_id AND tq.content_type = tcc.content_type
            )
            ORDER BY tcc.content_type, display_name
        ");
        $training_content = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = 'Error loading quiz data: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<style>
.quiz-management {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.quiz-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.quiz-title {
    color: #333;
    margin: 0 0 5px 0;
    font-size: 18px;
}

.quiz-meta {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.quiz-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin: 15px 0;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.stat-number {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

.quiz-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
    transition: background-color 0.2s;
}

.btn-primary { background: #667eea; color: white; }
.btn-primary:hover { background: #5a6fd8; }

.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }

.btn-warning { background: #ffc107; color: #212529; }
.btn-warning:hover { background: #e0a800; }

.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }

.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    position: relative;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.quiz-content-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    background-color: white;
}

.content-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    margin-left: 8px;
}

.content-badge.category { background: #e3f2fd; color: #1976d2; }
.content-badge.subcategory { background: #f3e5f5; color: #7b1fa2; }
.content-badge.post { background: #e8f5e8; color: #388e3c; }
</style>

<div class="quiz-management">
    <div class="section-header">
        <h1>📝 Quiz Management</h1>
        <p>Create and manage training quizzes for your knowledge base content</p>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if (!$quiz_tables_exist): ?>
        <div class="quiz-card">
            <h3>⚠️ Quiz System Not Set Up</h3>
            <p>The quiz system tables are not available. Please import the add_quiz_system.sql file first.</p>
            <a href="manage_training_courses.php" class="btn btn-secondary">← Back to Training Management</a>
        </div>
    <?php else: ?>
        <!-- Create New Quiz Section -->
        <div class="quiz-card">
            <h2>➕ Create New Quiz</h2>
            <?php if (empty($training_content)): ?>
                <p>All available training content already has quizzes assigned.</p>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="create_quiz">

                    <div class="form-group">
                        <label for="content">Training Content:</label>
                        <select name="content_type" id="content_type" class="quiz-content-select" required onchange="updateContentOptions()">
                            <option value="">Select Content Type</option>
                            <?php
                            $grouped_content = [];
                            foreach ($training_content as $content) {
                                $grouped_content[$content['content_type']][] = $content;
                            }

                            foreach ($grouped_content as $type => $items):
                                $type_label = ucfirst($type);
                                echo "<optgroup label='$type_label'>";
                                foreach ($items as $item):
                                    echo "<option value='{$item['content_type']}|{$item['content_id']}'>{$item['display_name']}</option>";
                                endforeach;
                                echo "</optgroup>";
                            endforeach;
                            ?>
                        </select>
                        <input type="hidden" name="content_id" id="content_id" required>
                    </div>

                    <div class="form-group">
                        <label for="quiz_title">Quiz Title:</label>
                        <input type="text" name="quiz_title" id="quiz_title" class="form-control" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="quiz_description">Quiz Description (Optional):</label>
                        <textarea name="quiz_description" id="quiz_description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="passing_score">Passing Score (%):</label>
                        <input type="number" name="passing_score" id="passing_score" class="form-control" min="0" max="100" value="80" required>
                    </div>

                    <div class="form-group">
                        <label for="time_limit">Time Limit (minutes, optional):</label>
                        <input type="number" name="time_limit" id="time_limit" class="form-control" min="1" placeholder="No time limit">
                    </div>

                    <button type="submit" class="btn btn-primary">Create Quiz</button>
                    <a href="manage_training_courses.php" class="btn btn-secondary">← Back</a>
                </form>
            <?php endif; ?>
        </div>

        <!-- Existing Quizzes Section -->
        <div class="quiz-card">
            <h2>📚 Existing Quizzes</h2>
            <?php if (empty($quizzes)): ?>
                <p>No quizzes have been created yet.</p>
            <?php else: ?>
                <?php foreach ($quizzes as $quiz): ?>
                    <div class="quiz-card" style="border-left: 4px solid <?php echo $quiz['is_active'] ? '#28a745' : '#dc3545'; ?>;">
                        <div class="quiz-header">
                            <div>
                                <h3 class="quiz-title">
                                    <?php echo htmlspecialchars($quiz['quiz_title'] ?? ''); ?>
                                    <?php if (!$quiz['is_active']): ?>
                                        <span style="color: #dc3545; font-size: 12px;">(Inactive)</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="quiz-meta">
                                    <span class="content-badge <?php echo $quiz['content_type']; ?>">
                                        <?php echo ucfirst($quiz['content_type']); ?>: <?php echo htmlspecialchars($quiz['content_name'] ?? 'Unknown Content'); ?>
                                    </span>
                                    • Passing Score: <?php echo $quiz['passing_score']; ?>%
                                    <?php if ($quiz['time_limit_minutes']): ?>
                                        • Time Limit: <?php echo $quiz['time_limit_minutes']; ?> minutes
                                    <?php endif; ?>
                                    • Created by <?php echo htmlspecialchars($quiz['creator_name'] ?? 'Unknown'); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($quiz['total_questions'] > 0): ?>
                            <div class="quiz-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $quiz['total_questions']; ?></div>
                                    <div class="stat-label">Questions</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $quiz['total_users_attempted'] ?? 0; ?></div>
                                    <div class="stat-label">Attempts</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $quiz['users_passed'] ?? 0; ?></div>
                                    <div class="stat-label">Passed</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $quiz['average_score'] ? round($quiz['average_score']) : 0; ?>%</div>
                                    <div class="stat-label">Avg Score</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="color: #6c757d; font-style: italic;">No questions added yet</p>
                        <?php endif; ?>

                        <div class="quiz-actions">
                            <a href="manage_quiz_questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-primary">
                                <?php echo $quiz['total_questions'] > 0 ? '📝 Edit Questions' : '➕ Add Questions'; ?>
                            </a>
                            <button onclick="editQuiz(<?php echo $quiz['id']; ?>)" class="btn btn-warning">✏️ Edit Quiz</button>
                            <button onclick="toggleQuizStatus(<?php echo $quiz['id']; ?>, <?php echo $quiz['is_active'] ? 0 : 1; ?>)" class="btn btn-secondary">
                                <?php echo $quiz['is_active'] ? '🔒 Deactivate' : '🔓 Activate'; ?>
                            </button>
                            <button onclick="deleteQuiz(<?php echo $quiz['id']; ?>, '<?php echo htmlspecialchars($quiz['quiz_title'] ?? ''); ?>')" class="btn btn-danger">🗑️ Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Quiz Modal -->
<div id="editQuizModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>✏️ Edit Quiz</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_quiz">
            <input type="hidden" name="quiz_id" id="edit_quiz_id">

            <div class="form-group">
                <label for="edit_quiz_title">Quiz Title:</label>
                <input type="text" name="quiz_title" id="edit_quiz_title" class="form-control" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="edit_quiz_description">Quiz Description:</label>
                <textarea name="quiz_description" id="edit_quiz_description" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="edit_passing_score">Passing Score (%):</label>
                <input type="number" name="passing_score" id="edit_passing_score" class="form-control" min="0" max="100" required>
            </div>

            <div class="form-group">
                <label for="edit_time_limit">Time Limit (minutes, optional):</label>
                <input type="number" name="time_limit" id="edit_time_limit" class="form-control" min="1" placeholder="No time limit">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                    Quiz is active
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function updateContentOptions() {
    const select = document.getElementById('content_type');
    const contentIdInput = document.getElementById('content_id');

    if (select.value) {
        const [contentType, contentId] = select.value.split('|');
        contentIdInput.value = contentId;
    } else {
        contentIdInput.value = '';
    }
}

function editQuiz(quizId) {
    // This would typically load quiz data via AJAX
    // For now, redirect to edit page
    window.location.href = 'manage_quiz_questions.php?quiz_id=' + quizId + '&edit=true';
}

function toggleQuizStatus(quizId, newStatus) {
    if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this quiz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="quiz_id" value="${quizId}">
            <input type="hidden" name="is_active" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteQuiz(quizId, quizTitle) {
    if (confirm(`Are you sure you want to delete "${quizTitle}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_quiz">
            <input type="hidden" name="quiz_id" value="${quizId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeEditModal() {
    document.getElementById('editQuizModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editQuizModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>