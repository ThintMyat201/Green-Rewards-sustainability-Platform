<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

$currentUser = getCurrentUser();
$flash = getFlashMessage();
$showPointsBadge = $currentUser && roleEarnsPoints($currentUser['role'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Green Rewards'; ?> - Green Rewards Platform</title>
    <link rel="stylesheet" href="<?php echo appUrl('/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="<?php echo appUrl('/index.php'); ?>" class="logo"><i class="fa-solid fa-seedling"></i> Green Rewards</a>

            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div id="primary-navigation" class="nav-panel">
                <?php if (isLoggedIn()): ?>
                    <ul class="nav-menu nav-links">
                        <?php if (hasRole('student')): ?>
                            <li><a href="<?php echo appUrl('/student/dashboard.php'); ?>">Dashboard</a></li>
                            
                            <li><a href="<?php echo appUrl('/student/achievements.php'); ?>">Achievements</a></li>
                            
                            <li><a href="<?php echo appUrl('/community/leaderboard.php'); ?>">Leaderboard</a></li>
                            
                            <li><a href="<?php echo appUrl('/community/rewards.php'); ?>">Rewards</a></li>
                            <li><a href="<?php echo appUrl('/student/tree.php'); ?>">My Tree</a></li>
                            <li class="nav-more-item">
                                <details class="nav-dropdown">
                                    <summary>Activity</summary>
                                    <div class="nav-dropdown-menu">
                                        <a href="<?php echo appUrl('/student/electricity.php'); ?>">Electricity</a>
                                        <a href="<?php echo appUrl('/community/quiz.php'); ?>">Quizzes</a>
                                        <a href="<?php echo appUrl('/community/challenges.php'); ?>">Challenges</a>

                                    </div>
                                </details>
                            </li>
                        <?php elseif (hasRole('staff')): ?>
                            <li><a href="<?php echo appUrl('/staff/dashboard.php'); ?>">Dashboard</a></li>
                            <li><a href="<?php echo appUrl('/community/eco_tips.php'); ?>">Eco Tips</a></li>
                            <li><a href="<?php echo appUrl('/community/challenges.php'); ?>">Challenges</a></li>
                            <li><a href="<?php echo appUrl('/community/rewards.php'); ?>">Rewards</a></li>
                            <li><a href="<?php echo appUrl('/community/leaderboard.php'); ?>">Leaderboard</a></li>
                        <?php elseif (hasRole('moderator')): ?>
                            <li><a href="<?php echo appUrl('/mod/verify.php'); ?>">Verify Submissions</a></li>
                            <li><a href="<?php echo appUrl('/mod/challenges.php'); ?>">Manage Challenges</a></li>
                            <li><a href="<?php echo appUrl('/mod/quiz.php'); ?>">Manage Quizzes</a></li>
                            <li><a href="<?php echo appUrl('/community/eco_tips.php'); ?>">Eco Tips</a></li>
                        <?php elseif (hasRole('admin')): ?>
                            <li><a href="<?php echo appUrl('/admin/dashboard.php'); ?>">Dashboard</a></li>
                            <li><a href="<?php echo appUrl('/admin/users.php'); ?>">Users</a></li>
                            <li><a href="<?php echo appUrl('/admin/departments.php'); ?>">Departments</a></li>
                            <li><a href="<?php echo appUrl('/admin/rewards.php'); ?>">Rewards</a></li>
                            <li><a href="<?php echo appUrl('/admin/reports.php'); ?>">Reports</a></li>
                            <li><a href="<?php echo appUrl('/admin/benchmark.php'); ?>">Settings</a></li>
                        <?php endif; ?>
                    </ul>

                    <div class="nav-user-area">
                        <?php if ($showPointsBadge): ?>
                            <div class="points-chip">
                                <a href="<?php echo appUrl('/community/points.php'); ?>" class="points-badge"><i class="fa-solid fa-star"></i> <?php echo number_format($currentUser['points_total']); ?> pts</a>
                            </div>
                        <?php endif; ?>
                        <a href="<?php echo appUrl('/profile.php'); ?>" class="profile-summary" aria-label="Go to profile">
                            <span class="user-name"><?php echo clean($currentUser['name'] ?? 'Profile'); ?></span>
                            <span class="role-badge"><?php echo ucfirst(clean($currentUser['role'] ?? 'user')); ?></span>
                        </a>
                        <div class="profile-action">
                            <a href="<?php echo appUrl('/profile.php'); ?>" class="profile-icon-link" aria-label="Go to profile" title="Profile">
                                <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <ul class="nav-menu nav-links">
                        <li><a href="<?php echo appUrl('/auth/login.php'); ?>">Login</a></li>
                    </ul>
                    <div class="nav-user-area nav-guest-area">
                        <a href="<?php echo appUrl('/auth/register.php'); ?>" class="btn btn-primary btn-sm">Register</a>
                    </div>
                <?php endif; ?>
            </div>
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
