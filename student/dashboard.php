<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
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

// Get recent eco tips
$stmt = $pdo->query(
    "SELECT et.*, u.name as author_name, u.role as author_role 
     FROM eco_tips et
     JOIN users u ON et.posted_by = u.id
     ORDER BY et.created_at DESC
     LIMIT 4"
);
$ecoTips = $stmt->fetchAll();

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

<h1 class="mb-3">Welcome back, <?php echo clean($user['name']); ?>! <i class="fa-solid fa-seedling"></i></h1>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-trophy"></i> Total Points</div>
        <div class="stat-value"><?php echo number_format($stats['points']); ?></div>
        <div class="stat-label">Rank #<?php echo $position; ?> on leaderboard</div>
    </div>
    
    <div class="stat-card blue">
        <div class="stat-label"><i class="fa-solid fa-fire"></i> Current Streak</div>
        <div class="stat-value"><?php echo $stats['streak']; ?></div>
        <div class="stat-label">Consecutive days</div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-label"><i class="fa-solid fa-bullseye"></i> Challenges</div>
        <div class="stat-value"><?php echo $stats['challenges']; ?></div>
        <div class="stat-label">Completed</div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-label"><i class="fa-solid fa-medal"></i> Achievements</div>
        <div class="stat-value"><?php echo $stats['achievements']; ?></div>
        <div class="stat-label">Unlocked</div>
    </div>
</div>

<div class="card mt-3">
    <h2 class="card-header"><i class="fa-solid fa-lightbulb"></i> Latest Eco Tips</h2>
    <?php if (count($ecoTips) > 0): ?>
        <div class="stack-list">
            <?php foreach ($ecoTips as $tip): ?>
                <div class="content-panel">
                    <div class="responsive-row content-panel-top">
                        <span class="badge badge-info">
                            <?php echo ucfirst($tip['category']); ?>
                        </span>
                        <span class="content-panel-meta">
                            <?php echo formatDate($tip['created_at']); ?>
                        </span>
                    </div>
                    <p class="content-panel-copy">
                        <?php echo nl2br(clean($tip['content'])); ?>
                    </p>
                    <p class="text-muted content-panel-footer">
                        <i class="fa-solid fa-user"></i> <?php echo clean($tip['author_name']); ?> (<?php echo ucfirst($tip['author_role']); ?>)
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-2">
            <a href="<?php echo appUrl('/community/eco_tips.php'); ?>" class="btn btn-secondary btn-sm">View All Tips</a>
        </div>
    <?php else: ?>
        <p class="text-muted">No eco tips available yet. Check back soon!</p>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <h2 class="card-header">Recent Activity</h2>
    <?php if (count($activities) > 0): ?>
        <div class="table-responsive">
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
        </div>
    <?php else: ?>
        <p class="text-muted">No recent activity. Start earning points!</p>
    <?php endif; ?>
</div>

<div class="card-grid mt-3">
    <div class="card">
        <h3><i class="fa-solid fa-seedling"></i> Your Progress</h3>
        <p><strong>Department:</strong> <?php echo clean($user['department']); ?></p>
        <p><strong>Member since:</strong> <?php echo formatDate($user['created_at']); ?></p>
        <a href="<?php echo appUrl('/student/tree.php'); ?>" class="btn btn-success btn-block mt-2">View My Tree</a>
        <a href="<?php echo appUrl('/student/achievements.php'); ?>" class="btn btn-warning btn-block mt-2">My Achievements</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
