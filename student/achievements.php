<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$userId = $user['id'];

// Get all achievements
$stmt = $pdo->query("SELECT * FROM achievements ORDER BY condition_value ASC");
$allAchievements = $stmt->fetchAll();

// Get user's unlocked achievements
$stmt = $pdo->prepare(
    "SELECT ua.*, a.name, a.description, a.badge_icon, ua.unlocked_at AS unlock_time
     FROM user_achievements ua
     JOIN achievements a ON ua.achievement_id = a.id
     WHERE ua.user_id = ?
     ORDER BY ua.unlocked_at DESC"
);
$stmt->execute([$userId]);
$unlockedAchievements = $stmt->fetchAll();

// Create array of unlocked achievement IDs
$unlockedIds = array_column($unlockedAchievements, 'achievement_id');

$pageTitle = 'My Achievements';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-medal"></i> My Achievements</h1>

<div class="alert alert-info">
    <strong>Progress:</strong> <?php echo count($unlockedAchievements); ?> / <?php echo count($allAchievements); ?> achievements unlocked
</div>

<div class="card mb-3">
    <div class="progress-bar">
        <?php 
        $progress = count($allAchievements) > 0 
            ? (count($unlockedAchievements) / count($allAchievements)) * 100 
            : 0;
        ?>
        <div class="progress-fill" style="width: <?php echo $progress; ?>%">
            <?php echo round($progress); ?>%
        </div>
    </div>
</div>

<h2 class="mt-3 mb-2"><i class="fa-solid fa-circle-check"></i> Unlocked Achievements</h2>
<div class="card-grid">
    <?php if (count($unlockedAchievements) > 0): ?>
        <?php foreach ($unlockedAchievements as $achievement): ?>
            <div class="card" style="border: 3px solid var(--success); background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                <div style="font-size: 3rem; text-align: center;">
                    <?php 
                    $icons = [
                        'seedling' => '<i class="fa-solid fa-seedling"></i>',
                        'book' => '<i class="fa-solid fa-book-open"></i>',
                        'bolt' => '<i class="fa-solid fa-bolt"></i>',
                        'graduation' => '<i class="fa-solid fa-graduation-cap"></i>',
                        'coin' => '<i class="fa-solid fa-coins"></i>',
                        'trophy' => '<i class="fa-solid fa-trophy"></i>',
                        'target' => '<i class="fa-solid fa-bullseye"></i>',
                        'sword' => '<i class="fa-solid fa-sword"></i>',
                        'fire' => '<i class="fa-solid fa-fire"></i>',
                        'crown' => '<i class="fa-solid fa-crown"></i>'
                    ];
                    echo $icons[$achievement['badge_icon']] ?? '<i class="fa-solid fa-star"></i>';
                    ?>
                </div>
                <h3 class="text-center mt-2"><?php echo clean($achievement['name']); ?></h3>
                <p class="text-center text-muted"><?php echo clean($achievement['description']); ?></p>
                <p class="text-center"><strong>Unlocked:</strong> <?php echo formatDate($achievement['unlock_time']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">
            <p class="text-muted">No achievements unlocked yet. Start earning points!</p>
        </div>
    <?php endif; ?>
</div>

<h2 class="mt-4 mb-2"><i class="fa-solid fa-lock"></i> Locked Achievements</h2>
<div class="card-grid">
    <?php foreach ($allAchievements as $achievement): ?>
        <?php if (!in_array($achievement['id'], $unlockedIds)): ?>
            <div class="card" style="opacity: 0.6; border: 2px dashed var(--gray);">
                <div style="font-size: 3rem; text-align: center; filter: grayscale(100%);">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h3 class="text-center mt-2"><?php echo clean($achievement['name']); ?></h3>
                <p class="text-center text-muted"><?php echo clean($achievement['description']); ?></p>
                <p class="text-center">
                    <strong>Requirement:</strong> 
                    <?php 
                    switch ($achievement['condition_type']) {
                        case 'points_total':
                            echo 'Earn ' . number_format($achievement['condition_value']) . ' total points';
                            break;
                        case 'quiz_streak':
                            echo 'Answer ' . $achievement['condition_value'] . ' quizzes correctly';
                            break;
                        case 'challenges_completed':
                            echo 'Complete ' . $achievement['condition_value'] . ' challenges';
                            break;
                        case 'electricity_saves':
                            echo 'Save electricity ' . $achievement['condition_value'] . ' times';
                            break;
                        default:
                            echo 'Complete the requirement';
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
