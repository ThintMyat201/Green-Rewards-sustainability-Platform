<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$userId = $user['id'];
$benchmark = (float) getSetting('electricity_benchmark', 150);
$pointsPerKwh = (int) getSetting('points_per_kwh_saved', 5);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int) $_POST['month'];
    $year = (int) $_POST['year'];
    $unitsUsed = (float) $_POST['units_used'];
    
    if ($month < 1 || $month > 12) {
        $error = 'Invalid month';
    } elseif ($year < 2020 || $year > date('Y')) {
        $error = 'Invalid year';
    } elseif ($unitsUsed < 0) {
        $error = 'Invalid units value';
    } else {
        // Check if already logged for this month
        $stmt = $pdo->prepare(
            "SELECT id FROM electricity_logs WHERE user_id = ? AND month = ? AND year = ?"
        );
        $stmt->execute([$userId, $month, $year]);
        
        if ($stmt->fetch()) {
            $error = 'Electricity usage already logged for this month';
        } else {
            // Calculate points
            $pointsAwarded = 0;
            if ($unitsUsed < $benchmark) {
                $saved = $benchmark - $unitsUsed;
                $pointsAwarded = (int) ($saved * $pointsPerKwh);
            }
            
            // Insert electricity log
            $stmt = $pdo->prepare(
                "INSERT INTO electricity_logs (user_id, month, year, units_used, benchmark, points_awarded) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $month, $year, $unitsUsed, $benchmark, $pointsAwarded]);
            
            // Award points if saved
            if ($pointsAwarded > 0) {
                addPoints(
                    $userId, 
                    $pointsAwarded, 
                    'electricity', 
                    "Electricity savings for " . date('F Y', mktime(0, 0, 0, $month, 1, $year))
                );
                checkAchievements($userId);
                $success = "Great job! You saved " . number_format($saved, 2) . " kWh and earned {$pointsAwarded} points!";
            } else {
                $success = "Usage logged. Try to stay under {$benchmark} kWh next month to earn points!";
            }
        }
    }
}

// Get electricity history
$stmt = $pdo->prepare(
    "SELECT * FROM electricity_logs WHERE user_id = ? ORDER BY year DESC, month DESC LIMIT 12"
);
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = 'Electricity Logger';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3 electricity-page-title"><i class="fa-solid fa-bolt"></i> Electricity Usage Logger</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card-grid electricity-layout">
    <div class="card electricity-log-card">
        <h2 class="card-header">Log Monthly Usage</h2>
        <div class="alert alert-info electricity-benchmark-alert">
            <strong><i class="fa-solid fa-bullseye"></i> Current Benchmark:</strong> <?php echo $benchmark; ?> kWh/month<br>
            <strong><i class="fa-solid fa-coins"></i> Reward:</strong> <?php echo $pointsPerKwh; ?> points per kWh saved
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Month *</label>
                <select name="month" required>
                    <option value="">Select Month</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>">
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Year *</label>
                <select name="year" required>
                    <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Units Used (kWh) *</label>
                <input type="number" step="0.01" name="units_used" required 
                       placeholder="e.g., 135.5">
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Log Usage</button>
        </form>
    </div>
    
    <div class="card electricity-history-card">
        <h2 class="card-header">Your History</h2>
        <?php if (count($history) > 0): ?>
            <div class="table-responsive">
                <table class="table electricity-history-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Usage</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $log): ?>
                            <?php 
                            $monthName = date('M Y', mktime(0, 0, 0, $log['month'], 1, $log['year']));
                            $isSaved = $log['units_used'] < $log['benchmark'];
                            ?>
                            <tr>
                                <td><?php echo $monthName; ?></td>
                                <td>
                                    <?php echo number_format($log['units_used'], 2); ?> kWh
                                    <?php if ($isSaved): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Saved</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Over</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>+<?php echo $log['points_awarded']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No electricity logs yet. Start tracking your usage!</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
