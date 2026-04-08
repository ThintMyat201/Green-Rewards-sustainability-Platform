<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('moderator')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$userId = $user['id'];

$error = '';
$success = '';
$challengeTypeOptions = ['daily', 'monthly'];

// Handle challenge creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_challenge'])) {
    $title = clean($_POST['title'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $type = clean($_POST['type'] ?? 'daily');
    $pointsReward = (int) ($_POST['points_reward'] ?? 50);
    $startDate = clean($_POST['start_date'] ?? date('Y-m-d'));
    $endDate = clean($_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days')));
    
    if (empty($title) || empty($description)) {
        $error = 'Please fill in all fields';
    } elseif ($pointsReward < 1) {
        $error = 'Points reward must be positive';
    } elseif (!in_array($type, $challengeTypeOptions, true)) {
        $error = 'Invalid challenge type selected';
    } elseif ($startDate > $endDate) {
        $error = 'End date must be on or after start date';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO challenges (title, description, type, points_reward, start_date, end_date, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt->execute([$title, $description, $type, $pointsReward, $startDate, $endDate, $userId])) {
            $success = 'Challenge created successfully!';
        } else {
            $error = 'Failed to create challenge';
        }
    }
}

// Handle challenge updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_challenge'])) {
    $challengeId = (int) ($_POST['challenge_id'] ?? 0);
    $title = clean($_POST['title'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $type = clean($_POST['type'] ?? 'daily');
    $pointsReward = (int) ($_POST['points_reward'] ?? 0);
    $startDate = clean($_POST['start_date'] ?? '');
    $endDate = clean($_POST['end_date'] ?? '');

    if ($challengeId <= 0) {
        $error = 'Invalid challenge selected';
    } elseif (empty($title) || empty($description) || empty($startDate) || empty($endDate)) {
        $error = 'Please fill in all required fields';
    } elseif ($pointsReward < 1) {
        $error = 'Points reward must be positive';
    } elseif (!in_array($type, $challengeTypeOptions, true)) {
        $error = 'Invalid challenge type selected';
    } elseif ($startDate > $endDate) {
        $error = 'End date must be on or after start date';
    } else {
        $stmt = $pdo->prepare(
            "UPDATE challenges
             SET title = ?, description = ?, type = ?, points_reward = ?, start_date = ?, end_date = ?
             WHERE id = ?"
        );
        if ($stmt->execute([$title, $description, $type, $pointsReward, $startDate, $endDate, $challengeId])) {
            $success = 'Challenge updated successfully!';
        } else {
            $error = 'Failed to update challenge';
        }
    }
}

// Handle challenge deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_challenge'])) {
    $challengeId = (int) ($_POST['challenge_id'] ?? 0);

    if ($challengeId <= 0) {
        $error = 'Invalid challenge selected';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM challenge_submissions WHERE challenge_id = ?");
        $stmt->execute([$challengeId]);
        $submissionCount = (int) ($stmt->fetch()['cnt'] ?? 0);

        if ($submissionCount > 0) {
            $error = 'Cannot delete challenge with submissions';
        } else {
            $stmt = $pdo->prepare("DELETE FROM challenges WHERE id = ?");
            if ($stmt->execute([$challengeId])) {
                $success = 'Challenge deleted successfully!';
            } else {
                $error = 'Failed to delete challenge';
            }
        }
    }
}

// Handle challenge status toggle
if (isset($_GET['toggle'])) {
    $challengeId = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE challenges SET is_active = NOT is_active WHERE id = ?");
    if ($stmt->execute([$challengeId])) {
        redirect('/mod/challenges.php', 'Challenge status updated', 'success');
    }
}

// Get all challenges
$stmt = $pdo->prepare(
    "SELECT c.*, u.name as creator_name,
            (SELECT COUNT(*) FROM challenge_submissions WHERE challenge_id = c.id) as submission_count,
            (SELECT COUNT(*) FROM challenge_submissions WHERE challenge_id = c.id AND status = 'approved') as approved_count
     FROM challenges c
     JOIN users u ON c.created_by = u.id
     ORDER BY c.created_at DESC"
);
$stmt->execute();
$challenges = $stmt->fetchAll();

$pageTitle = 'Manage Challenges';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-bullseye"></i> Manage Challenges</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header"><i class="fa-solid fa-plus"></i> Create New Challenge</h2>
    <form method="POST">
        <div class="form-group">
            <label>Challenge Title *</label>
            <input type="text" name="title" required 
                   placeholder="e.g., Zero Waste Lunch Week">
        </div>
        
        <div class="form-group">
            <label>Description *</label>
            <textarea name="description" rows="3" required 
                      placeholder="Describe the challenge and what participants need to do..."></textarea>
        </div>
        
        <div class="form-group">
            <label>Challenge Type *</label>
            <select name="type" required>
                <option value="daily">Daily Challenge</option>
                <option value="monthly">Monthly Challenge</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Points Reward *</label>
            <input type="number" name="points_reward" value="50" min="1" required>
        </div>
        
        <div class="admin-form-grid admin-form-grid-2">
            <div class="form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>End Date *</label>
                <input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
            </div>
        </div>
        
        <button type="submit" name="create_challenge" class="btn btn-primary">Create Challenge</button>
    </form>
</div>

<div class="card">
    <h2 class="card-header">All Challenges</h2>
    <?php if (count($challenges) > 0): ?>
        <div class="table-responsive">
            <table class="table rewards-table-main">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Reward</th>
                        <th>Period</th>
                        <th>Submissions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($challenges as $challenge): ?>
                        <tr>
                            <td><strong><?php echo clean($challenge['title']); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $challenge['type'] === 'daily' ? 'info' : 'warning'; ?>">
                                    <?php echo ucfirst($challenge['type']); ?>
                                </span>
                            </td>
                            <td><?php echo $challenge['points_reward']; ?> pts</td>
                            <td>
                                <?php echo date('M d', strtotime($challenge['start_date'])); ?> - 
                                <?php echo date('M d, Y', strtotime($challenge['end_date'])); ?>
                            </td>
                            <td>
                                <?php echo $challenge['approved_count']; ?> / <?php echo $challenge['submission_count']; ?>
                            </td>
                            <td>
                                <?php if ($challenge['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="reward-actions challenge-actions">
                                    <button onclick="document.getElementById('modal-<?php echo $challenge['id']; ?>').style.display='block'"
                                            class="btn btn-edit btn-sm">Edit</button>
                                    <a href="?toggle=<?php echo $challenge['id']; ?>" class="btn <?php echo $challenge['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?> btn-sm">
                                        <?php echo $challenge['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <form method="POST" class="admin-inline-form">
                                        <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                                        <button
                                            type="submit"
                                            name="delete_challenge"
                                            class="btn btn-delete btn-sm action-delete-btn"
                                            onclick="return confirm('Delete this challenge? This action cannot be undone.');"
                                            title="Delete challenge"
                                            aria-label="Delete challenge"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>

                                <div id="modal-<?php echo $challenge['id']; ?>" class="admin-modal-overlay" style="display:none;">
                                    <div class="admin-modal-content">
                                        <h3>Edit Challenge: <?php echo clean($challenge['title']); ?></h3>
                                        <form method="POST" style="margin-top: 1rem;">
                                            <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                                            <div class="form-group">
                                                <label>Challenge Title</label>
                                                <input type="text" name="title" value="<?php echo clean($challenge['title']); ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>Description</label>
                                                <textarea name="description" rows="3" required><?php echo clean($challenge['description']); ?></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label>Challenge Type</label>
                                                <select name="type" required>
                                                    <option value="daily" <?php echo $challenge['type'] === 'daily' ? 'selected' : ''; ?>>Daily Challenge</option>
                                                    <option value="monthly" <?php echo $challenge['type'] === 'monthly' ? 'selected' : ''; ?>>Monthly Challenge</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label>Points Reward</label>
                                                <input type="number" name="points_reward" min="1" value="<?php echo (int) $challenge['points_reward']; ?>" required>
                                            </div>

                                            <div class="admin-form-grid admin-form-grid-2">
                                                <div class="form-group">
                                                    <label>Start Date</label>
                                                    <input type="date" name="start_date" value="<?php echo clean($challenge['start_date']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>End Date</label>
                                                    <input type="date" name="end_date" value="<?php echo clean($challenge['end_date']); ?>" required>
                                                </div>
                                            </div>

                                            <button type="submit" name="update_challenge" class="btn btn-success btn-block">Save Changes</button>
                                            <button type="button" onclick="document.getElementById('modal-<?php echo $challenge['id']; ?>').style.display='none'"
                                                    class="btn btn-secondary btn-block mt-2">Cancel</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No challenges created yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
