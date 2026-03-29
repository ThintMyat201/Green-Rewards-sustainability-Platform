<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('moderator')) {
    redirect('/login.php', 'Access denied', 'danger');
}

$user = getCurrentUser();
$userId = $user['id'];

$error = '';
$success = '';

// Handle challenge creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

// Handle challenge deactivation
if (isset($_GET['deactivate'])) {
    $challengeId = (int) $_GET['deactivate'];
    $stmt = $pdo->prepare("UPDATE challenges SET is_active = 0 WHERE id = ?");
    if ($stmt->execute([$challengeId])) {
        redirect('/mod/challenges.php', 'Challenge deactivated', 'success');
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

<h1 class="mb-3">🎯 Manage Challenges</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header">➕ Create New Challenge</h2>
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
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>End Date *</label>
                <input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Create Challenge</button>
    </form>
</div>

<div class="card">
    <h2 class="card-header">All Challenges</h2>
    <?php if (count($challenges) > 0): ?>
        <table class="table">
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
                            <?php if ($challenge['is_active']): ?>
                                <a href="?deactivate=<?php echo $challenge['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Deactivate this challenge?');">Deactivate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No challenges created yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>