<?php
/**
 * Quiz Taking Page
 * Interface for trainees to take training quizzes
 * Only accessible by training users for assigned content
 *
 * Created: 2025-11-06
 * Author: Claude Code Assistant
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Load training helpers if available
if (file_exists('includes/training_helpers.php')) {
    require_once 'includes/training_helpers.php';
}

// Get quiz ID from URL
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$content_id = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
$content_type = isset($_GET['content_type']) ? $_GET['content_type'] : '';

if ($quiz_id <= 0 || ($content_id <= 0 || empty($content_type))) {
    header('Location: training_dashboard.php');
    exit;
}

$page_title = 'Training Quiz';
$error_message = '';
$success_message = '';

// Check if user is training user
if (!function_exists('is_training_user') || !is_training_user()) {
    header('Location: index.php');
    exit;
}

// Get quiz information and verify user has access
$quiz = null;
$quiz_attempt = null;
$can_attempt = false;

try {
    // Get quiz details
    $stmt = $pdo->prepare("
        SELECT tq.*,
               tcc.content_id, tcc.content_type,
               CASE tcc.content_type
                   WHEN 'category' THEN c.name
                   WHEN 'subcategory' THEN sc.name
                   WHEN 'post' THEN p.title
               END as content_name,
               CASE tcc.content_type
                   WHEN 'category' THEN 'category.php?id='
                   WHEN 'subcategory' THEN 'subcategory.php?id='
                   WHEN 'post' THEN 'post.php?id='
               END as content_url
        FROM training_quizzes tq
        JOIN training_course_content tcc ON tq.content_id = tcc.content_id AND tq.content_type = tcc.content_type
        LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
        LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
        LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
        WHERE tq.id = ? AND tq.is_active = TRUE
    ");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $error_message = 'Quiz not found or not active.';
    } elseif ($quiz['content_id'] != $content_id || $quiz['content_type'] != $content_type) {
        $error_message = 'Invalid quiz parameters.';
    } else {
        // Check if user has access to this content
        if (function_exists('is_assigned_training_content')) {
            $can_attempt = is_assigned_training_content($pdo, $_SESSION['user_id'], $content_id, $content_type);
        } else {
            $can_attempt = true; // Fallback if function doesn't exist
        }

        if (!$can_attempt) {
            $error_message = 'You do not have access to this quiz.';
        }
    }

    // Get existing attempt if any
    if ($can_attempt) {
        $stmt = $pdo->prepare("
            SELECT * FROM user_quiz_attempts
            WHERE user_id = ? AND quiz_id = ? AND status = 'in_progress'
            ORDER BY started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        $quiz_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no active attempt, create a new one
        if (!$quiz_attempt) {
            $stmt = $pdo->prepare("
                INSERT INTO user_quiz_attempts
                (user_id, quiz_id, attempt_number, status, started_at)
                VALUES (?, ?, (
                    SELECT COALESCE(MAX(attempt_number), 0) + 1
                    FROM user_quiz_attempts
                    WHERE user_id = ? AND quiz_id = ?
                ), 'in_progress', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$_SESSION['user_id'], $quiz_id, $_SESSION['user_id'], $quiz_id]);

            $attempt_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT * FROM user_quiz_attempts WHERE id = ?
            ");
            $stmt->execute([$attempt_id]);
            $quiz_attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    $error_message = 'Error loading quiz: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_attempt && $quiz_attempt) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'submit_quiz') {
        $answers = isset($_POST['answers']) ? $_POST['answers'] : [];

        if (empty($answers)) {
            $error_message = 'Please answer all questions before submitting.';
        } else {
            try {
                $pdo->beginTransaction();

                // Get quiz questions and correct answers
                $stmt = $pdo->prepare("
                    SELECT qq.id, qq.points, qac.id as choice_id, qac.is_correct
                    FROM quiz_questions qq
                    JOIN quiz_answer_choices qac ON qq.id = qac.question_id
                    WHERE qq.quiz_id = ? AND qq.is_active = TRUE
                    ORDER BY qq.question_order, qq.id
                ");
                $stmt->execute([$quiz_id]);
                $question_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate score
                $total_points = 0;
                $earned_points = 0;
                $correct_answers = 0;
                $total_questions = 0;

                $questions = [];
                foreach ($question_data as $data) {
                    if (!isset($questions[$data['id']])) {
                        $questions[$data['id']] = [
                            'points' => $data['points'],
                            'correct_choice' => null,
                            'user_choice' => null
                        ];
                        $total_points += $data['points'];
                        $total_questions++;
                    }

                    if ($data['is_correct']) {
                        $questions[$data['id']]['correct_choice'] = $data['choice_id'];
                    }
                }

                // Process user answers
                $user_answers = [];
                foreach ($answers as $question_id => $choice_id) {
                    if (isset($questions[$question_id])) {
                        $questions[$question_id]['user_choice'] = $choice_id;

                        // Save answer
                        $stmt = $pdo->prepare("
                            INSERT INTO user_quiz_answers
                            (attempt_id, question_id, selected_choice_id, is_correct, points_earned)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            selected_choice_id = VALUES(selected_choice_id),
                            is_correct = VALUES(is_correct),
                            points_earned = VALUES(points_earned),
                            answered_at = CURRENT_TIMESTAMP
                        ");

                        $is_correct = ($questions[$question_id]['correct_choice'] == $choice_id);
                        $points_earned = $is_correct ? $questions[$question_id]['points'] : 0;

                        $stmt->execute([$quiz_attempt['id'], $question_id, $choice_id, $is_correct, $points_earned]);

                        if ($is_correct) {
                            $earned_points += $questions[$question_id]['points'];
                            $correct_answers++;
                        }
                    }
                }

                // Calculate percentage score
                $score = $total_points > 0 ? round(($earned_points / $total_points) * 100) : 0;
                $status = ($score >= $quiz['passing_score']) ? 'passed' : 'failed';

                // Update attempt
                $stmt = $pdo->prepare("
                    UPDATE user_quiz_attempts
                    SET status = ?, score = ?, total_points = ?, earned_points = ?,
                        completed_at = CURRENT_TIMESTAMP,
                        time_taken_minutes = TIMESTAMPDIFF(MINUTE, started_at, CURRENT_TIMESTAMP)
                    WHERE id = ?
                ");
                $stmt->execute([$status, $score, $total_points, $earned_points, $quiz_attempt['id']]);

                // If passed, update training progress
                if ($status === 'passed') {
                    $stmt = $pdo->prepare("
                        UPDATE training_progress
                        SET quiz_completed = TRUE, quiz_score = ?, quiz_completed_at = CURRENT_TIMESTAMP,
                            last_quiz_attempt_id = ?, status = 'completed', completion_date = CURRENT_TIMESTAMP
                        WHERE user_id = ? AND content_type = ? AND content_id = ?
                    ");
                    $stmt->execute([$score, $quiz_attempt['id'], $_SESSION['user_id'], $content_type, $content_id]);
                }

                $pdo->commit();

                // Redirect to results page
                header('Location: quiz_results.php?attempt_id=' . $quiz_attempt['id']);
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = 'Error submitting quiz: ' . $e->getMessage();
            }
        }
    }
}

// Get quiz questions for display
$questions = [];
if ($can_attempt && $quiz) {
    try {
        $stmt = $pdo->prepare("
            SELECT qq.*,
                   qac.id as choice_id, qac.choice_text, qac.is_correct, qac.choice_order
            FROM quiz_questions qq
            JOIN quiz_answer_choices qac ON qq.id = qac.question_id
            WHERE qq.quiz_id = ? AND qq.is_active = TRUE
            ORDER BY qq.question_order, qq.id, qac.choice_order
        ");
        $stmt->execute([$quiz_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by question
        foreach ($results as $row) {
            $question_id = $row['id'];
            if (!isset($questions[$question_id])) {
                $questions[$question_id] = [
                    'id' => $row['id'],
                    'question_text' => $row['question_text'],
                    'points' => $row['points'],
                    'choices' => []
                ];
            }
            $questions[$question_id]['choices'][] = [
                'id' => $row['choice_id'],
                'text' => $row['choice_text'],
                'is_correct' => $row['is_correct'],
                'order' => $row['choice_order']
            ];
        }

        // Sort choices by order
        foreach ($questions as &$question) {
            usort($question['choices'], function($a, $b) {
                return $a['order'] - $b['order'];
            });
        }

    } catch (PDOException $e) {
        $error_message = 'Error loading questions: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<style>
.quiz-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.quiz-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.quiz-header h1 {
    margin: 0 0 15px 0;
    font-size: 28px;
}

.quiz-info {
    background: rgba(255,255,255,0.2);
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.quiz-info-row {
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 15px;
}

.quiz-info-item {
    text-align: center;
}

.quiz-info-label {
    font-size: 12px;
    opacity: 0.9;
    margin-bottom: 5px;
}

.quiz-info-value {
    font-size: 18px;
    font-weight: bold;
}

.timer {
    background: #ffc107;
    color: #212529;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: bold;
    margin: 20px 0;
    text-align: center;
    display: none;
}

.timer.warning {
    background: #dc3545;
    color: white;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.question-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.question-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.question-number {
    background: #667eea;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
    margin-right: 15px;
}

.question-text {
    font-size: 18px;
    font-weight: 500;
    color: #333;
    flex: 1;
}

.question-points {
    background: #e3f2fd;
    color: #1976d2;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 14px;
    font-weight: 500;
}

.answer-choices {
    margin: 20px 0;
}

.answer-choice {
    display: flex;
    align-items: center;
    padding: 15px;
    margin: 10px 0;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.answer-choice:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.answer-choice.selected {
    border-color: #667eea;
    background: #f8f9ff;
}

.answer-choice input[type="radio"] {
    margin-right: 15px;
    transform: scale(1.2);
}

.answer-choice label {
    flex: 1;
    cursor: pointer;
    font-size: 16px;
    color: #333;
}

.quiz-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e9ecef;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a6fd8;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.progress-indicator {
    text-align: center;
    margin: 20px 0;
}

.progress-text {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.progress-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.progress-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e9ecef;
    transition: all 0.2s ease;
}

.progress-dot.completed {
    background: #28a745;
}

.progress-dot.current {
    background: #667eea;
    transform: scale(1.3);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #495057;
}

.content-navigation {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.content-navigation a {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
}

.content-navigation a:hover {
    text-decoration: underline;
}
</style>

<div class="quiz-container">
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            <br><br>
            <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    <?php elseif (!$can_attempt): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🚫</div>
            <h3>Access Denied</h3>
            <p>You don't have access to this quiz.</p>
            <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    <?php elseif ($quiz): ?>
        <!-- Quiz Header -->
        <div class="quiz-header">
            <h1>📝 Training Quiz</h1>
            <div class="quiz-info">
                <div class="quiz-info-row">
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Content</div>
                        <div class="quiz-info-value"><?php echo htmlspecialchars($quiz['content_name']); ?></div>
                    </div>
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Questions</div>
                        <div class="quiz-info-value"><?php echo count($questions); ?></div>
                    </div>
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Passing Score</div>
                        <div class="quiz-info-value"><?php echo $quiz['passing_score']; ?>%</div>
                    </div>
                    <?php if ($quiz['time_limit_minutes']): ?>
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Time Limit</div>
                        <div class="quiz-info-value"><?php echo $quiz['time_limit_minutes']; ?> min</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Timer (if time limit) -->
        <?php if ($quiz['time_limit_minutes']): ?>
        <div class="timer" id="quiz-timer">
            ⏱️ Time Remaining: <span id="time-display"><?php echo $quiz['time_limit_minutes']; ?>:00</span>
        </div>
        <?php endif; ?>

        <!-- Progress Indicator -->
        <div class="progress-indicator">
            <div class="progress-text">Question Progress</div>
            <div class="progress-dots">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="progress-dot" data-question="<?php echo $index + 1; ?>"></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content Navigation -->
        <div class="content-navigation">
            <p>📚 Read the content before taking the quiz:</p>
            <a href="<?php echo htmlspecialchars($quiz['content_url'] . $quiz['content_id']); ?>">
                View <?php echo ucfirst($quiz['content_type']); ?> Content
            </a>
        </div>

        <?php if (empty($questions)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">❓</div>
                <h3>No Questions Available</h3>
                <p>This quiz doesn't have any questions yet. Please contact your administrator.</p>
                <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        <?php else: ?>
            <form method="POST" id="quiz-form">
                <input type="hidden" name="action" value="submit_quiz">

                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card" data-question="<?php echo $index + 1; ?>">
                        <div class="question-header">
                            <div class="question-number"><?php echo $index + 1; ?></div>
                            <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                            <div class="question-points"><?php echo $question['points']; ?> pts</div>
                        </div>

                        <div class="answer-choices">
                            <?php foreach ($question['choices'] as $choice): ?>
                                <div class="answer-choice" onclick="selectAnswer(this)">
                                    <input type="radio"
                                           name="answers[<?php echo $question['id']; ?>]"
                                           value="<?php echo $choice['id']; ?>"
                                           id="choice_<?php echo $choice['id']; ?>"
                                           onchange="updateProgress()">
                                    <label for="choice_<?php echo $choice['id']; ?>">
                                        <?php echo htmlspecialchars($choice['text']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Quiz Navigation -->
                <div class="quiz-navigation">
                    <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <div>
                        <span id="completion-status" style="color: #666; margin-right: 15px;">
                            Answer all questions to submit
                        </span>
                        <button type="submit" class="btn btn-success" id="submit-btn" disabled>
                            Submit Quiz
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
let timeLimit = <?php echo $quiz['time_limit_minutes'] ?? 0; ?>;
let timeRemaining = timeLimit * 60; // Convert to seconds
let timerInterval = null;

// Timer functionality
if (timeLimit > 0) {
    const timerElement = document.getElementById('quiz-timer');
    const timeDisplay = document.getElementById('time-display');

    timerElement.style.display = 'block';

    timerInterval = setInterval(function() {
        timeRemaining--;

        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        if (timeRemaining <= 300 && timeRemaining > 60) { // 5 minutes warning
            timerElement.classList.add('warning');
        }

        if (timeRemaining <= 60) { // 1 minute critical
            timeDisplay.style.color = 'white';
        }

        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            // Auto-submit when time runs out
            document.getElementById('quiz-form').submit();
        }
    }, 1000);
}

// Answer selection
function selectAnswer(element) {
    // Remove selected class from all choices in this question
    const questionCard = element.closest('.question-card');
    questionCard.querySelectorAll('.answer-choice').forEach(choice => {
        choice.classList.remove('selected');
    });

    // Add selected class to clicked choice
    element.classList.add('selected');

    // Update progress
    updateProgress();
}

// Update progress and submit button
function updateProgress() {
    const totalQuestions = <?php echo count($questions); ?>;
    const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;

    // Update progress dots
    document.querySelectorAll('.progress-dot').forEach((dot, index) => {
        if (index < answeredQuestions) {
            dot.classList.add('completed');
        } else if (index === answeredQuestions) {
            dot.classList.add('current');
        } else {
            dot.classList.remove('completed', 'current');
        }
    });

    // Update submit button
    const submitBtn = document.getElementById('submit-btn');
    const statusText = document.getElementById('completion-status');

    if (answeredQuestions === totalQuestions) {
        submitBtn.disabled = false;
        statusText.textContent = 'All questions answered ✓';
        statusText.style.color = '#28a745';
    } else {
        submitBtn.disabled = true;
        statusText.textContent = `${answeredQuestions} of ${totalQuestions} questions answered`;
        statusText.style.color = '#666';
    }
}

// Handle form submission
document.getElementById('quiz-form').addEventListener('submit', function(e) {
    const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;
    const totalQuestions = <?php echo count($questions); ?>;

    if (answeredQuestions < totalQuestions) {
        e.preventDefault();
        alert('Please answer all questions before submitting the quiz.');
        return false;
    }

    if (confirm('Are you ready to submit your quiz? You cannot change your answers after submitting.')) {
        // Clear timer if running
        if (timerInterval) {
            clearInterval(timerInterval);
        }

        // Show loading state
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.textContent = 'Submitting...';
        submitBtn.disabled = true;

        return true;
    } else {
        e.preventDefault();
        return false;
    }
});

// Initialize progress on page load
updateProgress();

// Clean up timer on page unload
window.addEventListener('beforeunload', function() {
    if (timerInterval) {
        clearInterval(timerInterval);
    }
});
</script>

<?php include 'includes/footer.php'; ?>