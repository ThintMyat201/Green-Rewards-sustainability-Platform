<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please login first', 'warning');
}

$user = getCurrentUser();
$canPost = hasRole('staff') || hasRole('moderator');
$canManage = hasRole('moderator') || hasRole('admin');

$error = '';
$success = '';

// Handle pin/unpin post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin_post'])) {
    if (!$canManage) {
        $error = 'You do not have permission to pin posts';
    } else {
        $tip_id = intval($_POST['tip_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT is_pinned FROM eco_tips WHERE id = ?");
        $stmt->execute([$tip_id]);
        $tip = $stmt->fetch();
        
        if ($tip) {
            $new_pinned = $tip['is_pinned'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE eco_tips SET is_pinned = ? WHERE id = ?");
            if ($stmt->execute([$new_pinned, $tip_id])) {
                $success = $new_pinned ? 'Post pinned successfully!' : 'Post unpinned successfully!';
            } else {
                $error = 'Failed to update pin status';
            }
        } else {
            $error = 'Post not found';
        }
    }
}

// Handle delete post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $tip_id = intval($_POST['tip_id'] ?? 0);
    
    // Get the post to check ownership
    $stmt = $pdo->prepare("SELECT posted_by FROM eco_tips WHERE id = ?");
    $stmt->execute([$tip_id]);
    $tip = $stmt->fetch();
    
    if (!$tip) {
        $error = 'Post not found';
    } elseif ($tip['posted_by'] !== $user['id'] && !$canManage) {
        // Only post owner, moderators, and admins can delete
        $error = 'You do not have permission to delete this post';
    } else {
        $stmt = $pdo->prepare("DELETE FROM eco_tips WHERE id = ?");
        if ($stmt->execute([$tip_id])) {
            $success = 'Post deleted successfully!';
        } else {
            $error = 'Failed to delete post';
        }
    }
}

// Handle tip posting (staff/moderator only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canPost && isset($_POST['content'])) {
    $content = clean($_POST['content'] ?? '');
    $category = clean($_POST['category'] ?? 'general');
    
    if (empty($content)) {
        $error = 'Please enter tip content';
    } elseif (strlen($content) < 10) {
        $error = 'Tip must be at least 10 characters';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO eco_tips (posted_by, content, category) VALUES (?, ?, ?)"
        );
        if ($stmt->execute([$user['id'], $content, $category])) {
            $success = 'Eco tip posted successfully!';
        } else {
            $error = 'Failed to post tip';
        }
    }
}

// Get all eco tips (pinned first, then recent)
$stmt = $pdo->query(
    "SELECT et.*, u.name as author_name, u.role as author_role 
     FROM eco_tips et
     JOIN users u ON et.posted_by = u.id
     ORDER BY et.is_pinned DESC, et.created_at DESC
     LIMIT 50"
);
$tips = $stmt->fetchAll();

$pageTitle = 'Eco Tips';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-lightbulb"></i> Eco Tips & Awareness</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($canPost): ?>
    <div class="card mb-3">
        <h2 class="card-header"><i class="fa-solid fa-pen-to-square"></i> Post a Tip</h2>
        <form method="POST">
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="general">General</option>
                    <option value="recycling">Recycling</option>
                    <option value="energy">Energy</option>
                    <option value="water">Water</option>
                    <option value="waste">Waste</option>
                    <option value="transportation">Transportation</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Tip Content *</label>
                <textarea name="content" rows="4" required 
                          placeholder="Share an eco-friendly tip with the community..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Post Tip</button>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <h2 class="card-header">Community Tips</h2>
    
    <?php if (count($tips) > 0): ?>
        <?php foreach ($tips as $tip): ?>
            <div class="card mt-2 <?php echo $tip['is_pinned'] ? 'content-panel-accent' : ''; ?>">
                <div class="responsive-row content-panel-top">
                    <div class="responsive-row-grow">
                        <?php if ($tip['is_pinned']): ?>
                            <span class="badge badge-warning"><i class="fa-solid fa-thumbtack"></i> Pinned</span>
                        <?php endif; ?>
                        <span class="badge badge-info">
                            <?php echo ucfirst($tip['category']); ?>
                        </span>
                        <p class="content-panel-copy content-panel-copy-lg">
                            <?php echo nl2br(clean($tip['content'])); ?>
                        </p>
                        <p class="text-muted content-panel-footer content-panel-footer-md">
                            Posted by <strong><?php echo clean($tip['author_name']); ?></strong> 
                            (<?php echo ucfirst($tip['author_role']); ?>) - 
                            <?php echo formatDate($tip['created_at']); ?>
                        </p>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="eco-tips-actions">
                        <?php if ($canManage): ?>
                            <form method="POST">
                                <input type="hidden" name="tip_id" value="<?php echo $tip['id']; ?>">
                                <button type="submit" name="pin_post" class="btn btn-sm <?php echo $tip['is_pinned'] ? 'btn-warning' : 'btn-outline-warning'; ?>"
                                    title="<?php echo $tip['is_pinned'] ? 'Unpin post' : 'Pin post'; ?>" 
                                    aria-label="<?php echo $tip['is_pinned'] ? 'Unpin post' : 'Pin post'; ?>">
                                    <i class="fa-solid fa-thumbtack"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($tip['posted_by'] === $user['id'] || $canManage): ?>
                            <form method="POST">
                                <input type="hidden" name="tip_id" value="<?php echo $tip['id']; ?>">
                                <button type="submit" name="delete_post" class="btn btn-sm btn-delete"
                                    onclick="return confirm('Delete this post? This action cannot be undone.');"
                                    title="Delete post" 
                                    aria-label="Delete post">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No eco tips posted yet. Be the first to share!</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
