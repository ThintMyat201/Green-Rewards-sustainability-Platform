<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$points = $user['points_total'];

// Determine tree stage based on points
$stage = 'Seed';
$emoji = '<i class="fa-solid fa-seedling"></i>';
$nextStage = 'Sprout';
$nextPoints = 100;

if ($points >= 2000) {
    $stage = 'Mighty Oak';
    $emoji = '<i class="fa-solid fa-tree"></i>';
    $nextStage = 'Forest!';
    $nextPoints = 5000;
} elseif ($points >= 1000) {
    $stage = 'Young Tree';
    $emoji = '<i class="fa-solid fa-tree"></i>';
    $nextStage = 'Mighty Oak';
    $nextPoints = 2000;
} elseif ($points >= 500) {
    $stage = 'Sapling';
    $emoji = '<i class="fa-solid fa-seedling"></i>';
    $nextStage = 'Young Tree';
    $nextPoints = 1000;
} elseif ($points >= 100) {
    $stage = 'Sprout';
    $emoji = '<i class="fa-solid fa-seedling"></i>';
    $nextStage = 'Sapling';
    $nextPoints = 500;
}

$progressToNext = $points >= 2000 ? 100 : (($points / $nextPoints) * 100);

$pageTitle = 'My Virtual Tree';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3 text-center"><i class="fa-solid fa-tree"></i> My Virtual Tree Pet</h1>

<div class="card">
    <div class="tree-container">
        <div class="tree-visual"><?php echo $emoji; ?></div>
        <div class="tree-stage"><?php echo $stage; ?></div>
        <p class="text-center" style="font-size: 1.1rem; color: var(--gray);">
            Your tree grows as you earn points!
        </p>
    </div>
</div>

<div class="card mt-3">
    <h2 class="card-header">Growth Progress</h2>
    <div class="stat-card">
        <div class="stat-label">Current Points</div>
        <div class="stat-value"><?php echo number_format($points); ?></div>
    </div>
    
    <?php if ($points < 2000): ?>
        <div class="mt-3">
            <p><strong>Next Stage:</strong> <?php echo $nextStage; ?></p>
            <p><strong>Points Needed:</strong> <?php echo number_format($nextPoints - $points); ?> more points</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min($progressToNext, 100); ?>%">
                    <?php echo round($progressToNext); ?>%
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success mt-3">
            <strong><i class="fa-solid fa-circle-check"></i> Congratulations!</strong> Your tree has reached maximum growth!
        </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <h2 class="card-header">Growth Stages</h2>
    <div class="card-grid">
        <div class="card <?php echo $points >= 0 ? 'badge-success' : ''; ?>">
            <div style="font-size: 2rem; text-align: center;"><i class="fa-solid fa-seedling"></i></div>
            <h4 class="text-center">Seed</h4>
            <p class="text-center">0 points</p>
        </div>
        <div class="card <?php echo $points >= 100 ? 'badge-success' : ''; ?>">
            <div style="font-size: 2rem; text-align: center;"><i class="fa-solid fa-seedling"></i></div>
            <h4 class="text-center">Sprout</h4>
            <p class="text-center">100 points</p>
        </div>
        <div class="card <?php echo $points >= 500 ? 'badge-success' : ''; ?>">
            <div style="font-size: 2rem; text-align: center;"><i class="fa-solid fa-seedling"></i></div>
            <h4 class="text-center">Sapling</h4>
            <p class="text-center">500 points</p>
        </div>
        <div class="card <?php echo $points >= 1000 ? 'badge-success' : ''; ?>">
            <div style="font-size: 2rem; text-align: center;"><i class="fa-solid fa-tree"></i></div>
            <h4 class="text-center">Young Tree</h4>
            <p class="text-center">1000 points</p>
        </div>
        <div class="card <?php echo $points >= 2000 ? 'badge-success' : ''; ?>">
            <div style="font-size: 2rem; text-align: center;"><i class="fa-solid fa-tree"></i></div>
            <h4 class="text-center">Mighty Oak</h4>
            <p class="text-center">2000 points</p>
        </div>
    </div>
</div>

<div class="text-center mt-3 tree-actions">
    <a href="<?php echo appUrl('/student/dashboard.php'); ?>" class="btn btn-primary">Back to Dashboard</a>
    <a href="<?php echo appUrl('/community/challenges.php'); ?>" class="btn btn-success">Earn More Points</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
