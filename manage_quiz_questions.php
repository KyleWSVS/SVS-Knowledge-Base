<?php
/**
 * Quiz Questions Management Page
 * Admin interface for creating and managing quiz questions and answer choices
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

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

if ($quiz_id <= 0) {
    header('Location: manage_quizzes.php');
    exit;
}

$page_title = 'Quiz Questions Management';
$success_message = '';
$error_message = '';

// Get quiz information
$quiz = null;
try {
    $stmt = $pdo->prepare("
        SELECT tq.*,
               CASE tq.content_type
                   WHEN 'category' THEN c.name
                   WHEN 'subcategory' THEN sc.name
                   WHEN 'post' THEN p.title
               END as content_name
        FROM training_quizzes tq
        LEFT JOIN training_course_content tcc ON tq.content_id = tcc.content_id AND tq.content_type = tcc.content_type
        LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
        LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
        LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
        WHERE tq.id = ?
    ");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        header('Location: manage_quizzes.php');
        exit;
    }
} catch (PDOException $e) {
    $error_message = 'Error loading quiz information: ' . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'edit_quiz':
            $quiz_title = isset($_POST['quiz_title']) ? trim($_POST['quiz_title']) : '';
            $quiz_description = isset($_POST['quiz_description']) ? trim($_POST['quiz_description']) : '';
            $passing_score = isset($_POST['passing_score']) ? intval($_POST['passing_score']) : 100;
            $time_limit = isset($_POST['time_limit']) && !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($quiz_title)) {
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
                    // Reload quiz data
                    $stmt->execute([$quiz_id]);
                    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $error_message = 'Error updating quiz: ' . $e->getMessage();
                }
            }
            break;

        case 'add_question':
            $question_text = isset($_POST['question_text']) ? trim($_POST['question_text']) : '';
            $points = isset($_POST['points']) ? intval($_POST['points']) : 1;
            $choices = isset($_POST['choices']) ? $_POST['choices'] : [];
            $correct_choice = isset($_POST['correct_choice']) ? intval($_POST['correct_choice']) : 0;

            if (empty($question_text)) {
                $error_message = 'Question text is required.';
            } elseif (count($choices) < 2) {
                $error_message = 'At least 2 answer choices are required.';
            } elseif ($correct_choice < 1 || $correct_choice > count($choices)) {
                $error_message = 'Please select a correct answer.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Add question
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_questions
                        (quiz_id, question_text, question_order, points, is_active)
                        VALUES (?, ?, ?, ?, TRUE)
                    ");
                    $stmt->execute([$quiz_id, $question_text, 0, $points]);
                    $question_id = $pdo->lastInsertId();

                    // Add answer choices
                    foreach ($choices as $index => $choice_text) {
                        $choice_text = trim($choice_text);
                        if (!empty($choice_text)) {
                            $is_correct = ($index + 1) === $correct_choice;
                            $stmt = $pdo->prepare("
                                INSERT INTO quiz_answer_choices
                                (question_id, choice_text, is_correct, choice_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$question_id, $choice_text, $is_correct, $index]);
                        }
                    }

                    $pdo->commit();
                    $success_message = 'Question added successfully!';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error_message = 'Error adding question: ' . $e->getMessage();
                }
            }
            break;

        case 'edit_question':
            $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
            $question_text = isset($_POST['question_text']) ? trim($_POST['question_text']) : '';
            $points = isset($_POST['points']) ? intval($_POST['points']) : 1;
            $choices = isset($_POST['choices']) ? $_POST['choices'] : [];
            $correct_choice = isset($_POST['correct_choice']) ? intval($_POST['correct_choice']) : 0;

            if ($question_id <= 0) {
                $error_message = 'Invalid question ID.';
            } elseif (empty($question_text)) {
                $error_message = 'Question text is required.';
            } elseif (count($choices) < 2) {
                $error_message = 'At least 2 answer choices are required.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Update question
                    $stmt = $pdo->prepare("
                        UPDATE quiz_questions
                        SET question_text = ?, points = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ? AND quiz_id = ?
                    ");
                    $stmt->execute([$question_text, $points, $question_id, $quiz_id]);

                    // Delete existing choices
                    $stmt = $pdo->prepare("DELETE FROM quiz_answer_choices WHERE question_id = ?");
                    $stmt->execute([$question_id]);

                    // Add new choices
                    foreach ($choices as $index => $choice_text) {
                        $choice_text = trim($choice_text);
                        if (!empty($choice_text)) {
                            $is_correct = ($index + 1) === $correct_choice;
                            $stmt = $pdo->prepare("
                                INSERT INTO quiz_answer_choices
                                (question_id, choice_text, is_correct, choice_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$question_id, $choice_text, $is_correct, $index]);
                        }
                    }

                    $pdo->commit();
                    $success_message = 'Question updated successfully!';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error_message = 'Error updating question: ' . $e->getMessage();
                }
            }
            break;

        case 'delete_question':
            $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;

            if ($question_id <= 0) {
                $error_message = 'Invalid question ID.';
            } else {
                try {
                    // Check if users have attempted this quiz
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM user_quiz_attempts uqa
                        JOIN user_quiz_answers uqa_ans ON uqa.id = uqa_ans.attempt_id
                        JOIN quiz_questions qq ON uqa_ans.question_id = qq.id
                        WHERE qq.quiz_id = ? AND qq.id = ?
                    ");
                    $stmt->execute([$quiz_id, $question_id]);
                    $attempt_count = $stmt->fetchColumn();

                    if ($attempt_count > 0) {
                        $error_message = 'Cannot delete question. Users have already attempted this quiz.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?");
                        $stmt->execute([$question_id, $quiz_id]);
                        $success_message = 'Question deleted successfully!';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error deleting question: ' . $e->getMessage();
                }
            }
            break;

        case 'reorder_questions':
            $question_orders = isset($_POST['question_order']) ? $_POST['question_order'] : [];

            try {
                $pdo->beginTransaction();
                foreach ($question_orders as $question_id => $order) {
                    $stmt = $pdo->prepare("
                        UPDATE quiz_questions
                        SET question_order = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ? AND quiz_id = ?
                    ");
                    $stmt->execute([$order, $question_id, $quiz_id]);
                }
                $pdo->commit();
                $success_message = 'Question order updated successfully!';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = 'Error reordering questions: ' . $e->getMessage();
            }
            break;
    }
}

// Handle AJAX request for question data
if (isset($_GET['get_question_data'])) {
    $question_id = intval($_GET['get_question_data']);

    try {
        // Get question data
        $stmt = $pdo->prepare("
            SELECT * FROM quiz_questions
            WHERE id = ? AND quiz_id = ?
        ");
        $stmt->execute([$question_id, $quiz_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($question) {
            // Get answer choices
            $stmt = $pdo->prepare("
                SELECT * FROM quiz_answer_choices
                WHERE question_id = ?
                ORDER BY choice_order
            ");
            $stmt->execute([$question_id]);
            $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'question' => $question,
                'choices' => $choices
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Question not found'
            ]);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Get questions for this quiz
$questions = [];
try {
    $stmt = $pdo->prepare("
        SELECT qq.*,
               COUNT(qac.id) as choice_count,
               COUNT(CASE WHEN qac.is_correct = TRUE THEN 1 END) as correct_choices
        FROM quiz_questions qq
        LEFT JOIN quiz_answer_choices qac ON qq.id = qac.question_id
        WHERE qq.quiz_id = ?
        GROUP BY qq.id
        ORDER BY qq.question_order, qq.id
    ");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get answer choices for each question
    foreach ($questions as &$question) {
        $stmt = $pdo->prepare("
            SELECT * FROM quiz_answer_choices
            WHERE question_id = ?
            ORDER BY choice_order
        ");
        $stmt->execute([$question['id']]);
        $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = 'Error loading questions: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<style>
.quiz-questions-management {
    max-width: 1000px;
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

.quiz-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}

.question-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: relative;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.question-text {
    font-weight: 500;
    color: #333;
    margin-bottom: 10px;
}

.question-meta {
    color: #666;
    font-size: 14px;
}

.answer-choices {
    margin: 15px 0;
    padding-left: 20px;
}

.answer-choice {
    padding: 8px 12px;
    margin: 5px 0;
    border-radius: 6px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}

.answer-choice.correct {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.answer-choice.correct::before {
    content: "✅ ";
    font-weight: bold;
}

.question-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
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

.btn-sm { padding: 6px 12px; font-size: 13px; }

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
    margin: 2% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
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

.choice-input-group {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.choice-input-group input[type="radio"] {
    margin-right: 10px;
}

.choice-input-group input[type="text"] {
    flex: 1;
}

.add-choice-btn {
    background: #e3f2fd;
    border: 1px dashed #2196f3;
    color: #2196f3;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    text-align: center;
    margin-top: 10px;
}

.add-choice-btn:hover {
    background: #bbdefb;
}

.drag-handle {
    cursor: move;
    color: #999;
    font-size: 18px;
    margin-right: 10px;
}

.question-number {
    background: #667eea;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    margin-right: 15px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 15px;
}
</style>

<div class="quiz-questions-management">
    <div class="section-header">
        <h1>📝 Quiz Questions Management</h1>
        <p><?php echo htmlspecialchars($quiz['content_name'] ?? 'Unknown Content'); ?> - <?php echo htmlspecialchars($quiz['quiz_title']); ?></p>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <!-- Quiz Information -->
    <div class="quiz-info">
        <h3>📚 Quiz Information</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <div>
                <strong>Title:</strong> <?php echo htmlspecialchars($quiz['quiz_title']); ?>
            </div>
            <div>
                <strong>Passing Score:</strong> <?php echo $quiz['passing_score']; ?>%
            </div>
            <div>
                <strong>Time Limit:</strong> <?php echo $quiz['time_limit_minutes'] ? $quiz['time_limit_minutes'] . ' minutes' : 'No limit'; ?>
            </div>
            <div>
                <strong>Status:</strong> <?php echo $quiz['is_active'] ? '🟢 Active' : '🔴 Inactive'; ?>
            </div>
        </div>
        <?php if ($quiz['quiz_description']): ?>
            <div style="margin-top: 15px;">
                <strong>Description:</strong><br>
                <?php echo nl2br(htmlspecialchars($quiz['quiz_description'])); ?>
            </div>
        <?php endif; ?>
        <div style="margin-top: 15px;">
            <button onclick="editQuizSettings()" class="btn btn-warning">✏️ Edit Quiz Settings</button>
            <a href="manage_quizzes.php" class="btn btn-secondary">← Back to Quizzes</a>
        </div>
    </div>

    <!-- Add Question Section -->
    <div class="question-card">
        <h3>➕ Add New Question</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_question">

            <div class="form-group">
                <label for="question_text">Question Text:</label>
                <textarea name="question_text" id="question_text" class="form-control" rows="3" required placeholder="Enter your question here..."></textarea>
            </div>

            <div class="form-group">
                <label for="points">Points:</label>
                <input type="number" name="points" id="points" class="form-control" min="1" value="1" required>
            </div>

            <div class="form-group">
                <label>Answer Choices (Multiple Choice):</label>
                <div id="choices-container">
                    <div class="choice-input-group">
                        <input type="radio" name="correct_choice" value="1" required>
                        <input type="text" name="choices[]" class="form-control" placeholder="Choice 1" required>
                    </div>
                    <div class="choice-input-group">
                        <input type="radio" name="correct_choice" value="2" required>
                        <input type="text" name="choices[]" class="form-control" placeholder="Choice 2" required>
                    </div>
                </div>
                <div class="add-choice-btn" onclick="addChoiceInput()">
                    + Add Another Choice
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Add Question</button>
        </form>
    </div>

    <!-- Existing Questions Section -->
    <div class="question-card">
        <h3>📚 Questions (<?php echo count($questions); ?>)</h3>
        <?php if (empty($questions)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">❓</div>
                <h4>No questions yet</h4>
                <p>Add your first question using the form above to get started.</p>
            </div>
        <?php else: ?>
            <div id="questions-list">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card" draggable="true" data-question-id="<?php echo $question['id']; ?>">
                        <div class="question-header">
                            <div style="display: flex; align-items: flex-start;">
                                <div class="drag-handle">⋮⋮</div>
                                <div class="question-number"><?php echo $index + 1; ?></div>
                                <div style="flex: 1;">
                                    <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                                    <div class="question-meta">
                                        Points: <?php echo $question['points']; ?> •
                                        Choices: <?php echo $question['choice_count']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="answer-choices">
                            <?php foreach ($question['choices'] as $choice): ?>
                                <div class="answer-choice <?php echo $choice['is_correct'] ? 'correct' : ''; ?>">
                                    <?php echo htmlspecialchars($choice['choice_text']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="question-actions">
                            <button onclick="editQuestion(<?php echo $question['id']; ?>)" class="btn btn-warning btn-sm">✏️ Edit</button>
                            <button onclick="deleteQuestion(<?php echo $question['id']; ?>, '<?php echo htmlspecialchars(substr($question['question_text'], 0, 50)); ?>')" class="btn btn-danger btn-sm">🗑️ Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Quiz Settings Modal -->
<div id="editQuizModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>✏️ Edit Quiz Settings</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_quiz">

            <div class="form-group">
                <label for="edit_quiz_title">Quiz Title:</label>
                <input type="text" name="quiz_title" id="edit_quiz_title" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($quiz['quiz_title']); ?>">
            </div>

            <div class="form-group">
                <label for="edit_quiz_description">Quiz Description:</label>
                <textarea name="quiz_description" id="edit_quiz_description" class="form-control" rows="3"><?php echo htmlspecialchars($quiz['quiz_description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="edit_passing_score">Passing Score (%):</label>
                <input type="number" name="passing_score" id="edit_passing_score" class="form-control" min="0" max="100" required value="<?php echo $quiz['passing_score']; ?>">
            </div>

            <div class="form-group">
                <label for="edit_time_limit">Time Limit (minutes, optional):</label>
                <input type="number" name="time_limit" id="edit_time_limit" class="form-control" min="1" placeholder="No time limit" value="<?php echo $quiz['time_limit_minutes']; ?>">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" <?php echo $quiz['is_active'] ? 'checked' : ''; ?>>
                    Quiz is active
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- Edit Question Modal -->
<div id="editQuestionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditQuestionModal()">&times;</span>
        <h2>✏️ Edit Question</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_question">
            <input type="hidden" name="question_id" id="edit_question_id">

            <div class="form-group">
                <label for="edit_question_text">Question Text:</label>
                <textarea name="question_text" id="edit_question_text" class="form-control" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label for="edit_points">Points:</label>
                <input type="number" name="points" id="edit_points" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label>Answer Choices:</label>
                <div id="edit-choices-container"></div>
                <div class="add-choice-btn" onclick="addEditChoiceInput()">
                    + Add Another Choice
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="closeEditQuestionModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
let choiceCount = 2;
let editChoiceCount = 0;

function addChoiceInput() {
    choiceCount++;
    const container = document.getElementById('choices-container');
    const choiceDiv = document.createElement('div');
    choiceDiv.className = 'choice-input-group';
    choiceDiv.innerHTML = `
        <input type="radio" name="correct_choice" value="${choiceCount}">
        <input type="text" name="choices[]" class="form-control" placeholder="Choice ${choiceCount}">
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; padding: 6px 12px;">×</button>
    `;
    container.appendChild(choiceDiv);
}

function addEditChoiceInput() {
    editChoiceCount++;
    const container = document.getElementById('edit-choices-container');
    const choiceDiv = document.createElement('div');
    choiceDiv.className = 'choice-input-group';
    choiceDiv.innerHTML = `
        <input type="radio" name="correct_choice" value="${editChoiceCount}">
        <input type="text" name="choices[]" class="form-control" placeholder="Choice ${editChoiceCount}">
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; padding: 6px 12px;">×</button>
    `;
    container.appendChild(choiceDiv);
}

function editQuizSettings() {
    document.getElementById('editQuizModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editQuizModal').style.display = 'none';
}

function editQuestion(questionId) {
    // Load question data via AJAX and show modal
    fetch('manage_quiz_questions.php?quiz_id=<?php echo $quiz_id; ?>&get_question_data=' + questionId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate the edit form
                document.getElementById('edit_question_id').value = data.question.id;
                document.getElementById('edit_question_text').value = data.question.question_text;
                document.getElementById('edit_points').value = data.question.points;

                // Clear existing choices
                const container = document.getElementById('edit-choices-container');
                container.innerHTML = '';

                // Add choices from the question
                editChoiceCount = 0;
                data.choices.forEach((choice, index) => {
                    editChoiceCount++;
                    const choiceDiv = document.createElement('div');
                    choiceDiv.className = 'choice-input-group';
                    choiceDiv.innerHTML = `
                        <input type="radio" name="correct_choice" value="${index + 1}" ${choice.is_correct ? 'checked' : ''}>
                        <input type="text" name="choices[]" class="form-control" value="${choice.choice_text}">
                        <button type="button" onclick="this.parentElement.remove()" style="margin-left: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; padding: 6px 12px;">×</button>
                    `;
                    container.appendChild(choiceDiv);
                });

                // Show the modal
                document.getElementById('editQuestionModal').style.display = 'block';
            } else {
                alert('Error loading question data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading question data');
        });
}

function closeEditQuestionModal() {
    document.getElementById('editQuestionModal').style.display = 'none';
}

function deleteQuestion(questionId, questionText) {
    if (confirm(`Are you sure you want to delete this question?\n\n"${questionText}..."`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_question">
            <input type="hidden" name="question_id" value="${questionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Drag and drop functionality for reordering questions
let draggedElement = null;

document.addEventListener('DOMContentLoaded', function() {
    const questionsList = document.getElementById('questions-list');
    if (questionsList) {
        questionsList.addEventListener('dragstart', function(e) {
            if (e.target.classList.contains('question-card') && e.target.draggable) {
                draggedElement = e.target;
                e.target.style.opacity = '0.5';
            }
        });

        questionsList.addEventListener('dragend', function(e) {
            if (e.target.classList.contains('question-card')) {
                e.target.style.opacity = '';
            }
        });

        questionsList.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(questionsList, e.clientY);
            if (afterElement == null) {
                questionsList.appendChild(draggedElement);
            } else {
                questionsList.insertBefore(draggedElement, afterElement);
            }
        });

        questionsList.addEventListener('drop', function(e) {
            e.preventDefault();
            updateQuestionOrder();
        });
    }
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.question-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function updateQuestionOrder() {
    const questionsList = document.getElementById('questions-list');
    const questionCards = questionsList.querySelectorAll('.question-card');
    const questionOrder = {};

    questionCards.forEach((card, index) => {
        const questionId = card.dataset.questionId;
        questionOrder[questionId] = index;
    });

    // Send order to server
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="reorder_questions">
    `;

    for (const [questionId, order] of Object.entries(questionOrder)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `question_order[${questionId}]`;
        input.value = order;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
}

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editQuizModal');
    const questionModal = document.getElementById('editQuestionModal');

    if (event.target == editModal) {
        editModal.style.display = 'none';
    }
    if (event.target == questionModal) {
        questionModal.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>