<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (!isLoggedIn()) {
    redirect('/login.php', 'Please login first', 'warning');
}

$user = getCurrentUser();
$userId = $user['id'];

// Get points history
$stmt = $pdo->prepare(
    "SELECT * FROM points_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 100"
);
$stmt->execute([$userId]);
$pointsHistory = $stmt->fetchAll();

// Calculate totals by source
$stmt = $pdo->prepare(
    "SELECT source, SUM(points_earned) as total 
     FROM points_log 
     WHERE user_id = ? 
     GROUP BY source"
);
$stmt->execute([$userId]);
$bySource = $stmt->fetchAll();

$pageTitle = 'Points History';
include __DIR__ . '/includes/header.php';
?>

<h1 class="mb-3">📊 Points History</h1>

<div class="card mb-3">
    <div class="stat-card">
        <div class="stat-label">Total Points Balance</div>
        <div class="stat-value"><?php echo number_format($user['points_total']); ?></div>
    </div>
</div>

<div class="card-grid mb-3">
    <div class="card">
        <h3>Points by Source</h3>
        <?php if (count($bySource) > 0): ?>
            <?php foreach ($bySource as $source): ?>
                <div style="display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid var(--border);">
                    <span><?php echo ucfirst(str_replace('_', ' ', $source['source'])); ?></span>
                    <strong><?php echo number_format($source['total']); ?> pts</strong>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No points earned yet.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2 class="card-header">Transaction History</h2>
    <?php if (count($pointsHistory) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Description</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pointsHistory as $log): ?>
                    <tr>
                        <td><?php echo formatDate($log['created_at']); ?></td>
                        <td>
                            <span class="badge badge-info">
                                <?php echo ucfirst(str_replace('_', ' ', $log['source'])); ?>
                            </span>
                        </td>
                        <td><?php echo clean($log['description']); ?></td>
                        <td>
                            <strong class="<?php echo $log['points_earned'] >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                <?php echo $log['points_earned'] >= 0 ? '+' : ''; ?>
                                <?php echo $log['points_earned']; ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No transaction history.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>