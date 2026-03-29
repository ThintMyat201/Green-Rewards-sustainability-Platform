<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

$currentUser = getCurrentUser();
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Green Rewards'; ?> - Green Rewards Platform</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo">🌿 Green Rewards</a>
            
            <?php if (isLoggedIn()): ?>
                <ul class="nav-menu">
                    <?php if (hasRole('student')): ?>
                        <li><a href="/student/dashboard.php">Dashboard</a></li>
                        <li><a href="/electricity.php">Electricity</a></li>
                        <li><a href="/quiz.php">Quizzes</a></li>
                        <li><a href="/challenges.php">Challenges</a></li>
                        <li><a href="/leaderboard.php">Leaderboard</a></li>
                        <li><a href="/rewards.php">Rewards</a></li>
                        <li><a href="/achievements.php">Achievements</a></li>
                        <li><a href="/tree.php">My Tree</a></li>
                    <?php elseif (hasRole('staff')): ?>
                        <li><a href="/staff/dashboard.php">Dashboard</a></li>
                        <li><a href="/eco_tips.php">Eco Tips</a></li>
                        <li><a href="/challenges.php">Challenges</a></li>
                        <li><a href="/leaderboard.php">Leaderboard</a></li>
                        <li><a href="/points.php">My Points</a></li>
                    <?php elseif (hasRole('moderator')): ?>
                        <li><a href="/mod/verify.php">Verify Submissions</a></li>
                        <li><a href="/mod/challenges.php">Manage Challenges</a></li>
                        <li><a href="/mod/quiz.php">Manage Quizzes</a></li>
                        <li><a href="/eco_tips.php">Eco Tips</a></li>
                    <?php elseif (hasRole('admin')): ?>
                        <li><a href="/admin/dashboard.php">Dashboard</a></li>
                        <li><a href="/admin/users.php">Users</a></li>
                        <li><a href="/admin/rewards.php">Rewards</a></li>
                        <li><a href="/admin/reports.php">Reports</a></li>
                        <li><a href="/admin/benchmark.php">Settings</a></li>
                    <?php endif; ?>
                    
                    <li class="user-info">
                        <span class="points-badge">⭐ <?php echo number_format($currentUser['points_total']); ?> pts</span>
                        <span class="user-name"><?php echo clean($currentUser['name']); ?></span>
                        <span class="role-badge"><?php echo ucfirst($currentUser['role']); ?></span>
                    </li>
                    <li><a href="/logout.php" class="btn-logout">Logout</a></li>
                </ul>
            <?php else: ?>
                <ul class="nav-menu">
                    <li><a href="/login.php">Login</a></li>
                    <li><a href="/register.php" class="btn-primary">Register</a></li>
                </ul>
            <?php endif; ?>
        </div>
    </nav>
    
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <div class="container">
                <?php echo clean($flash['message']); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <main class="main-content">
        <div class="container">