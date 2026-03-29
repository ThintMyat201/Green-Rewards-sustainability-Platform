<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php', 'Access denied', 'danger');
}

$error = '';
$success = '';

// Handle reward creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_reward'])) {
    $name = clean($_POST['name'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $type = clean($_POST['type'] ?? 'merchandise');
    $pointsCost = (int) ($_POST['points_cost'] ?? 0);
    $stockQty = (int) ($_POST['stock_qty'] ?? 0);
    
    if (empty($name) || $pointsCost < 1 || $stockQty < 0) {
        $error = 'Please fill in all fields correctly';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO rewards (name, description, type, points_cost, stock_qty) 
             VALUES (?, ?, ?, ?, ?)"
        );
        if ($stmt->execute([$name, $description, $type, $pointsCost, $stockQty])) {
            $success = 'Reward created successfully!';
        } else {
            $error = 'Failed to create reward';
        }
    }
}

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $rewardId = (int) $_POST['reward_id'];
    $newStock = (int) $_POST['new_stock'];
    
    $stmt = $pdo->prepare("UPDATE rewards SET stock_qty = ? WHERE id = ?");
    if ($stmt->execute([$newStock, $rewardId])) {
        $success = 'Stock updated successfully!';
    } else {
        $error = 'Failed to update stock';
    }
}

// Handle reward toggle
if (isset($_GET['toggle'])) {
    $rewardId = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE rewards SET is_active = NOT is_active WHERE id = ?");
    if ($stmt->execute([$rewardId])) {
        redirect('/admin/rewards.php', 'Reward status updated', 'success');
    }
}

// Handle redemption status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_redemption'])) {
    $redemptionId = (int) $_POST['redemption_id'];
    $newStatus = clean($_POST['new_status']);
    
    $stmt = $pdo->prepare("UPDATE redemptions SET status = ?, fulfilled_at = NOW() WHERE id = ?");
    if ($stmt->execute([$newStatus, $redemptionId])) {
        $success = 'Redemption status updated!';
    }
}

// Get all rewards
$stmt = $pdo->query(
    "SELECT r.*, 
            (SELECT COUNT(*) FROM redemptions WHERE reward_id = r.id) as redemption_count,
            (SELECT SUM(points_spent) FROM redemptions WHERE reward_id = r.id) as total_points_spent
     FROM rewards r
     ORDER BY r.created_at DESC"
);
$rewards = $stmt->fetchAll();

// Get pending redemptions
$stmt = $pdo->query(
    "SELECT red.*, r.name as reward_name, u.name as user_name, u.email 
     FROM redemptions red
     JOIN rewards r ON red.reward_id = r.id
     JOIN users u ON red.user_id = u.id
     WHERE red.status = 'pending'
     ORDER BY red.redeemed_at DESC"
);
$pendingRedemptions = $stmt->fetchAll();

$pageTitle = 'Reward Management';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">🎁 Reward Management</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (count($pendingRedemptions) > 0): ?>
    <div class="card mb-3">
        <h2 class="card-header">⏳ Pending Redemptions (<?php echo count($pendingRedemptions); ?>)</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Reward</th>
                    <th>Points</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingRedemptions as $redemption): ?>
                    <tr>
                        <td>
                            <?php echo clean($redemption['user_name']); ?><br>
                            <small><?php echo clean($redemption['email']); ?></small>
                        </td>
                        <td><?php echo clean($redemption['reward_name']); ?></td>
                        <td><?php echo number_format($redemption['points_spent']); ?></td>
                        <td><?php echo formatDate($redemption['redeemed_at']); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="redemption_id" value="<?php echo $redemption['id']; ?>">
                                <input type="hidden" name="new_status" value="fulfilled">
                                <button type="submit" name="update_redemption" class="btn btn-success btn-sm">Mark Fulfilled</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header">➕ Create New Reward</h2>
    <form method="POST">
        <div class="form-group">
            <label>Reward Name *</label>
            <input type="text" name="name" required placeholder="e.g., Campus Cafeteria Voucher">
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="2" placeholder="Brief description of the reward"></textarea>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Type *</label>
                <select name="type" required>
                    <option value="voucher">Voucher</option>
                    <option value="merchandise">Merchandise</option>
                    <option value="privilege">Privilege</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Points Cost *</label>
                <input type="number" name="points_cost" min="1" required placeholder="100">
            </div>
            
            <div class="form-group">
                <label>Stock Quantity *</label>
                <input type="number" name="stock_qty" min="0" required placeholder="50">
            </div>
        </div>
        
        <button type="submit" name="create_reward" class="btn btn-primary">Create Reward</button>
    </form>
</div>

<div class="card">
    <h2 class="card-header">All Rewards</h2>
    <?php if (count($rewards) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Cost</th>
                    <th>Stock</th>
                    <th>Redeemed</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rewards as $reward): ?>
                    <tr>
                        <td><strong><?php echo clean($reward['name']); ?></strong></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $reward['type'] === 'voucher' ? 'success' : 
                                    ($reward['type'] === 'merchandise' ? 'warning' : 'info'); 
                            ?>">
                                <?php echo ucfirst($reward['type']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($reward['points_cost']); ?> pts</td>
                        <td>
                            <strong class="<?php echo $reward['stock_qty'] < 5 ? 'text-danger' : ''; ?>">
                                <?php echo $reward['stock_qty']; ?>
                            </strong>
                        </td>
                        <td><?php echo $reward['redemption_count'] ?? 0; ?> times</td>
                        <td>
                            <?php if ($reward['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="document.getElementById('modal-<?php echo $reward['id']; ?>').style.display='block'" 
                                    class="btn btn-primary btn-sm">Edit Stock</button>
                            <a href="?toggle=<?php echo $reward['id']; ?>" class="btn btn-warning btn-sm">
                                <?php echo $reward['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            
                            <!-- Stock Edit Modal -->
                            <div id="modal-<?php echo $reward['id']; ?>" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; padding: 2rem;">
                                <div style="background: white; max-width: 400px; margin: 100px auto; padding: 2rem; border-radius: 10px;">
                                    <h3>Update Stock: <?php echo clean($reward['name']); ?></h3>
                                    <form method="POST" style="margin-top: 1rem;">
                                        <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                        <div class="form-group">
                                            <label>New Stock Quantity</label>
                                            <input type="number" name="new_stock" value="<?php echo $reward['stock_qty']; ?>" min="0" required>
                                        </div>
                                        <button type="submit" name="update_stock" class="btn btn-success btn-block">Update</button>
                                        <button type="button" onclick="document.getElementById('modal-<?php echo $reward['id']; ?>').style.display='none'" 
                                                class="btn btn-secondary btn-block mt-2">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No rewards created yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>