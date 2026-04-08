<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please login first', 'warning');
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
        "SELECT c.*, cs.status as user_status
         FROM challenges c
         LEFT JOIN challenge_submissions cs ON cs.challenge_id = c.id AND cs.user_id = ?
         WHERE c.is_active = 1
             AND c.end_date >= CURDATE()
             AND (cs.status IS NULL OR cs.status = 'pending')
         ORDER BY c.created_at DESC"
);
$stmt->execute([$userId]);
$challenges = $stmt->fetchAll();

// Get user's submission history
$submissionsPerPage = 10;
$currentSubmissionsPage = isset($_GET['submissions_page']) ? (int) $_GET['submissions_page'] : 1;
$currentSubmissionsPage = max(1, $currentSubmissionsPage);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as total
     FROM (
         SELECT id
         FROM challenge_submissions
         WHERE user_id = ?
         ORDER BY submitted_at DESC
         LIMIT 20
     ) AS latest_submissions"
);
$stmt->execute([$userId]);
$totalSubmissions = (int) ($stmt->fetch()['total'] ?? 0);
$totalSubmissionPages = max(1, (int) ceil($totalSubmissions / $submissionsPerPage));
$currentSubmissionsPage = min($currentSubmissionsPage, $totalSubmissionPages);
$submissionsOffset = ($currentSubmissionsPage - 1) * $submissionsPerPage;

$stmt = $pdo->prepare(
    "SELECT cs.*, c.title, c.points_reward
     FROM (
         SELECT *
         FROM challenge_submissions
         WHERE user_id = ?
         ORDER BY submitted_at DESC
         LIMIT 20
     ) cs
     JOIN challenges c ON cs.challenge_id = c.id
     ORDER BY cs.submitted_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$userId, $submissionsPerPage, $submissionsOffset]);
$submissions = $stmt->fetchAll();

$submissionStartRow = $totalSubmissions > 0 ? $submissionsOffset + 1 : 0;
$submissionEndRow = min($submissionsOffset + $submissionsPerPage, $totalSubmissions);

$submissionBaseQuery = $_GET;
unset($submissionBaseQuery['submissions_page']);
$submissionBasePath = strtok($_SERVER['REQUEST_URI'], '?');

$buildSubmissionsPageUrl = static function (int $page) use ($submissionBaseQuery, $submissionBasePath): string {
    $query = $submissionBaseQuery;
    $query['submissions_page'] = $page;
    return $submissionBasePath . '?' . http_build_query($query);
};

$pageTitle = 'Challenges';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-bullseye"></i> Green Challenges</h1>

<?php if ($error): ?>
    <div class="alert alert-danger challenge-alert challenge-alert-danger" role="alert" aria-live="polite">
        <p class="challenge-alert-title">Submission Error</p>
        <p class="challenge-alert-message"><?php echo $error; ?></p>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success challenge-alert challenge-alert-success" role="status" aria-live="polite">
        <p class="challenge-alert-title">Success</p>
        <p class="challenge-alert-message"><?php echo $success; ?></p>
    </div>
<?php endif; ?>

<h2 class="mt-3 mb-2">Active Challenges</h2>
<div class="card-grid card-grid-four">
    <?php foreach ($challenges as $challenge): ?>
        <div class="card">
            <div class="card-content">
                <h3><?php echo clean($challenge['title']); ?></h3>
                <span class="badge badge-<?php echo $challenge['type'] === 'daily' ? 'info' : 'warning'; ?>">
                    <?php echo ucfirst($challenge['type']); ?> Challenge
                </span>
                <p class="challenge-description"><?php echo clean($challenge['description']); ?></p>
                <div class="challenge-info">
                    <p><strong><i class="fa-solid fa-bullseye"></i> Reward:</strong> <?php echo $challenge['points_reward']; ?> points</p>
                    <p><strong><i class="fa-solid fa-calendar-days"></i> Ends:</strong> <?php echo formatDate($challenge['end_date']); ?></p>
                </div>
            </div>
            
            <div class="card-footer" style="margin-top: 10px;">
                <?php if ($challenge['user_status']): ?>
                    <?php if ($challenge['user_status'] === 'pending'): ?>
                        <span class="badge badge-warning">Pending Review</span>
                    <?php elseif ($challenge['user_status'] === 'approved'): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Completed</span>
                    <?php elseif ($challenge['user_status'] === 'rejected'): ?>
                        <span class="badge badge-danger">Rejected</span>
                    <?php endif; ?>
                <?php else: ?>
                    <button onclick="document.getElementById('modal-<?php echo $challenge['id']; ?>').style.display='block'" 
                            class="btn btn-primary btn-block">
                        Submit Challenge
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Modal -->
        <div id="modal-<?php echo $challenge['id']; ?>" class="admin-modal-overlay" style="display:none;">
            <div class="admin-modal-content">
                <h3>Submit: <?php echo clean($challenge['title']); ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                    
                    <div class="form-group">
                        <label>Upload Proof Image (Optional)</label>
                        <input type="file" name="proof_image" accept="image/*">
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-block">Submit</button>
                    <button type="button" onclick="document.getElementById('modal-<?php echo $challenge['id']; ?>').style.display='none'" 
                            class="btn btn-secondary btn-block" style="margin-top: 0.75rem;">Cancel</button>
                </form>
            </div>
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
        <div class="table-responsive">
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

        <?php if ($totalSubmissionPages > 1): ?>
            <div class="pagination-wrap">
                <p class="pagination-summary">
                    Showing <?php echo $submissionStartRow; ?>-<?php echo $submissionEndRow; ?> of <?php echo $totalSubmissions; ?> submissions
                </p>
                <nav class="pagination" aria-label="Submission history pages">
                    <?php if ($currentSubmissionsPage > 1): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildSubmissionsPageUrl($currentSubmissionsPage - 1)); ?>">Previous</a>
                    <?php endif; ?>

                    <span class="pagination-page">Page <?php echo $currentSubmissionsPage; ?> of <?php echo $totalSubmissionPages; ?></span>

                    <?php if ($currentSubmissionsPage < $totalSubmissionPages): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildSubmissionsPageUrl($currentSubmissionsPage + 1)); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
