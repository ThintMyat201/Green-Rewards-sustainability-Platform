<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php', 'Access denied', 'danger');
}

// Get system statistics
$stats = [];

// Total users by role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$usersByRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$stats['total_users'] = array_sum($usersByRole);
$stats['students'] = $usersByRole['student'] ?? 0;
$stats['staff'] = $usersByRole['staff'] ?? 0;
$stats['moderators'] = $usersByRole['moderator'] ?? 0;

// Total points distributed
$stmt = $pdo->query("SELECT SUM(points_total) as total FROM users");
$stats['total_points'] = $stmt->fetch()['total'] ?? 0;

// Active challenges
$stmt = $pdo->query("SELECT COUNT(*) as count FROM challenges WHERE is_active = 1 AND end_date >= CURDATE()");
$stats['active_challenges'] = $stmt->fetch()['count'];

// Pending submissions
$stmt = $pdo->query("SELECT COUNT(*) as count FROM challenge_submissions WHERE status = 'pending'");
$stats['pending_submissions'] = $stmt->fetch()['count'];

// Total redemptions
$stmt = $pdo->query("SELECT COUNT(*) as count, SUM(points_spent) as points FROM redemptions");
$redemptionData = $stmt->fetch();
$stats['redemptions'] = $redemptionData['count'] ?? 0;
$stats['points_redeemed'] = $redemptionData['points'] ?? 0;

// Recent activity
$stmt = $pdo->query(
    "SELECT pl.*, u.name, u.role 
     FROM points_log pl
     JOIN users u ON pl.user_id = u.id
     ORDER BY pl.created_at DESC
     LIMIT 15"
);
$recentActivity = $stmt->fetchAll();

// Top performers
$stmt = $pdo->query(
    "SELECT name, role, department, points_total 
     FROM users 
     WHERE role IN ('student', 'staff')
     ORDER BY points_total DESC 
     LIMIT 10"
);
$topPerformers = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">🛠️ Admin Dashboard</h1>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-label">👥 Total Users</div>
        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
        <div class="stat-label">Students: <?php echo $stats['students']; ?> | Staff: <?php echo $stats['staff']; ?></div>
    </div>
    
    <div class="stat-card blue">
        <div class="stat-label">⭐ Total Points</div>
        <div class="stat-value"><?php echo number_format($stats['total_points']); ?></div>
        <div class="stat-label">Distributed across system</div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-label">🎯 Active Challenges</div>
        <div class="stat-value"><?php echo $stats['active_challenges']; ?></div>
        <div class="stat-label">Pending: <?php echo $stats['pending_submissions']; ?></div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-label">🎁 Redemptions</div>
        <div class="stat-value"><?php echo number_format($stats['redemptions']); ?></div>
        <div class="stat-label"><?php echo number_format($stats['points_redeemed']); ?> pts spent</div>
    </div>
</div>

<div class="card-grid mt-3">
    <div class="card">
        <h2 class="card-header">🏆 Top Performers</h2>
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
                        <tr>
                            <td>#<?php echo $index + 1; ?></td>
                            <td><?php echo clean($performer['name']); ?></td>
                            <td><?php echo ucfirst($performer['role']); ?></td>
                            <td><strong><?php echo number_format($performer['points_total']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No data available.</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2 class="card-header">📈 Quick Stats</h2>
        <div style="padding: 1rem 0;">
            <div style="display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid var(--border);">
                <span>Students</span>
                <strong><?php echo $stats['students']; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid var(--border);">
                <span>Staff Members</span>
                <strong><?php echo $stats['staff']; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid var(--border);">
                <span>Moderators</span>
                <strong><?php echo $stats['moderators']; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.8rem 0;">
                <span>Avg Points/User</span>
                <strong><?php echo $stats['total_users'] > 0 ? number_format($stats['total_points'] / $stats['total_users']) : 0; ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <h2 class="card-header">Recent System Activity</h2>
    <?php if (count($recentActivity) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Activity</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentActivity as $activity): ?>
                    <tr>
                        <td><?php echo formatDate($activity['created_at']); ?></td>
                        <td><?php echo clean($activity['name']); ?></td>
                        <td><?php echo ucfirst($activity['role']); ?></td>
                        <td><?php echo clean($activity['description']); ?></td>
                        <td>
                            <strong class="<?php echo $activity['points_earned'] >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                <?php echo $activity['points_earned'] >= 0 ? '+' : ''; ?>
                                <?php echo $activity['points_earned']; ?>
                            </strong>
                        </td>
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
        <a href="/admin/users.php" class="btn btn-primary btn-block mb-2">Manage Users</a>
        <a href="/admin/rewards.php" class="btn btn-secondary btn-block mb-2">Manage Rewards</a>
        <a href="/admin/reports.php" class="btn btn-success btn-block mb-2">View Reports</a>
        <a href="/admin/benchmark.php" class="btn btn-warning btn-block">System Settings</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>