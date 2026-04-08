<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please login first', 'warning');
}

$user = getCurrentUser();
$userId = $user['id'];

$historyPerPage = 10;
$currentHistoryPage = isset($_GET['history_page']) ? (int) $_GET['history_page'] : 1;
$currentHistoryPage = max(1, $currentHistoryPage);

// Get points history
$stmt = $pdo->prepare(
    "SELECT COUNT(*) as count
     FROM (
         SELECT id
         FROM points_log
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 20
     ) AS latest_points_history"
);
$stmt->execute([$userId]);
$totalHistoryRows = (int) ($stmt->fetch()['count'] ?? 0);
$totalHistoryPages = max(1, (int) ceil($totalHistoryRows / $historyPerPage));
$currentHistoryPage = min($currentHistoryPage, $totalHistoryPages);
$historyOffset = ($currentHistoryPage - 1) * $historyPerPage;

$stmt = $pdo->prepare(
    "SELECT *
     FROM (
         SELECT *
         FROM points_log
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 20
     ) AS latest_points_history
     ORDER BY created_at DESC
     LIMIT {$historyPerPage} OFFSET {$historyOffset}"
);
$stmt->execute([$userId]);
$pointsHistory = $stmt->fetchAll();

$historyStartRow = $totalHistoryRows > 0 ? $historyOffset + 1 : 0;
$historyEndRow = min($historyOffset + $historyPerPage, $totalHistoryRows);

$historyBaseQuery = $_GET;
unset($historyBaseQuery['history_page']);
$historyBasePath = strtok($_SERVER['REQUEST_URI'], '?');

$buildHistoryPageUrl = static function (int $page) use ($historyBaseQuery, $historyBasePath): string {
    $query = $historyBaseQuery;
    $query['history_page'] = $page;
    return $historyBasePath . '?' . http_build_query($query);
};

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
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-chart-simple"></i> Points History</h1>

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
                <div class="responsive-row responsive-row-border">
                    <span><?php echo ucfirst(str_replace('_', ' ', $source['source'])); ?></span>
                    <strong class="responsive-row-value"><?php echo number_format($source['total']); ?> pts</strong>
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
        <div class="table-responsive points-history-table-wrap">
            <table class="table points-history-table">
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
        </div>

        <?php if ($totalHistoryPages > 1): ?>
            <div class="pagination-wrap">
                <p class="pagination-summary">
                    Showing <?php echo $historyStartRow; ?>-<?php echo $historyEndRow; ?> of <?php echo $totalHistoryRows; ?> recent records
                </p>
                <nav class="pagination" aria-label="Points history pages">
                    <?php if ($currentHistoryPage > 1): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildHistoryPageUrl($currentHistoryPage - 1)); ?>">Previous</a>
                    <?php endif; ?>

                    <span class="pagination-page">Page <?php echo $currentHistoryPage; ?> of <?php echo $totalHistoryPages; ?></span>

                    <?php if ($currentHistoryPage < $totalHistoryPages): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildHistoryPageUrl($currentHistoryPage + 1)); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted">No transaction history.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
