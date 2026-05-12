<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$error = '';
$success = '';
$rewardsPerPage = 10;
$currentRewardsPage = isset($_GET['reward_page']) ? (int) $_GET['reward_page'] : 1;
$currentRewardsPage = max(1, $currentRewardsPage);
$rewardTypeOptions = ['voucher', 'merchandise', 'privilege'];

// Handle reward creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_reward'])) {
    $name = clean($_POST['name'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $type = clean($_POST['type'] ?? 'merchandise');
    $pointsCost = (int) ($_POST['points_cost'] ?? 0);
    $stockQty = (int) ($_POST['stock_qty'] ?? 0);
    $imagePath = null;
    
    if (empty($name) || $pointsCost < 1 || $stockQty < 0) {
        $error = 'Please fill in all fields correctly';
    } else {
        if (!empty($_FILES['image']['name'])) {
            $uploadResult = uploadFile($_FILES['image'], 'reward');
            if (!$uploadResult['success']) {
                $error = $uploadResult['error'];
            } else {
                $imagePath = $uploadResult['filename'];
            }
        }

        if (!$error) {
        $stmt = $pdo->prepare(
            "INSERT INTO rewards (name, description, type, points_cost, stock_qty, image_path) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
            if ($stmt->execute([$name, $description, $type, $pointsCost, $stockQty, $imagePath])) {
                $success = 'Reward created successfully!';
            } else {
                $error = 'Failed to create reward';
            }
        }
    }
}

// Handle reward update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reward'])) {
    $rewardId = (int) ($_POST['reward_id'] ?? 0);
    $updatedName = clean($_POST['name'] ?? '');
    $updatedType = clean($_POST['type'] ?? '');
    $updatedPointsCost = (int) ($_POST['points_cost'] ?? 0);
    $updatedStockQty = (int) ($_POST['stock_qty'] ?? 0);

    if ($rewardId <= 0) {
        $error = 'Invalid reward selected';
    } elseif (empty($updatedName) || $updatedPointsCost < 1 || $updatedStockQty < 0) {
        $error = 'Please provide valid reward details';
    } elseif (!in_array($updatedType, $rewardTypeOptions, true)) {
        $error = 'Invalid reward type selected';
    } else {
        $stmt = $pdo->prepare("SELECT image_path FROM rewards WHERE id = ?");
        $stmt->execute([$rewardId]);
        $currentReward = $stmt->fetch();

        if (!$currentReward) {
            $error = 'Reward not found';
        } else {
            $updatedImagePath = $currentReward['image_path'];

            if (!empty($_FILES['image']['name'])) {
                $uploadResult = uploadFile($_FILES['image'], 'reward');
                if (!$uploadResult['success']) {
                    $error = $uploadResult['error'];
                } else {
                    $updatedImagePath = $uploadResult['filename'];

                    if (!empty($currentReward['image_path'])) {
                        $oldImageFile = __DIR__ . '/../' . ltrim($currentReward['image_path'], '/');
                        if (file_exists($oldImageFile)) {
                            unlink($oldImageFile);
                        }
                    }
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare(
                    "UPDATE rewards
                     SET name = ?, type = ?, points_cost = ?, stock_qty = ?, image_path = ?
                     WHERE id = ?"
                );

                if ($stmt->execute([$updatedName, $updatedType, $updatedPointsCost, $updatedStockQty, $updatedImagePath, $rewardId])) {
                    $success = 'Reward updated successfully!';
                } else {
                    $error = 'Failed to update reward';
                }
            }
        }
    }
}

// Handle reward deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reward'])) {
    $rewardId = (int) ($_POST['reward_id'] ?? 0);

    if ($rewardId <= 0) {
        $error = 'Invalid reward selected';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM redemptions WHERE reward_id = ?");
        $stmt->execute([$rewardId]);
        $redemptionCount = (int) ($stmt->fetch()['cnt'] ?? 0);

        if ($redemptionCount > 0) {
            $error = 'Cannot delete reward with redemption history';
        } else {
            $stmt = $pdo->prepare("DELETE FROM rewards WHERE id = ?");
            if ($stmt->execute([$rewardId])) {
                $success = 'Reward deleted successfully!';
            } else {
                $error = 'Failed to delete reward';
            }
        }
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
$stmt = $pdo->query("SELECT COUNT(*) as total FROM rewards");
$totalRewards = (int) ($stmt->fetch()['total'] ?? 0);
$totalRewardPages = max(1, (int) ceil($totalRewards / $rewardsPerPage));
$currentRewardsPage = min($currentRewardsPage, $totalRewardPages);
$rewardsOffset = ($currentRewardsPage - 1) * $rewardsPerPage;

$stmt = $pdo->prepare(
    "SELECT r.*, 
            (SELECT COUNT(*) FROM redemptions WHERE reward_id = r.id) as redemption_count,
            (SELECT SUM(points_spent) FROM redemptions WHERE reward_id = r.id) as total_points_spent
     FROM rewards r
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$rewardsPerPage, $rewardsOffset]);
$rewards = $stmt->fetchAll();

$rewardStartRow = $totalRewards > 0 ? $rewardsOffset + 1 : 0;
$rewardEndRow = min($rewardsOffset + $rewardsPerPage, $totalRewards);

$rewardBaseQuery = $_GET;
unset($rewardBaseQuery['reward_page']);
$rewardBasePath = strtok($_SERVER['REQUEST_URI'], '?');

$buildRewardPageUrl = static function (int $page) use ($rewardBaseQuery, $rewardBasePath): string {
    $query = $rewardBaseQuery;
    $query['reward_page'] = $page;
    return $rewardBasePath . '?' . http_build_query($query);
};

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

<h1 class="mb-3 rewards-page-title"><i class="fa-solid fa-gift"></i> Reward Management</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (count($pendingRedemptions) > 0): ?>
    <div class="card mb-3">
        <h2 class="card-header"><i class="fa-solid fa-hourglass-half"></i> Pending Redemptions (<?php echo count($pendingRedemptions); ?>)</h2>
        <div class="table-responsive">
            <table class="table rewards-table-pending">
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
                                <form method="POST" class="admin-inline-form">
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
    </div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header"><i class="fa-solid fa-plus"></i> Create New Reward</h2>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Reward Name *</label>
            <input type="text" name="name" required placeholder="e.g., Campus Cafeteria Voucher">
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="2" placeholder="Brief description of the reward"></textarea>
        </div>
        
        <div class="admin-form-grid admin-form-grid-3">
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

        <div class="form-group">
            <label>Reward Image</label>
            <input type="file" id="reward_image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <div class="reward-upload-preview-wrap">
                <img id="reward-image-preview" class="reward-upload-preview" alt="Selected reward image preview" hidden>
            </div>
            <small class="text-muted reward-upload-note">Optional. Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB. Recommended size: 400x400px.</small>
        </div>
        
        <button type="submit" name="create_reward" class="btn btn-primary">Create Reward</button>
    </form>
</div>

<div class="card">
    <h2 class="card-header">All Rewards (<?php echo $totalRewards; ?>)</h2>
    <?php if (count($rewards) > 0): ?>
        <div class="table-responsive">
            <table class="table rewards-table-main">
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
                                <div class="reward-actions">
                                    <button onclick="document.getElementById('modal-<?php echo $reward['id']; ?>').style.display='block'" 
                                            class="btn btn-edit btn-sm">Edit</button>
                                    <a href="?toggle=<?php echo $reward['id']; ?>" class="btn <?php echo $reward['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?> btn-sm">
                                        <?php echo $reward['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <form method="POST" class="admin-inline-form">
                                        <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                        <button
                                            type="submit"
                                            name="delete_reward"
                                            class="btn btn-delete btn-sm action-delete-btn"
                                            onclick="return confirm('Delete this reward? This action cannot be undone.');"
                                            title="Delete reward"
                                            aria-label="Delete reward"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Stock Edit Modal -->
                                <div id="modal-<?php echo $reward['id']; ?>" class="admin-modal-overlay" style="display:none;">
                                    <div class="admin-modal-content admin-modal-content-sm">
                                        <h3>Edit Reward: <?php echo clean($reward['name']); ?></h3>
                                        <form method="POST" enctype="multipart/form-data" style="margin-top: 1rem;">
                                            <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                            <div class="form-group">
                                                <label>Reward Name</label>
                                                <input type="text" name="name" value="<?php echo clean($reward['name']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Type</label>
                                                <select name="type" required>
                                                    <option value="voucher" <?php echo $reward['type'] === 'voucher' ? 'selected' : ''; ?>>Voucher</option>
                                                    <option value="merchandise" <?php echo $reward['type'] === 'merchandise' ? 'selected' : ''; ?>>Merchandise</option>
                                                    <option value="privilege" <?php echo $reward['type'] === 'privilege' ? 'selected' : ''; ?>>Privilege</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Points Cost</label>
                                                <input type="number" name="points_cost" value="<?php echo (int) $reward['points_cost']; ?>" min="1" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Stock Quantity</label>
                                                <input type="number" name="stock_qty" value="<?php echo (int) $reward['stock_qty']; ?>" min="0" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Reward Image</label>
                                                <input
                                                    type="file"
                                                    id="reward_edit_image_<?php echo $reward['id']; ?>"
                                                    name="image"
                                                    accept="image/jpeg,image/png,image/gif,image/webp"
                                                    data-preview-target="reward-edit-preview-<?php echo $reward['id']; ?>"
                                                >
                                                <div class="reward-upload-preview-wrap">
                                                    <?php if (!empty($reward['image_path'])): ?>
                                                        <img
                                                            id="reward-edit-preview-<?php echo $reward['id']; ?>"
                                                            src="<?php echo appUrl(clean($reward['image_path'])); ?>"
                                                            alt="<?php echo clean($reward['name']); ?> preview"
                                                            class="reward-upload-preview"
                                                        >
                                                    <?php else: ?>
                                                        <img
                                                            id="reward-edit-preview-<?php echo $reward['id']; ?>"
                                                            class="reward-upload-preview"
                                                            alt="Selected reward image preview"
                                                            hidden
                                                        >
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted reward-upload-note">Leave blank to keep the current image. Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB. Recommended size: 400x400px.</small>
                                            </div>
                                            <button type="submit" name="update_reward" class="btn btn-success btn-block">Save Changes</button>
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
        </div>

        <?php if ($totalRewardPages > 1): ?>
            <div class="pagination-wrap">
                <p class="pagination-summary">
                    Showing <?php echo $rewardStartRow; ?>-<?php echo $rewardEndRow; ?> of <?php echo $totalRewards; ?> rewards
                </p>
                <nav class="pagination" aria-label="Rewards table pages">
                    <?php if ($currentRewardsPage > 1): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildRewardPageUrl($currentRewardsPage - 1)); ?>">Previous</a>
                    <?php endif; ?>

                    <span class="pagination-page">Page <?php echo $currentRewardsPage; ?> of <?php echo $totalRewardPages; ?></span>

                    <?php if ($currentRewardsPage < $totalRewardPages): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildRewardPageUrl($currentRewardsPage + 1)); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted">No rewards created yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
