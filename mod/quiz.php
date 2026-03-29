<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('moderator')) {
    redirect('/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$userId = $user['id'];

$error = '';
$success = '';

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    } elseif (!in_array($correctOption, ['a', 'b', 'c', 'd'])) {
        $error = 'Please select a correct answer';
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

// Handle quiz deactivation
if (isset($_GET['deactivate'])) {
    $quizId = (int) $_GET['deactivate'];
    $stmt = $pdo->prepare("UPDATE quizzes SET is_active = 0 WHERE id = ?");
    if ($stmt->execute([$quizId])) {
        redirect('/mod/quiz.php', 'Quiz deactivated', 'success');
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

<h1 class="mb-3">📝 Manage Quiz Questions</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header">➕ Create New Quiz Question</h2>
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
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
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
        
        <button type="submit" class="btn btn-primary">Create Quiz</button>
    </form>
</div>

<div class="card">
    <h2 class="card-header">All Quiz Questions</h2>
    <?php if (count($quizzes) > 0): ?>
        <table class="table">
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
                            <?php if ($quiz['is_active']): ?>
                                <a href="?deactivate=<?php echo $quiz['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Deactivate this quiz?');">Deactivate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No quiz questions created yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>