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

    if ($profileAction === 'update_profile') {
        $newName = clean($_POST['name'] ?? '');
        $studentId = clean($_POST['student_id'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $address = clean($_POST['address'] ?? '');

        if (strlen($newName) < 2) {
            redirect('/profile.php', 'Name must be at least 2 characters long', 'danger');
        }

        if (strlen($newName) > 100) {
            redirect('/profile.php', 'Name must be less than 100 characters', 'danger');
        }

        if ($phone && !preg_match('/^[0-9\-\+\s\(\)]+$/', $phone)) {
            redirect('/profile.php', 'Invalid phone number format', 'danger');
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ?, student_id = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$newName, $studentId, $phone, $address, $user['id']]);

        $_SESSION['name'] = $newName;
        redirect('/profile.php', 'Profile updated successfully', 'success');
    }

    if ($profileAction === 'upload_picture') {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            redirect('/profile.php', 'Please select a valid image file', 'danger');
        }

        $file = $_FILES['profile_picture'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $fileMime = $file['type'] ?? '';

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (is_string($detectedMime) && $detectedMime !== '') {
                    $fileMime = $detectedMime;
                }
            }
        }

        if (!in_array($fileMime, $allowed, true)) {
            redirect('/profile.php', 'Only JPEG, PNG, GIF, WebP, HEIC, and HEIF images are allowed', 'danger');
        }

        if ($file['size'] > $maxSize) {
            redirect('/profile.php', 'File size must be less than 5MB', 'danger');
        }

        // Create uploads/profiles directory if it doesn't exist
        $uploadDir = __DIR__ . '/uploads/profiles';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Normalize all uploads to JPEG so iPhone HEIC files work everywhere.
        $newFilename = 'profile_' . $user['id'] . '_' . time() . '.jpg';
        $uploadPath = $uploadDir . '/' . $newFilename;
        $dbPath = '/uploads/profiles/' . $newFilename;

        $saved = false;

        if (class_exists('Imagick')) {
            try {
                $image = new Imagick($file['tmp_name']);
                $image->autoOrient();
                $image->stripImage();
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality(85);
                $saved = $image->writeImage($uploadPath);
                $image->clear();
                $image->destroy();
            } catch (Throwable $e) {
                $saved = false;
            }
        }

        if (!$saved) {
            $saved = move_uploaded_file($file['tmp_name'], $uploadPath);
        }

        if ($saved) {
            $stmt = $pdo->prepare("UPDATE users SET profile_picture_path = ? WHERE id = ?");
            $stmt->execute([$dbPath, $user['id']]);

            if ($user['profile_picture_path']) {
                $oldFile = __DIR__ . $user['profile_picture_path'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            redirect('/profile.php', 'Profile picture uploaded successfully', 'success');
        } else {
            redirect('/profile.php', 'Failed to upload picture', 'danger');
        }
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
            <div class="profile-avatar-container">
                <?php if ($user['profile_picture_path']): ?>
                    <img src="<?php echo appUrl(clean($user['profile_picture_path'])); ?>" alt="Profile Picture" class="profile-avatar-img">
                <?php else: ?>
                    <div class="profile-avatar" aria-hidden="true">
                        <?php echo strtoupper(substr(clean($user['name']), 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-top-text">
                <h1 class="profile-title"><?php echo clean($user['name']); ?></h1>
                <p class="profile-subtitle"><?php echo clean($user['email']); ?></p>
                <?php if ($user['student_id'] || $user['department']): ?>
                    <p class="profile-student-id">
                        <?php if ($user['student_id']): ?>
                            <span>ID: <?php echo clean($user['student_id']); ?></span>
                        <?php endif; ?>
                        <?php if ($user['student_id'] && $user['department']): ?>
                            <span class="profile-meta-separator">|</span>
                        <?php endif; ?>
                        <?php if ($user['department']): ?>
                            <span>Department: <?php echo clean($user['department']); ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <span class="role-pill"><?php echo clean(ucfirst($user['role'])); ?></span>
    </div>

    <div class="card mb-3">
        <h2 class="card-header">Upload Profile Picture</h2>
        <form method="POST" enctype="multipart/form-data" class="profile-form">
            <input type="hidden" name="profile_action" value="upload_picture">
            <div class="form-group">
                <label for="profile_picture">Choose Picture (JPEG, PNG, GIF, or WebP)</label>
                <input
                    type="file"
                    id="profile_picture"
                    name="profile_picture"
                    required
                    accept="image/jpeg,image/png,image/gif,image/webp,image/heic,image/heif"
                >
                <div class="profile-picture-preview-wrap">
                    <img id="profile-picture-preview" class="profile-picture-preview" alt="Selected profile picture preview" hidden>
                </div>
                <small class="text-muted profile-picture-note">Max file size: 5MB. iPhone photos (HEIC/HEIF) are supported and converted automatically.</small>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Upload Picture</button>
        </form>
    </div>

    <div class="card mb-3">
        <h2 class="card-header">Edit Profile</h2>
        <form method="POST" class="profile-form">
            <input type="hidden" name="profile_action" value="update_profile">
            <div class="form-group">
                <label for="name">Display Name <span class="text-danger">*</span></label>
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

            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input
                    type="text"
                    id="student_id"
                    name="student_id"
                    maxlength="50"
                    value="<?php echo clean($user['student_id'] ?? ''); ?>"
                    placeholder="Enter your student ID"
                >
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    maxlength="20"
                    value="<?php echo clean($user['phone'] ?? ''); ?>"
                    placeholder="Enter your phone number"
                >
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea
                    id="address"
                    name="address"
                    maxlength="500"
                    placeholder="Enter your address"
                    rows="3"
                ><?php echo clean($user['address'] ?? ''); ?></textarea>
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
