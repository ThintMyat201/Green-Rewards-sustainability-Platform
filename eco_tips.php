<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (!isLoggedIn()) {
    redirect('/login.php', 'Please login first', 'warning');
}

$user = getCurrentUser();
$canPost = hasRole('staff') || hasRole('moderator');

$error = '';
$success = '';

// Handle tip posting (staff/moderator only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canPost) {
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
include __DIR__ . '/includes/header.php';
?>

<h1 class="mb-3">💡 Eco Tips & Awareness</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($canPost): ?>
    <div class="card mb-3">
        <h2 class="card-header">✍️ Post a Tip</h2>
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
            <div class="card mt-2" style="<?php echo $tip['is_pinned'] ? 'border-left: 4px solid var(--warning); background: #fffbeb;' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <?php if ($tip['is_pinned']): ?>
                            <span class="badge badge-warning">📌 Pinned</span>
                        <?php endif; ?>
                        <span class="badge badge-info">
                            <?php echo ucfirst($tip['category']); ?>
                        </span>
                        <p style="font-size: 1.1rem; margin: 0.8rem 0;">
                            <?php echo nl2br(clean($tip['content'])); ?>
                        </p>
                        <p class="text-muted" style="font-size: 0.9rem; margin: 0;">
                            Posted by <strong><?php echo clean($tip['author_name']); ?></strong> 
                            (<?php echo ucfirst($tip['author_role']); ?>) - 
                            <?php echo formatDate($tip['created_at']); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No eco tips posted yet. Be the first to share!</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>