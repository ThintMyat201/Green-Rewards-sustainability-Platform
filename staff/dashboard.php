<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$department = $user['department'];

// Get department statistics
$stmt = $pdo->prepare(
    "SELECT COUNT(*) as count, SUM(points_total) as total_points 
     FROM users 
     WHERE department = ? AND role IN ('student', 'staff')"
);
$stmt->execute([$department]);
$deptStats = $stmt->fetch();

// Get top performers in department
$stmt = $pdo->prepare(
    "SELECT name, points_total, role 
     FROM users 
     WHERE department = ? AND role IN ('student', 'staff')
     ORDER BY points_total DESC 
     LIMIT 10"
);
$stmt->execute([$department]);
$topPerformers = $stmt->fetchAll();

// Get staff's personal stats
$stmt = $pdo->prepare(
    "SELECT COUNT(*) as count FROM eco_tips WHERE posted_by = ?"
);
$stmt->execute([$user['id']]);
$tipsPosted = $stmt->fetch()['count'];

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as count FROM challenge_submissions 
     WHERE user_id = ? AND status = 'approved'"
);
$stmt->execute([$user['id']]);
$challengesCompleted = $stmt->fetch()['count'];

// Recent department activity
$stmt = $pdo->prepare(
    "SELECT pl.*, u.name 
     FROM points_log pl
     JOIN users u ON pl.user_id = u.id
     WHERE u.department = ?
     ORDER BY pl.created_at DESC
     LIMIT 10"
);
$stmt->execute([$department]);
$recentActivity = $stmt->fetchAll();

$pageTitle = 'Staff Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">Welcome, <?php echo clean($user['name']); ?>! 🌿</h1>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-label">🏆 Your Points</div>
        <div class="stat-value"><?php echo number_format($user['points_total']); ?></div>
        <div class="stat-label">Personal contribution</div>
    </div>
    
    <div class="stat-card blue">
        <div class="stat-label">🏛️ Department</div>
        <div class="stat-value"><?php echo $department; ?></div>
        <div class="stat-label"><?php echo $deptStats['count']; ?> members</div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-label">💬 Tips Posted</div>
        <div class="stat-value"><?php echo $tipsPosted; ?></div>
        <div class="stat-label">Eco awareness contributions</div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-label">🎯 Challenges</div>
        <div class="stat-value"><?php echo $challengesCompleted; ?></div>
        <div class="stat-label">Completed</div>
    </div>
</div>

<div class="card-grid mt-3">
    <div class="card">
        <h2 class="card-header"><?php echo $department; ?> Top Performers</h2>
        <?php if (count($topPerformers) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPerformers as $index => $performer): ?>
                        <tr <?php echo $performer['name'] === $user['name'] ? 'style="background: #f0fdf4; font-weight: bold;"' : ''; ?>>
                            <td>#<?php echo $index + 1; ?></td>
                            <td><?php echo clean($performer['name']); ?></td>
                            <td><?php echo ucfirst($performer['role']); ?></td>
                            <td><?php echo number_format($performer['points_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No data available.</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2 class="card-header">Department Statistics</h2>
        <div class="stat-card">
            <div class="stat-label">Total Department Points</div>
            <div class="stat-value"><?php echo number_format($deptStats['total_points']); ?></div>
        </div>
        <div class="stat-card blue mt-2">
            <div class="stat-label">Average Points per Member</div>
            <div class="stat-value">
                <?php echo $deptStats['count'] > 0 ? number_format($deptStats['total_points'] / $deptStats['count']) : 0; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <h2 class="card-header">Recent Department Activity</h2>
    <?php if (count($recentActivity) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Activity</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentActivity as $activity): ?>
                    <tr>
                        <td><?php echo formatDate($activity['created_at']); ?></td>
                        <td><?php echo clean($activity['name']); ?></td>
                        <td><?php echo clean($activity['description']); ?></td>
                        <td><strong>+<?php echo $activity['points_earned']; ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No recent activity.</p>
    <?php endif; ?>
</div>

<div class="card-grid mt-3">
    <div class="card">
        <h3>⚡ Quick Actions</h3>
        <a href="/eco_tips.php" class="btn btn-primary btn-block mb-2">Post Eco Tip</a>
        <a href="/challenges.php" class="btn btn-secondary btn-block mb-2">View Challenges</a>
        <a href="/leaderboard.php?role=staff" class="btn btn-success btn-block">Staff Leaderboard</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>