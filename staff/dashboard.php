<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
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
    LIMIT 6"
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

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as count, COALESCE(SUM(points_spent), 0) as points_spent
     FROM redemptions
     WHERE user_id = ?"
);
$stmt->execute([$user['id']]);
$redemptionStats = $stmt->fetch();
$redemptionsCount = (int) ($redemptionStats['count'] ?? 0);
$pointsSpent = (int) ($redemptionStats['points_spent'] ?? 0);

// Recent department activity
$activityPerPage = 10;
$currentActivityPage = isset($_GET['activity_page']) ? (int) $_GET['activity_page'] : 1;
$currentActivityPage = max(1, $currentActivityPage);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as total
     FROM (
         SELECT pl.id
         FROM points_log pl
         JOIN users u ON pl.user_id = u.id
         WHERE u.department = ?
         ORDER BY pl.created_at DESC
         LIMIT 20
     ) AS latest_department_activity"
);
$stmt->execute([$department]);
$totalRecentActivity = (int) ($stmt->fetch()['total'] ?? 0);
$totalActivityPages = max(1, (int) ceil($totalRecentActivity / $activityPerPage));
$currentActivityPage = min($currentActivityPage, $totalActivityPages);
$activityOffset = ($currentActivityPage - 1) * $activityPerPage;

$stmt = $pdo->prepare(
    "SELECT pl.*, u.name
     FROM (
         SELECT pl.*
         FROM points_log pl
         JOIN users u ON pl.user_id = u.id
         WHERE u.department = ?
         ORDER BY pl.created_at DESC
         LIMIT 20
     ) pl
     JOIN users u ON pl.user_id = u.id
     ORDER BY pl.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$department, $activityPerPage, $activityOffset]);
$recentActivity = $stmt->fetchAll();

$activityStartRow = $totalRecentActivity > 0 ? $activityOffset + 1 : 0;
$activityEndRow = min($activityOffset + $activityPerPage, $totalRecentActivity);

$activityBaseQuery = $_GET;
unset($activityBaseQuery['activity_page']);
$activityBasePath = strtok($_SERVER['REQUEST_URI'], '?');

$buildActivityPageUrl = static function (int $page) use ($activityBaseQuery, $activityBasePath): string {
    $query = $activityBaseQuery;
    $query['activity_page'] = $page;
    return $activityBasePath . '?' . http_build_query($query);
};

$pageTitle = 'Staff Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">Welcome, <?php echo clean($user['name']); ?>! <i class="fa-solid fa-seedling"></i></h1>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-trophy"></i> Your Points</div>
        <div class="stat-value"><?php echo number_format($user['points_total']); ?></div>
        <div class="stat-label">Personal contribution</div>
    </div>
    
    <div class="stat-card blue">
        <div class="stat-label"><i class="fa-solid fa-building-columns"></i> Department</div>
        <div class="stat-value"><?php echo $department; ?></div>
        <div class="stat-label"><?php echo $deptStats['count']; ?> members</div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-label"><i class="fa-solid fa-comments"></i> Tips Posted</div>
        <div class="stat-value"><?php echo $tipsPosted; ?></div>
        <div class="stat-label">Eco awareness contributions</div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-label"><i class="fa-solid fa-bullseye"></i> Challenges</div>
        <div class="stat-value"><?php echo $challengesCompleted; ?></div>
        <div class="stat-label">Completed</div>
    </div>

    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-gift"></i> Rewards</div>
        <div class="stat-value"><?php echo $redemptionsCount; ?></div>
        <div class="stat-label"><?php echo number_format($pointsSpent); ?> points spent</div>
    </div>
</div>

<div class="card-grid mt-3 staff-dashboard-secondary-cards">
    <div class="card staff-top-performers-card">
        <h2 class="card-header"><?php echo $department; ?> Top Performers</h2>
        <?php if (count($topPerformers) > 0): ?>
            <div class="table-responsive">
                <table class="table staff-top-performers-table">
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
            </div>
        <?php else: ?>
            <p class="text-muted">No data available.</p>
        <?php endif; ?>
    </div>
    
    <div class="card staff-department-stats-card">
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
        <div class="table-responsive">
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
        </div>

        <?php if ($totalActivityPages > 1): ?>
            <div class="pagination-wrap">
                <p class="pagination-summary">
                    Showing <?php echo $activityStartRow; ?>-<?php echo $activityEndRow; ?> of <?php echo $totalRecentActivity; ?> activities
                </p>
                <nav class="pagination" aria-label="Recent department activity pages">
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
