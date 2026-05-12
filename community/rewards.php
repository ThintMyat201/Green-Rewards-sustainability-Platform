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

// Handle redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reward_id'])) {
    $rewardId = (int) $_POST['reward_id'];
    
    // Get reward details
    $stmt = $pdo->prepare(
        "SELECT * FROM rewards WHERE id = ? AND is_active = 1 AND stock_qty > 0"
    );
    $stmt->execute([$rewardId]);
    $reward = $stmt->fetch();
    
    if (!$reward) {
        $error = 'Reward not available';
    } elseif ($user['points_total'] < $reward['points_cost']) {
        $error = 'Not enough points';
    } else {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Create redemption
            $stmt = $pdo->prepare(
                "INSERT INTO redemptions (user_id, reward_id, points_spent, status) 
                 VALUES (?, ?, ?, 'pending')"
            );
            $stmt->execute([$userId, $rewardId, $reward['points_cost']]);
            
            // Deduct points
            $stmt = $pdo->prepare(
                "UPDATE users SET points_total = points_total - ? WHERE id = ?"
            );
            $stmt->execute([$reward['points_cost'], $userId]);
            
            // Decrease stock
            $stmt = $pdo->prepare(
                "UPDATE rewards SET stock_qty = stock_qty - 1 WHERE id = ?"
            );
            $stmt->execute([$rewardId]);
            
            // Log points deduction
            $stmt = $pdo->prepare(
                "INSERT INTO points_log (user_id, source, points_earned, description) 
                 VALUES (?, 'admin_adjust', ?, ?)"
            );
            $stmt->execute([$userId, -$reward['points_cost'], 'Redeemed: ' . $reward['name']]);
            
            $pdo->commit();
            $success = 'Reward redeemed successfully! Check with admin for collection.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Redemption failed. Please try again.';
        }
    }
}

// Get available rewards
$stmt = $pdo->query(
    "SELECT * FROM rewards WHERE is_active = 1 AND stock_qty > 0 ORDER BY points_cost ASC"
);
$rewards = $stmt->fetchAll();

// Get user's redemption history
$stmt = $pdo->prepare(
    "SELECT r.*, rw.name, rw.type 
     FROM redemptions r
     JOIN rewards rw ON r.reward_id = rw.id
     WHERE r.user_id = ?
     ORDER BY r.redeemed_at DESC
     LIMIT 10"
);
$stmt->execute([$userId]);
$redemptions = $stmt->fetchAll();

$pageTitle = 'Rewards Shop';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-gift"></i> Rewards Shop</h1>

<div class="alert alert-info">
    <strong>Your Points:</strong> <i class="fa-solid fa-star"></i> <?php echo number_format($user['points_total']); ?> points
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<h2 class="mt-3 mb-2">Available Rewards</h2>
<div class="card-grid card-grid-four">
    <?php foreach ($rewards as $reward): ?>
        <?php $canAfford = $user['points_total'] >= $reward['points_cost']; ?>
        <div class="card">
            <div class="card-content">
                <div class="reward-image-wrap">
                    <?php if (!empty($reward['image_path'])): ?>
                        <img
                            src="<?php echo appUrl(clean($reward['image_path'])); ?>"
                            alt="<?php echo clean($reward['name']); ?>"
                            class="reward-image"
                        >
                    <?php else: ?>
                        <div class="reward-image reward-image-placeholder">
                            <i class="fa-solid fa-gift"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="badge badge-<?php echo $reward['type'] === 'voucher' ? 'success' : ($reward['type'] === 'merchandise' ? 'warning' : 'info'); ?>">
                    <?php echo ucfirst($reward['type']); ?>
                </span>
                <h3><?php echo clean($reward['name']); ?></h3>
                <p class="reward-description"><?php echo clean($reward['description']); ?></p>
                <div class="reward-info">
                    <p><strong><i class="fa-solid fa-coins"></i> Cost:</strong> <?php echo number_format($reward['points_cost']); ?> points</p>
                    <p><strong><i class="fa-solid fa-box-open"></i> Stock:</strong> <?php echo $reward['stock_qty']; ?> left</p>
                </div>
            </div>
            
            <div class="card-footer">
                <?php if ($canAfford): ?>
                    <form method="POST" onsubmit="return confirm('Redeem this reward for <?php echo $reward['points_cost']; ?> points?');">
                        <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                        <button type="submit" class="btn btn-primary btn-block">Redeem Now</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary btn-block" disabled>
                        Need <?php echo number_format($reward['points_cost'] - $user['points_total']); ?> more points
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (count($rewards) === 0): ?>
        <div class="card">
            <p class="text-muted">No rewards available right now. Check back later!</p>
        </div>
    <?php endif; ?>
</div>

<?php if (count($redemptions) > 0): ?>
    <div class="card mt-3">
        <h2 class="card-header">Redemption History</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reward</th>
                        <th>Type</th>
                        <th>Points</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($redemptions as $redemption): ?>
                        <tr>
                            <td><?php echo clean($redemption['name']); ?></td>
                            <td><?php echo ucfirst($redemption['type']); ?></td>
                            <td>-<?php echo number_format($redemption['points_spent']); ?></td>
                            <td><?php echo formatDate($redemption['redeemed_at']); ?></td>
                            <td>
                                <?php if ($redemption['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php elseif ($redemption['status'] === 'fulfilled'): ?>
                                    <span class="badge badge-success">Fulfilled</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
