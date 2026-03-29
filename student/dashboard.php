<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$userId = $user['id'];

// Get statistics
$stats = [];

// Total points
$stats['points'] = $user['points_total'];

// Current streak
$stats['streak'] = $user['streak_count'];

// Achievements count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_achievements WHERE user_id = ?");
$stmt->execute([$userId]);
$stats['achievements'] = $stmt->fetch()['count'];

// Challenges completed
$stmt = $pdo->prepare(
    "SELECT COUNT(*) as count FROM challenge_submissions 
     WHERE user_id = ? AND status = 'approved'"
);
$stmt->execute([$userId]);
$stats['challenges'] = $stmt->fetch()['count'];

// Recent activity (last 10)
$recentActivity = $pdo->prepare(
    "SELECT * FROM points_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 10"
);
$recentActivity->execute([$userId]);
$activities = $recentActivity->fetchAll();

// Leaderboard position
$stmt = $pdo->query(
    "SELECT id, points_total FROM users WHERE role = 'student' ORDER BY points_total DESC"
);
$leaderboard = $stmt->fetchAll();
$position = 0;
foreach ($leaderboard as $index => $entry) {
    if ($entry['id'] == $userId) {
        $position = $index + 1;
        break;
    }
}

$pageTitle = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">Welcome back, <?php echo clean($user['name']); ?>! 🌿</h1>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-label">🏆 Total Points</div>
        <div class="stat-value"><?php echo number_format($stats['points']); ?></div>
        <div class="stat-label">Rank #<?php echo $position; ?> on leaderboard</div>
    </div>
    
    <div class="stat-card blue">
        <div class="stat-label">🔥 Current Streak</div>
        <div class="stat-value"><?php echo $stats['streak']; ?></div>
        <div class="stat-label">Consecutive days</div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-label">🎯 Challenges</div>
        <div class="stat-value"><?php echo $stats['challenges']; ?></div>
        <div class="stat-label">Completed</div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-label">🏅 Achievements</div>
        <div class="stat-value"><?php echo $stats['achievements']; ?></div>
        <div class="stat-label">Unlocked</div>
    </div>
</div>

<div class="card mt-3">
    <h2 class="card-header">Recent Activity</h2>
    <?php if (count($activities) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Activity</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?php echo formatDate($activity['created_at']); ?></td>
                        <td><?php echo clean($activity['description'] ?: ucfirst($activity['source'])); ?></td>
                        <td><strong class="text-primary">+<?php echo $activity['points_earned']; ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No recent activity. Start earning points!</p>
    <?php endif; ?>
</div>

<div class="card-grid mt-3">
    <div class="card">
        <h3>⚡ Quick Actions</h3>
        <a href="/electricity.php" class="btn btn-primary btn-block mb-2">Log Electricity Usage</a>
        <a href="/quiz.php" class="btn btn-secondary btn-block mb-2">Take a Quiz</a>
        <a href="/challenges.php" class="btn btn-success btn-block">View Challenges</a>
    </div>
    
    <div class="card">
        <h3>🌱 Your Progress</h3>
        <p><strong>Department:</strong> <?php echo clean($user['department']); ?></p>
        <p><strong>Member since:</strong> <?php echo formatDate($user['created_at']); ?></p>
        <a href="/tree.php" class="btn btn-success btn-block mt-2">View My Tree</a>
        <a href="/achievements.php" class="btn btn-warning btn-block mt-2">My Achievements</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>