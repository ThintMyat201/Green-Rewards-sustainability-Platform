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
            
            // Delete proof image after decision
            if ($submission['proof_image_path']) {
                $imagePath = __DIR__ . '/../' . $submission['proof_image_path'];
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                }
            }
            
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
$recentReviewsPerPage = 10;
$currentRecentReviewPage = isset($_GET['review_page']) ? (int) $_GET['review_page'] : 1;
$currentRecentReviewPage = max(1, $currentRecentReviewPage);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as total
     FROM (
         SELECT cs.id
         FROM challenge_submissions cs
         WHERE cs.reviewed_by = ?
         ORDER BY cs.reviewed_at DESC
         LIMIT 20
     ) AS latest_reviews"
);
$stmt->execute([$userId]);
$totalRecentReviews = (int) ($stmt->fetch()['total'] ?? 0);
$totalRecentReviewPages = max(1, (int) ceil($totalRecentReviews / $recentReviewsPerPage));
$currentRecentReviewPage = min($currentRecentReviewPage, $totalRecentReviewPages);
$recentReviewsOffset = ($currentRecentReviewPage - 1) * $recentReviewsPerPage;

$stmt = $pdo->prepare(
    "SELECT cs.*, c.title, u.name as user_name
     FROM (
         SELECT *
         FROM challenge_submissions
         WHERE reviewed_by = ?
         ORDER BY reviewed_at DESC
         LIMIT 20
     ) cs
     JOIN challenges c ON cs.challenge_id = c.id
     JOIN users u ON cs.user_id = u.id
     ORDER BY cs.reviewed_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$userId, $recentReviewsPerPage, $recentReviewsOffset]);
$recentReviews = $stmt->fetchAll();

$recentReviewStartRow = $totalRecentReviews > 0 ? $recentReviewsOffset + 1 : 0;
$recentReviewEndRow = min($recentReviewsOffset + $recentReviewsPerPage, $totalRecentReviews);

$reviewBaseQuery = $_GET;
unset($reviewBaseQuery['review_page']);
$reviewBasePath = strtok($_SERVER['REQUEST_URI'], '?');

$buildReviewPageUrl = static function (int $page) use ($reviewBaseQuery, $reviewBasePath): string {
    $query = $reviewBaseQuery;
    $query['review_page'] = $page;
    return $reviewBasePath . '?' . http_build_query($query);
};

$pageTitle = 'Verify Submissions';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-circle-check"></i> Verify Challenge Submissions</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong><i class="fa-solid fa-clipboard-list"></i> Pending Submissions:</strong> <?php echo count($pendingSubmissions); ?>
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
                    <div class="verify-proof-wrap stack-block">
                        <strong>Proof Image:</strong><br>
                        <img src="<?php echo clean(appUrl($submission['proof_image_path'])); ?>" 
                             class="verify-proof-image"
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
                    
                        <div class="responsive-actions-grid">
                        <button type="submit" name="action" value="approve" 
                                class="btn btn-success"><i class="fa-solid fa-circle-check"></i> Approve</button>
                        <button type="submit" name="action" value="reject" 
                                class="btn btn-danger"><i class="fa-solid fa-circle-xmark"></i> Reject</button>
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
        <div class="table-responsive">
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

        <?php if ($totalRecentReviewPages > 1): ?>
            <div class="pagination-wrap">
                <p class="pagination-summary">
                    Showing <?php echo $recentReviewStartRow; ?>-<?php echo $recentReviewEndRow; ?> of <?php echo $totalRecentReviews; ?> reviews
                </p>
                <nav class="pagination" aria-label="Recent reviews pages">
                    <?php if ($currentRecentReviewPage > 1): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildReviewPageUrl($currentRecentReviewPage - 1)); ?>">Previous</a>
                    <?php endif; ?>

                    <span class="pagination-page">Page <?php echo $currentRecentReviewPage; ?> of <?php echo $totalRecentReviewPages; ?></span>

                    <?php if ($currentRecentReviewPage < $totalRecentReviewPages): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildReviewPageUrl($currentRecentReviewPage + 1)); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
