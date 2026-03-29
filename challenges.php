<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (!isLoggedIn()) {
    redirect('/login.php', 'Please login first', 'warning');
}

$user = getCurrentUser();
$userId = $user['id'];
$isStudent = hasRole('student');
$isStaff = hasRole('staff');

if (!$isStudent && !$isStaff) {
    redirect('/index.php', 'Access denied', 'danger');
}

$error = '';
$success = '';

// Handle challenge submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge_id'])) {
    $challengeId = (int) $_POST['challenge_id'];
    
    // Check if already submitted
    $stmt = $pdo->prepare(
        "SELECT id FROM challenge_submissions WHERE user_id = ? AND challenge_id = ?"
    );
    $stmt->execute([$userId, $challengeId]);
    
    if ($stmt->fetch()) {
        $error = 'You have already submitted this challenge';
    } else {
        // Handle file upload
        $imagePath = null;
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === 0) {
            $upload = uploadFile($_FILES['proof_image'], 'challenge_' . $userId);
            if ($upload['success']) {
                $imagePath = $upload['filename'];
            } else {
                $error = $upload['error'];
            }
        }
        
        if (!$error) {
            // Submit challenge
            $stmt = $pdo->prepare(
                "INSERT INTO challenge_submissions (challenge_id, user_id, proof_image_path, status) 
                 VALUES (?, ?, ?, 'pending')"
            );
            $stmt->execute([$challengeId, $userId, $imagePath]);
            $success = 'Challenge submitted! Waiting for moderator approval.';
        }
    }
}

// Get active challenges
$stmt = $pdo->prepare(
    "SELECT c.*, 
            (SELECT status FROM challenge_submissions WHERE challenge_id = c.id AND user_id = ?) as user_status
     FROM challenges c 
     WHERE c.is_active = 1 AND c.end_date >= CURDATE()
     ORDER BY c.created_at DESC"
);
$stmt->execute([$userId]);
$challenges = $stmt->fetchAll();

// Get user's submission history
$stmt = $pdo->prepare(
    "SELECT cs.*, c.title, c.points_reward 
     FROM challenge_submissions cs
     JOIN challenges c ON cs.challenge_id = c.id
     WHERE cs.user_id = ?
     ORDER BY cs.submitted_at DESC
     LIMIT 10"
);
$stmt->execute([$userId]);
$submissions = $stmt->fetchAll();

$pageTitle = 'Challenges';
include __DIR__ . '/includes/header.php';
?>

<h1 class="mb-3">🎯 Green Challenges</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<h2 class="mt-3 mb-2">Active Challenges</h2>
<div class="card-grid">
    <?php foreach ($challenges as $challenge): ?>
        <div class="card">
            <h3><?php echo clean($challenge['title']); ?></h3>
            <p class="badge badge-<?php echo $challenge['type'] === 'daily' ? 'info' : 'warning'; ?>">
                <?php echo ucfirst($challenge['type']); ?> Challenge
            </p>
            <p><?php echo clean($challenge['description']); ?></p>
            <p><strong>🎯 Reward:</strong> <?php echo $challenge['points_reward']; ?> points</p>
            <p><strong>📅 Ends:</strong> <?php echo formatDate($challenge['end_date']); ?></p>
            
            <?php if ($challenge['user_status']): ?>
                <?php if ($challenge['user_status'] === 'pending'): ?>
                    <span class="badge badge-warning">Pending Review</span>
                <?php elseif ($challenge['user_status'] === 'approved'): ?>
                    <span class="badge badge-success">✓ Completed</span>
                <?php elseif ($challenge['user_status'] === 'rejected'): ?>
                    <span class="badge badge-danger">Rejected</span>
                <?php endif; ?>
            <?php else: ?>
                <button onclick="document.getElementById('modal-<?php echo $challenge['id']; ?>').style.display='block'" 
                        class="btn btn-primary btn-block mt-2">
                    Submit Challenge
                </button>
                
                <!-- Modal -->
                <div id="modal-<?php echo $challenge['id']; ?>" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; padding: 2rem;">
                    <div style="background: white; max-width: 500px; margin: 50px auto; padding: 2rem; border-radius: 10px;">
                        <h3>Submit: <?php echo clean($challenge['title']); ?></h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                            
                            <div class="form-group">
                                <label>Upload Proof Image (Optional)</label>
                                <input type="file" name="proof_image" accept="image/*">
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-block">Submit</button>
                            <button type="button" onclick="document.getElementById('modal-<?php echo $challenge['id']; ?>').style.display='none'" 
                                    class="btn btn-secondary btn-block mt-2">Cancel</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <?php if (count($challenges) === 0): ?>
        <div class="card">
            <p class="text-muted">No active challenges right now. Check back soon!</p>
        </div>
    <?php endif; ?>
</div>

<?php if (count($submissions) > 0): ?>
    <div class="card mt-3">
        <h2 class="card-header">Your Submissions</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Challenge</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td><?php echo clean($sub['title']); ?></td>
                        <td><?php echo formatDate($sub['submitted_at']); ?></td>
                        <td>
                            <?php if ($sub['status'] === 'pending'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php elseif ($sub['status'] === 'approved'): ?>
                                <span class="badge badge-success">Approved</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sub['status'] === 'approved'): ?>
                                +<?php echo $sub['points_reward']; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>