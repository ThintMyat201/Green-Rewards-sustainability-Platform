<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (!isLoggedIn()) {
    redirect('/login.php', 'Please login first', 'warning');
}

$user = getCurrentUser();
$isStudent = hasRole('student');
$isStaff = hasRole('staff');

if (!$isStudent && !$isStaff) {
    redirect('/index.php', 'Access denied', 'danger');
}

// Get role filter
$roleFilter = $isStudent ? 'student' : 'staff';
if (isset($_GET['role']) && in_array($_GET['role'], ['student', 'staff'])) {
    $roleFilter = $_GET['role'];
}

// Get leaderboard
$stmt = $pdo->prepare(
    "SELECT id, name, department, points_total, streak_count 
     FROM users 
     WHERE role = ? 
     ORDER BY points_total DESC, streak_count DESC 
     LIMIT 50"
);
$stmt->execute([$roleFilter]);
$leaderboard = $stmt->fetchAll();

// Find current user's position
$userPosition = 0;
foreach ($leaderboard as $index => $entry) {
    if ($entry['id'] == $user['id']) {
        $userPosition = $index + 1;
        break;
    }
}

$pageTitle = 'Leaderboard';
include __DIR__ . '/includes/header.php';
?>

<h1 class="mb-3">🏆 Leaderboard</h1>

<div class="card mb-3">
    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
        <a href="?role=student" 
           class="btn <?php echo $roleFilter === 'student' ? 'btn-primary' : 'btn-secondary'; ?>">
            Student Leaderboard
        </a>
        <a href="?role=staff" 
           class="btn <?php echo $roleFilter === 'staff' ? 'btn-primary' : 'btn-secondary'; ?>">
            Staff Leaderboard
        </a>
    </div>
    
    <?php if ($userPosition > 0): ?>
        <div class="alert alert-info">
            <strong>Your Rank:</strong> #<?php echo $userPosition; ?> with <?php echo number_format($user['points_total']); ?> points
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (count($leaderboard) > 0): ?>
        <?php foreach ($leaderboard as $index => $entry): ?>
            <?php 
            $rank = $index + 1;
            $isCurrentUser = ($entry['id'] == $user['id']);
            $rankClass = '';
            if ($rank === 1) $rankClass = 'top1';
            elseif ($rank === 2) $rankClass = 'top2';
            elseif ($rank === 3) $rankClass = 'top3';
            ?>
            
            <div class="leaderboard-item" style="<?php echo $isCurrentUser ? 'border: 2px solid var(--primary); background: #f0fdf4;' : ''; ?>">
                <div class="leaderboard-rank <?php echo $rankClass; ?>">
                    <?php if ($rank === 1): ?>
                        🥇
                    <?php elseif ($rank === 2): ?>
                        🥈
                    <?php elseif ($rank === 3): ?>
                        🥉
                    <?php else: ?>
                        #<?php echo $rank; ?>
                    <?php endif; ?>
                </div>
                
                <div class="leaderboard-info">
                    <div class="leaderboard-name">
                        <?php echo clean($entry['name']); ?>
                        <?php if ($isCurrentUser): ?>
                            <span class="badge badge-success">You</span>
                        <?php endif; ?>
                    </div>
                    <div class="leaderboard-department">
                        <?php echo clean($entry['department'] ?? 'N/A'); ?> | 
                        🔥 Streak: <?php echo $entry['streak_count']; ?> days
                    </div>
                </div>
                
                <div class="leaderboard-points">
                    <?php echo number_format($entry['points_total']); ?> pts
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No entries on the leaderboard yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>