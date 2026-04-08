<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please log in to continue', 'warning');
}

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    redirect('/auth/login.php', 'Session expired. Please log in again.', 'warning');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileAction = clean($_POST['profile_action'] ?? '');

    if ($profileAction === 'update_name') {
        $newName = clean($_POST['name'] ?? '');

        if (strlen($newName) < 2) {
            redirect('/profile.php', 'Name must be at least 2 characters long', 'danger');
        }

        if (strlen($newName) > 100) {
            redirect('/profile.php', 'Name must be less than 100 characters', 'danger');
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $user['id']]);

        $_SESSION['name'] = $newName;
        redirect('/profile.php', 'Profile updated successfully', 'success');
    }

    if ($profileAction === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            redirect('/profile.php', 'Please fill in all password fields', 'danger');
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            redirect('/profile.php', 'Current password is incorrect', 'danger');
        }

        if (strlen($newPassword) < 6) {
            redirect('/profile.php', 'New password must be at least 6 characters', 'danger');
        }

        if ($newPassword !== $confirmPassword) {
            redirect('/profile.php', 'New password and confirmation do not match', 'danger');
        }

        if (password_verify($newPassword, $user['password_hash'])) {
            redirect('/profile.php', 'New password must be different from current password', 'danger');
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $user['id']]);

        redirect('/profile.php', 'Password changed successfully', 'success');
    }

    redirect('/profile.php', 'Invalid profile action', 'danger');
}

switch ($user['role']) {
    case 'admin':
        $dashboardUrl = '/admin/dashboard.php';
        break;
    case 'moderator':
        $dashboardUrl = '/mod/verify.php';
        break;
    case 'staff':
        $dashboardUrl = '/staff/dashboard.php';
        break;
    default:
        $dashboardUrl = '/student/dashboard.php';
        break;
}

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<section class="profile-page">
    <div class="card profile-top-card">
        <div class="profile-top-main">
            <div class="profile-avatar" aria-hidden="true">
                <?php echo strtoupper(substr(clean($user['name']), 0, 1)); ?>
            </div>
            <div class="profile-top-text">
                <h1 class="profile-title"><?php echo clean($user['name']); ?></h1>
                <p class="profile-subtitle"><?php echo clean($user['email']); ?></p>
            </div>
        </div>
        <span class="role-pill"><?php echo clean(ucfirst($user['role'])); ?></span>
    </div>

    <div class="card mb-3">
        <h2 class="card-header">Edit Name</h2>
        <form method="POST" class="profile-form">
            <input type="hidden" name="profile_action" value="update_name">
            <div class="form-group">
                <label for="name">Display Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    minlength="2"
                    maxlength="100"
                    value="<?php echo clean($user['name']); ?>"
                    placeholder="Enter your full name"
                >
                <small class="text-muted">This name is shown across your profile and leaderboard.</small>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
        </form>
    </div>

    <div class="card mb-3">
        <h2 class="card-header">Change Password</h2>
        <form method="POST" class="profile-form">
            <input type="hidden" name="profile_action" value="change_password">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your current password"
                >
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    required
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Enter a new password"
                >
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    required
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Re-enter the new password"
                >
            </div>
            <button type="submit" class="btn btn-warning btn-block">Update Password</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-header">Quick Actions</h2>
        <div class="profile-actions profile-actions-mobile">
            <a class="btn btn-secondary" href="<?php echo appUrl($dashboardUrl); ?>">Back to Dashboard</a>
            <a class="btn btn-danger" href="<?php echo appUrl('/auth/logout.php'); ?>">Log Out</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
