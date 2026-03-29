<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php', 'Access denied', 'danger');
}

$error = '';
$success = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = clean($_POST['role'] ?? 'student');
    $department = clean($_POST['department'] ?? '');
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email already exists';
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?)"
            );
            if ($stmt->execute([$name, $email, $passwordHash, $role, $department])) {
                $success = 'User created successfully!';
            } else {
                $error = 'Failed to create user';
            }
        }
    }
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = (int) $_POST['user_id'];
    $newRole = clean($_POST['new_role']);
    
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    if ($stmt->execute([$newRole, $userId])) {
        $success = 'User role updated successfully!';
    } else {
        $error = 'Failed to update role';
    }
}

// Handle points adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_points'])) {
    $userId = (int) $_POST['user_id'];
    $pointsChange = (int) $_POST['points_change'];
    $reason = clean($_POST['reason'] ?? 'Admin adjustment');
    
    $stmt = $pdo->prepare("UPDATE users SET points_total = points_total + ? WHERE id = ?");
    if ($stmt->execute([$pointsChange, $userId])) {
        // Log the adjustment
        $stmt = $pdo->prepare(
            "INSERT INTO points_log (user_id, source, points_earned, description) VALUES (?, 'admin_adjust', ?, ?)"
        );
        $stmt->execute([$userId, $pointsChange, $reason]);
        $success = 'Points adjusted successfully!';
    } else {
        $error = 'Failed to adjust points';
    }
}

// Get filter
$roleFilter = $_GET['role'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($roleFilter !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'User Management';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3">👥 User Management</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header">➕ Create New User</h2>
    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>Role *</label>
                <select name="role" required>
                    <option value="student">Student</option>
                    <option value="staff">Staff</option>
                    <option value="moderator">Moderator</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Department</label>
                <select name="department">
                    <option value="">None</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Engineering">Engineering</option>
                    <option value="Business">Business</option>
                </select>
            </div>
        </div>
        
        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
    </form>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 class="card-header" style="margin: 0;">All Users (<?php echo count($users); ?>)</h2>
        
        <form method="GET" style="display: flex; gap: 0.5rem;">
            <select name="role" onchange="this.form.submit()">
                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Students</option>
                <option value="staff" <?php echo $roleFilter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                <option value="moderator" <?php echo $roleFilter === 'moderator' ? 'selected' : ''; ?>>Moderators</option>
                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admins</option>
            </select>
            
            <input type="text" name="search" placeholder="Search..." value="<?php echo clean($searchQuery); ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        </form>
    </div>
    
    <?php if (count($users) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Points</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo clean($u['name']); ?></td>
                        <td><?php echo clean($u['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $u['role'] === 'admin' ? 'danger' : 
                                    ($u['role'] === 'moderator' ? 'warning' : 
                                    ($u['role'] === 'staff' ? 'info' : 'success')); 
                            ?>">
                                <?php echo ucfirst($u['role']); ?>
                            </span>
                        </td>
                        <td><?php echo clean($u['department'] ?? '-'); ?></td>
                        <td><strong><?php echo number_format($u['points_total']); ?></strong></td>
                        <td><?php echo formatDate($u['created_at']); ?></td>
                        <td>
                            <button onclick="document.getElementById('modal-<?php echo $u['id']; ?>').style.display='block'" 
                                    class="btn btn-primary btn-sm">Manage</button>
                            
                            <!-- User Management Modal -->
                            <div id="modal-<?php echo $u['id']; ?>" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; padding: 2rem;">
                                <div style="background: white; max-width: 500px; margin: 50px auto; padding: 2rem; border-radius: 10px; max-height: 80vh; overflow-y: auto;">
                                    <h3>Manage: <?php echo clean($u['name']); ?></h3>
                                    
                                    <form method="POST" style="margin-top: 1rem;">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <div class="form-group">
                                            <label>Change Role</label>
                                            <select name="new_role">
                                                <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                <option value="staff" <?php echo $u['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                <option value="moderator" <?php echo $u['role'] === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="update_role" class="btn btn-warning btn-block">Update Role</button>
                                    </form>
                                    
                                    <form method="POST" style="margin-top: 1rem;">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <div class="form-group">
                                            <label>Adjust Points</label>
                                            <input type="number" name="points_change" placeholder="+100 or -50" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Reason</label>
                                            <input type="text" name="reason" placeholder="e.g., Bonus reward">
                                        </div>
                                        <button type="submit" name="adjust_points" class="btn btn-success btn-block">Adjust Points</button>
                                    </form>
                                    
                                    <button onclick="document.getElementById('modal-<?php echo $u['id']; ?>').style.display='none'" 
                                            class="btn btn-secondary btn-block mt-2">Close</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No users found.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>