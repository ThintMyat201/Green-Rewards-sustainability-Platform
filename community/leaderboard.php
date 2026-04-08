<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please login first', 'warning');
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
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-trophy"></i> Leaderboard</h1>

<div class="card mb-3 leaderboard-header-card">
    <div class="leaderboard-role-switch" role="tablist" aria-label="Leaderboard role filter">
        <a
            href="?role=student"
            role="tab"
            aria-selected="<?php echo $roleFilter === 'student' ? 'true' : 'false'; ?>"
            class="leaderboard-switch-option <?php echo $roleFilter === 'student' ? 'is-active' : ''; ?>"
        >
            Student
        </a>
        <a
            href="?role=staff"
            role="tab"
            aria-selected="<?php echo $roleFilter === 'staff' ? 'true' : 'false'; ?>"
            class="leaderboard-switch-option <?php echo $roleFilter === 'staff' ? 'is-active' : ''; ?>"
        >
            Staff
        </a>
    </div>
    
    <?php if ($userPosition > 0): ?>
        <div class="alert alert-info leaderboard-user-rank">
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
            
            <div class="leaderboard-item <?php echo $isCurrentUser ? 'is-current-user' : ''; ?>">
                <div class="leaderboard-rank <?php echo $rankClass; ?>">
                    <?php if ($rank === 1): ?>
                        <i class="fa-solid fa-medal"></i>
                    <?php elseif ($rank === 2): ?>
                        <i class="fa-solid fa-medal"></i>
                    <?php elseif ($rank === 3): ?>
                        <i class="fa-solid fa-medal"></i>
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
                        <i class="fa-solid fa-fire"></i> Streak: <?php echo $entry['streak_count']; ?> days
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
