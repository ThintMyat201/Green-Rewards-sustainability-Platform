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

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id'])) {
    $submissionId = (int) $_POST['submission_id'];
    $action = clean($_POST['action'] ?? '');
    $reviewNote = clean($_POST['review_note'] ?? '');
    
    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        // Get submission details
        $stmt = $pdo->prepare(
            "SELECT cs.*, c.points_reward, cs.user_id 
             FROM challenge_submissions cs
             JOIN challenges c ON cs.challenge_id = c.id
             WHERE cs.id = ?"
        );
        $stmt->execute([$submissionId]);
        $submission = $stmt->fetch();
        
        if ($submission) {
            // Update submission status
            $stmt = $pdo->prepare(
                "UPDATE challenge_submissions 
                 SET status = ?, reviewed_by = ?, review_note = ?, reviewed_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([$status, $userId, $reviewNote, $submissionId]);
            
            // Award points if approved
            if ($status === 'approved') {
                addPoints(
                    $submission['user_id'],
                    $submission['points_reward'],
                    'challenge',
                    'Challenge approved: ' . $reviewNote
                );
                checkAchievements($submission['user_id']);
                $success = 'Challenge approved and points awarded!';
            } else {
                $success = 'Challenge rejected.';
            }
        }
    }
}

// Get pending submissions
$stmt = $pdo->query(
    "SELECT cs.*, c.title, c.points_reward, u.name as user_name, u.email 
     FROM challenge_submissions cs
     JOIN challenges c ON cs.challenge_id = c.id
     JOIN users u ON cs.user_id = u.id
     WHERE cs.status = 'pending'
     ORDER BY cs.submitted_at ASC"
);
$pendingSubmissions = $stmt->fetchAll();

// Get recent reviewed submissions
$stmt = $pdo->prepare(
    "SELECT cs.*, c.title, u.name as user_name 
     FROM challenge_submissions cs
     JOIN challenges c ON cs.challenge_id = c.id
     JOIN users u ON cs.user_id = u.id
     WHERE cs.reviewed_by = ?
     ORDER BY cs.reviewed_at DESC
     LIMIT 20"
);
$stmt->execute([$userId]);
$recentReviews = $stmt->fetchAll();

$pageTitle = 'Verify Submissions';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">✅ Verify Challenge Submissions</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>📋 Pending Submissions:</strong> <?php echo count($pendingSubmissions); ?>
</div>

<h2 class="mt-3 mb-2">Pending Submissions</h2>
<?php if (count($pendingSubmissions) > 0): ?>
    <div class="card-grid">
        <?php foreach ($pendingSubmissions as $submission): ?>
            <div class="card">
                <h3><?php echo clean($submission['title']); ?></h3>
                <p><strong>Submitted by:</strong> <?php echo clean($submission['user_name']); ?></p>
                <p><strong>Email:</strong> <?php echo clean($submission['email']); ?></p>
                <p><strong>Submitted:</strong> <?php echo formatDate($submission['submitted_at']); ?></p>
                <p><strong>Reward:</strong> <?php echo $submission['points_reward']; ?> points</p>
                
                <?php if ($submission['proof_image_path']): ?>
                    <div style="margin: 1rem 0;">
                        <strong>Proof Image:</strong><br>
                        <img src="/<?php echo clean($submission['proof_image_path']); ?>" 
                             style="max-width: 100%; height: auto; border-radius: 8px; margin-top: 0.5rem;"
                             alt="Proof">
                    </div>
                <?php else: ?>
                    <p class="text-muted">No image submitted</p>
                <?php endif; ?>
                
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                    
                    <div class="form-group">
                        <label>Review Note (Optional)</label>
                        <textarea name="review_note" rows="2" 
                                  placeholder="Add a note for the user..."></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <button type="submit" name="action" value="approve" 
                                class="btn btn-success">✅ Approve</button>
                        <button type="submit" name="action" value="reject" 
                                class="btn btn-danger">❌ Reject</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card">
        <p class="text-muted">No pending submissions. Great job!</p>
    </div>
<?php endif; ?>

<?php if (count($recentReviews) > 0): ?>
    <div class="card mt-3">
        <h2 class="card-header">Your Recent Reviews</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Challenge</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentReviews as $review): ?>
                    <tr>
                        <td><?php echo clean($review['title']); ?></td>
                        <td><?php echo clean($review['user_name']); ?></td>
                        <td>
                            <?php if ($review['status'] === 'approved'): ?>
                                <span class="badge badge-success">Approved</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($review['reviewed_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>