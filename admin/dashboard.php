<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
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

// Recent activity pagination
$activityPerPage = 10;
$currentActivityPage = isset($_GET['activity_page']) ? (int) $_GET['activity_page'] : 1;
$currentActivityPage = max(1, $currentActivityPage);

$stmt = $pdo->query(
    "SELECT COUNT(*) as count
     FROM (
         SELECT id
         FROM points_log
         ORDER BY created_at DESC
         LIMIT 20
     ) AS latest_activity"
);
$totalActivityRows = (int) ($stmt->fetch()['count'] ?? 0);
$totalActivityPages = max(1, (int) ceil($totalActivityRows / $activityPerPage));
$currentActivityPage = min($currentActivityPage, $totalActivityPages);
$activityOffset = ($currentActivityPage - 1) * $activityPerPage;

$stmt = $pdo->query(
    "SELECT pl.*, u.name, u.role
     FROM (
         SELECT *
         FROM points_log
         ORDER BY created_at DESC
         LIMIT 20
     ) pl
     JOIN users u ON pl.user_id = u.id
     ORDER BY pl.created_at DESC
     LIMIT {$activityPerPage} OFFSET {$activityOffset}"
);
$recentActivity = $stmt->fetchAll();

$activityStartRow = $totalActivityRows > 0 ? $activityOffset + 1 : 0;
$activityEndRow = min($activityOffset + $activityPerPage, $totalActivityRows);

$activityBaseQuery = $_GET;
unset($activityBaseQuery['activity_page']);
$activityBasePath = strtok($_SERVER['REQUEST_URI'], '?');

$buildActivityPageUrl = static function (int $page) use ($activityBaseQuery, $activityBasePath): string {
    $query = $activityBaseQuery;
    $query['activity_page'] = $page;
    return $activityBasePath . '?' . http_build_query($query);
};

// Top performers
$stmt = $pdo->query(
    "SELECT name, role, department, points_total 
     FROM users 
     WHERE role IN ('student', 'staff')
     ORDER BY points_total DESC 
    LIMIT 4"
);
$topPerformers = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">Admin Dashboard</h1>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-users"></i> Total Users</div>
        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
        <div class="stat-label">Students: <?php echo $stats['students']; ?> | Staff: <?php echo $stats['staff']; ?></div>
    </div>
    
    <div class="stat-card blue">
        <div class="stat-label"><i class="fa-solid fa-star"></i> Total Points</div>
        <div class="stat-value"><?php echo number_format($stats['total_points']); ?></div>
        <div class="stat-label">Distributed across system</div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-label"><i class="fa-solid fa-bullseye"></i> Active Challenges</div>
        <div class="stat-value"><?php echo $stats['active_challenges']; ?></div>
        <div class="stat-label">Pending: <?php echo $stats['pending_submissions']; ?></div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-label"><i class="fa-solid fa-gift"></i> Redemptions</div>
        <div class="stat-value"><?php echo number_format($stats['redemptions']); ?></div>
        <div class="stat-label"><?php echo number_format($stats['points_redeemed']); ?> pts spent</div>
    </div>
</div>

<div class="card-grid mt-3 admin-dashboard-secondary-cards">
    <div class="card">
        <h2 class="card-header"><i class="fa-solid fa-trophy"></i> Top Performers</h2>
        <?php if (count($topPerformers) > 0): ?>
            <div class="table-responsive">
                <table class="table admin-top-performers-table">
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
                                <td data-label="Rank">#<?php echo $index + 1; ?></td>
                                <td data-label="Name"><?php echo clean($performer['name']); ?></td>
                                <td data-label="Role"><?php echo ucfirst($performer['role']); ?></td>
                                <td data-label="Points"><strong><?php echo number_format($performer['points_total']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No data available.</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2 class="card-header"><i class="fa-solid fa-chart-line"></i> Quick Stats</h2>
        <div class="stack-list stack-list-tight stack-block">
            <div class="responsive-row responsive-row-border">
                <span>Students</span>
                <strong class="responsive-row-value"><?php echo $stats['students']; ?></strong>
            </div>
            <div class="responsive-row responsive-row-border">
                <span>Staff Members</span>
                <strong class="responsive-row-value"><?php echo $stats['staff']; ?></strong>
            </div>
            <div class="responsive-row responsive-row-border">
                <span>Moderators</span>
                <strong class="responsive-row-value"><?php echo $stats['moderators']; ?></strong>
            </div>
            <div class="responsive-row">
                <span>Avg Points/User</span>
                <strong class="responsive-row-value"><?php echo $stats['total_users'] > 0 ? number_format($stats['total_points'] / $stats['total_users']) : 0; ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <h2 class="card-header">Recent System Activity</h2>
    <?php if (count($recentActivity) > 0): ?>
        <div class="table-responsive">
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
        </div>

        <?php if ($totalActivityPages > 1): ?>
            <div class="pagination-wrap">
                <p class="pagination-summary">
                    Showing <?php echo $activityStartRow; ?>-<?php echo $activityEndRow; ?> of <?php echo $totalActivityRows; ?> activities
                </p>
                <nav class="pagination" aria-label="Recent activity pages">
                    <?php if ($currentActivityPage > 1): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildActivityPageUrl($currentActivityPage - 1)); ?>">Previous</a>
                    <?php endif; ?>

                    <span class="pagination-page">Page <?php echo $currentActivityPage; ?> of <?php echo $totalActivityPages; ?></span>

                    <?php if ($currentActivityPage < $totalActivityPages): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildActivityPageUrl($currentActivityPage + 1)); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted">No recent activity.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
