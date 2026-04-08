<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

// Overall statistics
$stats = [];

// Total points distributed
$stmt = $pdo->query("SELECT SUM(points_earned) as total FROM points_log WHERE points_earned > 0");
$stats['total_points_given'] = $stmt->fetch()['total'] ?? 0;

// Total points redeemed
$stmt = $pdo->query("SELECT SUM(points_spent) as total FROM redemptions");
$stats['total_points_spent'] = $stmt->fetch()['total'] ?? 0;

// Points by source
$stmt = $pdo->query(
    "SELECT source, SUM(points_earned) as total, COUNT(*) as count 
     FROM points_log 
     WHERE points_earned > 0
     GROUP BY source
     ORDER BY total DESC"
);
$pointsBySource = $stmt->fetchAll();

// Department statistics
$stmt = $pdo->query(
    "SELECT department, COUNT(*) as user_count, SUM(points_total) as total_points 
     FROM users 
     WHERE department IS NOT NULL AND role IN ('student', 'staff')
     GROUP BY department
     ORDER BY total_points DESC"
);
$departmentStats = $stmt->fetchAll();

// Monthly activity (last 6 months)
$stmt = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(points_earned) as points, COUNT(*) as activities
     FROM points_log
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month
     ORDER BY month DESC"
);
$monthlyActivity = $stmt->fetchAll();

// Quiz statistics
$stmt = $pdo->query(
    "SELECT 
        (SELECT COUNT(*) FROM quizzes WHERE is_active = 1) as total_quizzes,
        (SELECT COUNT(*) FROM quiz_attempts) as total_attempts,
        (SELECT COUNT(*) FROM quiz_attempts WHERE is_correct = 1) as correct_attempts"
);
$quizStats = $stmt->fetch();
$quizStats['accuracy'] = $quizStats['total_attempts'] > 0 
    ? round(($quizStats['correct_attempts'] / $quizStats['total_attempts']) * 100) 
    : 0;

$pageTitle = 'System Reports';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3 reports-page-title"><i class="fa-solid fa-chart-line"></i> Sustainability Reports & Analytics</h1>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-star"></i> Total Points Distributed</div>
        <div class="stat-value"><?php echo number_format($stats['total_points_given']); ?></div>
        <div class="stat-label">Across all activities</div>
    </div>
    
    <div class="stat-card blue">
        <div class="stat-label"><i class="fa-solid fa-money-bill-wave"></i> Points Redeemed</div>
        <div class="stat-value"><?php echo number_format($stats['total_points_spent']); ?></div>
        <div class="stat-label">
            <?php 
            $redemptionRate = $stats['total_points_given'] > 0 
                ? round(($stats['total_points_spent'] / $stats['total_points_given']) * 100) 
                : 0;
            echo $redemptionRate . '% redemption rate';
            ?>
        </div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-label"><i class="fa-solid fa-clipboard-question"></i> Quiz Accuracy</div>
        <div class="stat-value"><?php echo $quizStats['accuracy']; ?>%</div>
        <div class="stat-label"><?php echo number_format($quizStats['total_attempts']); ?> attempts</div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-label"><i class="fa-solid fa-building"></i> Active Departments</div>
        <div class="stat-value"><?php echo count($departmentStats); ?></div>
        <div class="stat-label">Participating</div>
    </div>
</div>

<div class="card-grid mt-3 admin-reports-secondary-cards">
    <div class="card">
        <h2 class="card-header"><i class="fa-solid fa-chart-simple"></i> Points by Source</h2>
        <?php if (count($pointsBySource) > 0): ?>
            <?php foreach ($pointsBySource as $source): ?>
                <?php 
                $percentage = $stats['total_points_given'] > 0 
                    ? round(($source['total'] / $stats['total_points_given']) * 100) 
                    : 0;
                ?>
                <div class="stack-list-tight stack-block">
                    <div class="responsive-row">
                        <span><?php echo ucfirst(str_replace('_', ' ', $source['source'])); ?></span>
                        <strong class="responsive-row-value"><?php echo number_format($source['total']); ?> pts (<?php echo $percentage; ?>%)</strong>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No data available.</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2 class="card-header"><i class="fa-solid fa-building-columns"></i> Department Performance</h2>
        <?php if (count($departmentStats) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Members</th>
                            <th>Total Points</th>
                            <th>Avg/Member</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departmentStats as $dept): ?>
                            <?php $avg = $dept['user_count'] > 0 ? round($dept['total_points'] / $dept['user_count']) : 0; ?>
                            <tr>
                                <td><strong><?php echo clean($dept['department']); ?></strong></td>
                                <td><?php echo $dept['user_count']; ?></td>
                                <td><?php echo number_format($dept['total_points']); ?></td>
                                <td><?php echo number_format($avg); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No department data.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-3">
    <h2 class="card-header"><i class="fa-solid fa-calendar-days"></i> Monthly Activity Trend</h2>
    <?php if (count($monthlyActivity) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Points</th>
                        <th>Activities</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyActivity as $month): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                            <td><strong><?php echo number_format($month['points']); ?></strong></td>
                            <td><?php echo number_format($month['activities']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No monthly data available.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
