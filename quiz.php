<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (!isLoggedIn()) {
    redirect('/login.php', 'Please login first', 'warning');
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
    $selectedOption = clean($_POST['selected_option']);
    
    // Get quiz details
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND is_active = 1");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    
    if ($quiz) {
        $isCorrect = ($selectedOption === $quiz['correct_option']);
        $pointsEarned = $isCorrect ? $quiz['points_reward'] : 0;
        
        // Log attempt
        $stmt = $pdo->prepare(
            "INSERT INTO quiz_attempts (user_id, quiz_id, selected_option, is_correct, points_earned) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $quizId, $selectedOption, $isCorrect, $pointsEarned]);
        
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
include __DIR__ . '/includes/header.php';
?>

<h1 class="mb-3">📝 Daily Quiz Challenge</h1>

<div class="card-grid">
    <div class="card">
        <?php if ($quizSubmitted && $result): ?>
            <?php if ($result['correct']): ?>
                <div class="alert alert-success">
                    <h3>✅ Correct!</h3>
                    <p>You earned <strong><?php echo $result['points']; ?> points</strong>!</p>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h3>❌ Incorrect</h3>
                    <p>The correct answer was: <strong><?php echo strtoupper($result['correct_answer']); ?></strong></p>
                    <p>Better luck next time!</p>
                </div>
            <?php endif; ?>
            <a href="/quiz.php" class="btn btn-primary btn-block">Try Another Quiz</a>
        <?php elseif ($quiz): ?>
            <h2 class="card-header">Question</h2>
            <div class="quiz-question">
                <p style="font-size: 1.2rem; margin: 1.5rem 0;"><?php echo clean($quiz['question']); ?></p>
                
                <form method="POST">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                    
                    <div class="form-group" style="margin: 1rem 0;">
                        <label style="display: block; padding: 1rem; background: #f3f4f6; border-radius: 8px; cursor: pointer; margin-bottom: 0.8rem;">
                            <input type="radio" name="selected_option" value="a" required style="margin-right: 0.5rem;">
                            <strong>A.</strong> <?php echo clean($quiz['option_a']); ?>
                        </label>
                        
                        <label style="display: block; padding: 1rem; background: #f3f4f6; border-radius: 8px; cursor: pointer; margin-bottom: 0.8rem;">
                            <input type="radio" name="selected_option" value="b" required style="margin-right: 0.5rem;">
                            <strong>B.</strong> <?php echo clean($quiz['option_b']); ?>
                        </label>
                        
                        <label style="display: block; padding: 1rem; background: #f3f4f6; border-radius: 8px; cursor: pointer; margin-bottom: 0.8rem;">
                            <input type="radio" name="selected_option" value="c" required style="margin-right: 0.5rem;">
                            <strong>C.</strong> <?php echo clean($quiz['option_c']); ?>
                        </label>
                        
                        <label style="display: block; padding: 1rem; background: #f3f4f6; border-radius: 8px; cursor: pointer; margin-bottom: 0.8rem;">
                            <input type="radio" name="selected_option" value="d" required style="margin-right: 0.5rem;">
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
        <h2 class="card-header">📊 Your Quiz Stats</h2>
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

<?php include __DIR__ . '/includes/footer.php'; ?>