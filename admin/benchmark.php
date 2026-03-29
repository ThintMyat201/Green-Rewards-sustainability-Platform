<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$error = '';
$success = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benchmark = (float) ($_POST['electricity_benchmark'] ?? 150);
    $pointsPerKwh = (int) ($_POST['points_per_kwh_saved'] ?? 5);
    $quizLimit = (int) ($_POST['quiz_daily_limit'] ?? 5);
    $streak7 = (int) ($_POST['streak_bonus_7days'] ?? 50);
    $streak30 = (int) ($_POST['streak_bonus_30days'] ?? 200);
    
    try {
        updateSetting('electricity_benchmark', $benchmark, $user['id']);
        updateSetting('points_per_kwh_saved', $pointsPerKwh, $user['id']);
        updateSetting('quiz_daily_limit', $quizLimit, $user['id']);
        updateSetting('streak_bonus_7days', $streak7, $user['id']);
        updateSetting('streak_bonus_30days', $streak30, $user['id']);
        
        $success = 'Settings updated successfully!';
    } catch (Exception $e) {
        $error = 'Failed to update settings';
    }
}

// Get current settings
$currentSettings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value, description, updated_at FROM settings");
while ($row = $stmt->fetch()) {
    $currentSettings[$row['setting_key']] = $row;
}

$pageTitle = 'System Settings';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">⚙️ System Settings & Benchmarks</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="card-header">Configure System Parameters</h2>
    
    <form method="POST">
        <h3 class="mt-3">⚡ Electricity Settings</h3>
        
        <div class="form-group">
            <label>Monthly Electricity Benchmark (kWh) *</label>
            <input type="number" step="0.01" name="electricity_benchmark" 
                   value="<?php echo $currentSettings['electricity_benchmark']['setting_value'] ?? 150; ?>" 
                   required>
            <small class="text-muted">Students who use less than this amount earn points</small>
        </div>
        
        <div class="form-group">
            <label>Points per kWh Saved *</label>
            <input type="number" name="points_per_kwh_saved" 
                   value="<?php echo $currentSettings['points_per_kwh_saved']['setting_value'] ?? 5; ?>" 
                   required>
            <small class="text-muted">Points awarded for each kWh saved below benchmark</small>
        </div>
        
        <hr>
        
        <h3 class="mt-3">📝 Quiz Settings</h3>
        
        <div class="form-group">
            <label>Daily Quiz Limit per User *</label>
            <input type="number" name="quiz_daily_limit" 
                   value="<?php echo $currentSettings['quiz_daily_limit']['setting_value'] ?? 5; ?>" 
                   required>
            <small class="text-muted">Maximum quizzes a user can attempt per day</small>
        </div>
        
        <hr>
        
        <h3 class="mt-3">🔥 Streak Bonuses</h3>
        
        <div class="form-group">
            <label>7-Day Streak Bonus (points) *</label>
            <input type="number" name="streak_bonus_7days" 
                   value="<?php echo $currentSettings['streak_bonus_7days']['setting_value'] ?? 50; ?>" 
                   required>
            <small class="text-muted">Bonus points for maintaining 7-day activity streak</small>
        </div>
        
        <div class="form-group">
            <label>30-Day Streak Bonus (points) *</label>
            <input type="number" name="streak_bonus_30days" 
                   value="<?php echo $currentSettings['streak_bonus_30days']['setting_value'] ?? 200; ?>" 
                   required>
            <small class="text-muted">Bonus points for maintaining 30-day activity streak</small>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block mt-3">Save Settings</button>
    </form>
</div>

<div class="card mt-3">
    <h2 class="card-header">Current Settings Overview</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Setting</th>
                <th>Value</th>
                <th>Description</th>
                <th>Last Updated</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($currentSettings as $key => $setting): ?>
                <tr>
                    <td><strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?></strong></td>
                    <td><?php echo clean($setting['setting_value']); ?></td>
                    <td><?php echo clean($setting['description']); ?></td>
                    <td><?php echo formatDate($setting['updated_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card mt-3">
    <h2 class="card-header">💡 Tips</h2>
    <ul style="line-height: 2;">
        <li>Set realistic benchmarks to encourage participation</li>
        <li>Adjust points rewards based on difficulty and impact</li>
        <li>Monitor redemption rates and adjust reward costs accordingly</li>
        <li>Review reports regularly to optimize the system</li>
    </ul>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
