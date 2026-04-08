<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please login first', 'warning');
}

$user = getCurrentUser();
$userId = $user['id'];
$isStudent = hasRole('student');
$isStaff = hasRole('staff');

if (!$isStudent && !$isStaff) {
    redirect('/index.php', 'Access denied', 'danger');
}

$quizSubmitted = false;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_id'])) {
    $quizId = (int) $_POST['quiz_id'];
    $selectedOption = strtolower(trim((string)($_POST['selected_option'] ?? '')));
    $validOptions = ['a', 'b', 'c', 'd'];
    
    // Get quiz details
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND is_active = 1");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    
    if ($quiz && in_array($selectedOption, $validOptions, true)) {
        $isCorrect = ($selectedOption === strtolower($quiz['correct_option']));
        $isCorrectInt = $isCorrect ? 1 : 0;
        $pointsEarned = $isCorrect ? $quiz['points_reward'] : 0;
        
        // Log attempt
        $stmt = $pdo->prepare(
            "INSERT INTO quiz_attempts (user_id, quiz_id, selected_option, is_correct, points_earned) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $quizId, $selectedOption, $isCorrectInt, $pointsEarned]);
        
        // Award points if correct
        if ($isCorrect) {
            addPoints($userId, $pointsEarned, 'quiz', 'Correct answer: ' . substr($quiz['question'], 0, 50));
            checkAchievements($userId);
        }
        
        $result = [
            'correct' => $isCorrect,
            'points' => $pointsEarned,
            'correct_answer' => $quiz['correct_option'],
            'explanation' => $quiz['question']
        ];
        $quizSubmitted = true;
    }
}

// Get a random unattempted quiz or any active quiz
$quiz = null;

// Try to get an unattempted quiz first
$stmt = $pdo->prepare(
    "SELECT q.* FROM quizzes q 
     WHERE q.is_active = 1 
     AND q.id NOT IN (
         SELECT quiz_id FROM quiz_attempts WHERE user_id = ? AND DATE(attempted_at) = CURDATE()
     )
     ORDER BY RAND() LIMIT 1"
);
$stmt->execute([$userId]);
$quiz = $stmt->fetch();

// If all quizzes attempted today, get any random quiz
if (!$quiz) {
    $stmt = $pdo->query("SELECT * FROM quizzes WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
    $quiz = $stmt->fetch();
}

// Get quiz stats
$stmt = $pdo->prepare(
    "SELECT COUNT(*) as total, SUM(is_correct) as correct FROM quiz_attempts WHERE user_id = ?"
);
$stmt->execute([$userId]);
$stats = $stmt->fetch();

$pageTitle = 'Daily Quiz';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-clipboard-question"></i> Daily Quiz Challenge</h1>

<div class="card-grid">
    <div class="card">
        <?php if ($quizSubmitted && $result): ?>
            <?php if ($result['correct']): ?>
                <div class="alert alert-success">
                    <h3><i class="fa-solid fa-circle-check"></i> Correct!</h3>
                    <p>You earned <strong><?php echo $result['points']; ?> points</strong>!</p>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h3><i class="fa-solid fa-circle-xmark"></i> Incorrect</h3>
                    <p>The correct answer was: <strong><?php echo strtoupper($result['correct_answer']); ?></strong></p>
                    <p>Better luck next time!</p>
                </div>
            <?php endif; ?>
            <a href="<?php echo appUrl('/community/quiz.php'); ?>" class="btn btn-primary btn-block">Try Another Quiz</a>
        <?php elseif ($quiz): ?>
            <h2 class="card-header">Question</h2>
            <div class="quiz-question">
                <p class="quiz-question-copy"><?php echo clean($quiz['question']); ?></p>
                
                <form method="POST">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                    
                    <div class="form-group quiz-options">
                        <label class="quiz-option">
                            <input type="radio" name="selected_option" value="a" required class="quiz-option-input">
                            <strong>A.</strong> <?php echo clean($quiz['option_a']); ?>
                        </label>
                        
                        <label class="quiz-option">
                            <input type="radio" name="selected_option" value="b" required class="quiz-option-input">
                            <strong>B.</strong> <?php echo clean($quiz['option_b']); ?>
                        </label>
                        
                        <label class="quiz-option">
                            <input type="radio" name="selected_option" value="c" required class="quiz-option-input">
                            <strong>C.</strong> <?php echo clean($quiz['option_c']); ?>
                        </label>
                        
                        <label class="quiz-option">
                            <input type="radio" name="selected_option" value="d" required class="quiz-option-input">
                            <strong>D.</strong> <?php echo clean($quiz['option_d']); ?>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Submit Answer</button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p>No quizzes available right now. Check back later!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2 class="card-header"><i class="fa-solid fa-chart-simple"></i> Your Quiz Stats</h2>
        <div class="stat-card">
            <div class="stat-label">Total Quizzes</div>
            <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
        </div>
        <div class="stat-card blue mt-2">
            <div class="stat-label">Correct Answers</div>
            <div class="stat-value"><?php echo $stats['correct'] ?? 0; ?></div>
        </div>
        <?php if ($stats['total'] > 0): ?>
            <?php $accuracy = round(($stats['correct'] / $stats['total']) * 100); ?>
            <div class="stat-card orange mt-2">
                <div class="stat-label">Accuracy</div>
                <div class="stat-value"><?php echo $accuracy; ?>%</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
