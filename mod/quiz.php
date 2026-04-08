<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('moderator')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$userId = $user['id'];

$error = '';
$success = '';
$difficultyOptions = ['easy', 'medium', 'hard'];
$correctOptionChoices = ['a', 'b', 'c', 'd'];

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $question = clean($_POST['question'] ?? '');
    $optionA = clean($_POST['option_a'] ?? '');
    $optionB = clean($_POST['option_b'] ?? '');
    $optionC = clean($_POST['option_c'] ?? '');
    $optionD = clean($_POST['option_d'] ?? '');
    $correctOption = clean($_POST['correct_option'] ?? '');
    $difficulty = clean($_POST['difficulty'] ?? 'medium');
    $pointsReward = (int) ($_POST['points_reward'] ?? 10);
    
    if (empty($question) || empty($optionA) || empty($optionB) || empty($optionC) || empty($optionD)) {
        $error = 'Please fill in all fields';
    } elseif (!in_array($correctOption, $correctOptionChoices, true)) {
        $error = 'Please select a correct answer';
    } elseif (!in_array($difficulty, $difficultyOptions, true)) {
        $error = 'Invalid difficulty selected';
    } elseif ($pointsReward < 1) {
        $error = 'Points reward must be positive';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO quizzes (question, option_a, option_b, option_c, option_d, correct_option, difficulty, points_reward, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt->execute([$question, $optionA, $optionB, $optionC, $optionD, $correctOption, $difficulty, $pointsReward, $userId])) {
            $success = 'Quiz question created successfully!';
        } else {
            $error = 'Failed to create quiz';
        }
    }
}

// Handle quiz update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quiz'])) {
    $quizId = (int) ($_POST['quiz_id'] ?? 0);
    $question = clean($_POST['question'] ?? '');
    $optionA = clean($_POST['option_a'] ?? '');
    $optionB = clean($_POST['option_b'] ?? '');
    $optionC = clean($_POST['option_c'] ?? '');
    $optionD = clean($_POST['option_d'] ?? '');
    $correctOption = clean($_POST['correct_option'] ?? '');
    $difficulty = clean($_POST['difficulty'] ?? 'medium');
    $pointsReward = (int) ($_POST['points_reward'] ?? 0);

    if ($quizId <= 0) {
        $error = 'Invalid quiz selected';
    } elseif (empty($question) || empty($optionA) || empty($optionB) || empty($optionC) || empty($optionD)) {
        $error = 'Please fill in all fields';
    } elseif (!in_array($correctOption, $correctOptionChoices, true)) {
        $error = 'Please select a valid correct answer';
    } elseif (!in_array($difficulty, $difficultyOptions, true)) {
        $error = 'Invalid difficulty selected';
    } elseif ($pointsReward < 1) {
        $error = 'Points reward must be positive';
    } else {
        $stmt = $pdo->prepare(
            "UPDATE quizzes
             SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                 correct_option = ?, difficulty = ?, points_reward = ?
             WHERE id = ?"
        );

        if ($stmt->execute([$question, $optionA, $optionB, $optionC, $optionD, $correctOption, $difficulty, $pointsReward, $quizId])) {
            $success = 'Quiz updated successfully!';
        } else {
            $error = 'Failed to update quiz';
        }
    }
}

// Handle quiz deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    $quizId = (int) ($_POST['quiz_id'] ?? 0);

    if ($quizId <= 0) {
        $error = 'Invalid quiz selected';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $attemptCount = (int) ($stmt->fetch()['cnt'] ?? 0);

        if ($attemptCount > 0) {
            $error = 'Cannot delete quiz with attempt history';
        } else {
            $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
            if ($stmt->execute([$quizId])) {
                $success = 'Quiz deleted successfully!';
            } else {
                $error = 'Failed to delete quiz';
            }
        }
    }
}

// Handle quiz status toggle
if (isset($_GET['toggle'])) {
    $quizId = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ?");
    if ($stmt->execute([$quizId])) {
        redirect('/mod/quiz.php', 'Quiz status updated', 'success');
    }
}

// Get all quizzes
$stmt = $pdo->prepare(
    "SELECT q.*, u.name as creator_name,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND is_correct = 1) as correct_count
     FROM quizzes q
     JOIN users u ON q.created_by = u.id
     ORDER BY q.created_at DESC"
);
$stmt->execute();
$quizzes = $stmt->fetchAll();

$pageTitle = 'Manage Quizzes';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-clipboard-question"></i> Manage Quiz Questions</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header"><i class="fa-solid fa-plus"></i> Create New Quiz Question</h2>
    <form method="POST">
        <div class="form-group">
            <label>Question *</label>
            <textarea name="question" rows="3" required 
                      placeholder="Enter the quiz question..."></textarea>
        </div>
        
        <div class="form-group">
            <label>Option A *</label>
            <input type="text" name="option_a" required placeholder="First option">
        </div>
        
        <div class="form-group">
            <label>Option B *</label>
            <input type="text" name="option_b" required placeholder="Second option">
        </div>
        
        <div class="form-group">
            <label>Option C *</label>
            <input type="text" name="option_c" required placeholder="Third option">
        </div>
        
        <div class="form-group">
            <label>Option D *</label>
            <input type="text" name="option_d" required placeholder="Fourth option">
        </div>
        
        <div class="form-group">
            <label>Correct Answer *</label>
            <select name="correct_option" required>
                <option value="">Select correct answer</option>
                <option value="a">A</option>
                <option value="b">B</option>
                <option value="c">C</option>
                <option value="d">D</option>
            </select>
        </div>
        
        <div class="admin-form-grid admin-form-grid-2">
            <div class="form-group">
                <label>Difficulty *</label>
                <select name="difficulty" required>
                    <option value="easy">Easy</option>
                    <option value="medium" selected>Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Points Reward *</label>
                <input type="number" name="points_reward" value="10" min="1" required>
            </div>
        </div>
        
        <button type="submit" name="create_quiz" class="btn btn-primary">Create Quiz</button>
    </form>
</div>

<div class="card">
    <h2 class="card-header">All Quiz Questions</h2>
    <?php if (count($quizzes) > 0): ?>
        <div class="table-responsive">
            <table class="table rewards-table-main">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Difficulty</th>
                        <th>Reward</th>
                        <th>Attempts</th>
                        <th>Success Rate</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $quiz): ?>
                        <tr>
                            <td style="max-width: 300px;"><?php echo clean(substr($quiz['question'], 0, 80)) . (strlen($quiz['question']) > 80 ? '...' : ''); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $quiz['difficulty'] === 'easy' ? 'success' : 
                                        ($quiz['difficulty'] === 'medium' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($quiz['difficulty']); ?>
                                </span>
                            </td>
                            <td><?php echo $quiz['points_reward']; ?> pts</td>
                            <td><?php echo $quiz['attempt_count']; ?></td>
                            <td>
                                <?php 
                                if ($quiz['attempt_count'] > 0) {
                                    $successRate = round(($quiz['correct_count'] / $quiz['attempt_count']) * 100);
                                    echo $successRate . '%';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($quiz['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="reward-actions">
                                    <button onclick="document.getElementById('modal-<?php echo $quiz['id']; ?>').style.display='block'"
                                            class="btn btn-edit btn-sm">Edit</button>
                                    <a href="?toggle=<?php echo $quiz['id']; ?>" class="btn <?php echo $quiz['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?> btn-sm">
                                        <?php echo $quiz['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <form method="POST" class="admin-inline-form">
                                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                        <button
                                            type="submit"
                                            name="delete_quiz"
                                            class="btn btn-delete btn-sm action-delete-btn"
                                            onclick="return confirm('Delete this quiz? This action cannot be undone.');"
                                            title="Delete quiz"
                                            aria-label="Delete quiz"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>

                                <div id="modal-<?php echo $quiz['id']; ?>" class="admin-modal-overlay" style="display:none;">
                                    <div class="admin-modal-content">
                                        <h3>Edit Quiz</h3>
                                        <form method="POST" style="margin-top: 1rem;">
                                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">

                                            <div class="form-group">
                                                <label>Question</label>
                                                <textarea name="question" rows="3" required><?php echo clean($quiz['question']); ?></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label>Option A</label>
                                                <input type="text" name="option_a" value="<?php echo clean($quiz['option_a']); ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>Option B</label>
                                                <input type="text" name="option_b" value="<?php echo clean($quiz['option_b']); ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>Option C</label>
                                                <input type="text" name="option_c" value="<?php echo clean($quiz['option_c']); ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>Option D</label>
                                                <input type="text" name="option_d" value="<?php echo clean($quiz['option_d']); ?>" required>
                                            </div>

                                            <div class="admin-form-grid admin-form-grid-2">
                                                <div class="form-group">
                                                    <label>Correct Answer</label>
                                                    <select name="correct_option" required>
                                                        <option value="a" <?php echo $quiz['correct_option'] === 'a' ? 'selected' : ''; ?>>A</option>
                                                        <option value="b" <?php echo $quiz['correct_option'] === 'b' ? 'selected' : ''; ?>>B</option>
                                                        <option value="c" <?php echo $quiz['correct_option'] === 'c' ? 'selected' : ''; ?>>C</option>
                                                        <option value="d" <?php echo $quiz['correct_option'] === 'd' ? 'selected' : ''; ?>>D</option>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label>Difficulty</label>
                                                    <select name="difficulty" required>
                                                        <option value="easy" <?php echo $quiz['difficulty'] === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                                        <option value="medium" <?php echo $quiz['difficulty'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                        <option value="hard" <?php echo $quiz['difficulty'] === 'hard' ? 'selected' : ''; ?>>Hard</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label>Points Reward</label>
                                                <input type="number" name="points_reward" value="<?php echo (int) $quiz['points_reward']; ?>" min="1" required>
                                            </div>

                                            <button type="submit" name="update_quiz" class="btn btn-success btn-block">Save Changes</button>
                                            <button type="button" onclick="document.getElementById('modal-<?php echo $quiz['id']; ?>').style.display='none'"
                                                    class="btn btn-secondary btn-block mt-2">Cancel</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No quiz questions created yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
