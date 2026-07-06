<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$error = '';
$success = '';
$roleOptions = ['student', 'staff', 'moderator', 'admin'];

$departmentOptions = getDepartmentOptions();
$departmentOptionsAll = getDepartmentOptions(false);

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = clean($_POST['role'] ?? 'student');
    $departmentInput = clean($_POST['department'] ?? '');
    $department = $departmentInput === '' ? null : $departmentInput;
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!isValidApuEmail($email)) {
        $error = 'Only @mail.apu.edu.my email addresses are allowed';
    } elseif ($department !== null && !isValidDepartment($department, true)) {
        $error = 'Invalid department selected';
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

// Handle role and department update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_meta'])) {
    $userId = (int) $_POST['user_id'];
    $newRole = clean($_POST['new_role'] ?? '');
    $newDepartmentInput = clean($_POST['new_department'] ?? '');
    $newDepartment = $newDepartmentInput === '' ? null : $newDepartmentInput;

    if (!in_array($newRole, $roleOptions, true)) {
        $error = 'Invalid role selected';
    } elseif ($newDepartment !== null && !isValidDepartment($newDepartment, false)) {
        $error = 'Invalid department selected';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ?, department = ? WHERE id = ?");
        if ($stmt->execute([$newRole, $newDepartment, $userId])) {
            $success = 'User role and department updated successfully!';
        } else {
            $error = 'Failed to update user details';
        }
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        $error = 'Invalid user selected';
    } elseif ($userId === $currentUserId) {
        $error = 'You cannot delete your own account';
    } else {
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            $error = 'User not found';
        } elseif ($targetUser['role'] === 'admin') {
            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin'");
            $adminCount = (int) ($stmt->fetch()['cnt'] ?? 0);

            if ($adminCount <= 1) {
                $error = 'Cannot delete the last admin account';
            }
        }

        if (!$error) {
            $blockingDependencies = [];
            $dependencyChecks = [
                ['table' => 'challenges', 'column' => 'created_by', 'label' => 'challenges created'],
                ['table' => 'quizzes', 'column' => 'created_by', 'label' => 'quiz questions created'],
                ['table' => 'eco_tips', 'column' => 'posted_by', 'label' => 'eco tips posted'],
                ['table' => 'settings', 'column' => 'updated_by', 'label' => 'settings updates made'],
                ['table' => 'challenge_submissions', 'column' => 'reviewed_by', 'label' => 'challenge reviews made']
            ];

            foreach ($dependencyChecks as $check) {
                $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM {$check['table']} WHERE {$check['column']} = ?");
                $stmt->execute([$userId]);
                $count = (int) ($stmt->fetch()['cnt'] ?? 0);

                if ($count > 0) {
                    $blockingDependencies[] = $check['label'] . ' (' . $count . ')';
                }
            }

            if (!empty($blockingDependencies)) {
                $error = 'Cannot delete user with linked records: ' . implode(', ', $blockingDependencies) . '.';
            }
        }

        if (!$error) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    $success = 'User deleted successfully!';
                } else {
                    $error = 'Failed to delete user';
                }
            } catch (PDOException $e) {
                $error = 'Cannot delete user because it is referenced by other records.';
            }
        }
    }
}

// Get filter
$roleFilter = $_GET['role'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$usersPerPage = 10;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage);

// Build filtered base query
$baseSql = "FROM users WHERE 1=1";
$params = [];

if ($roleFilter !== 'all') {
    $baseSql .= " AND role = ?";
    $params[] = $roleFilter;
}

if (!empty($searchQuery)) {
    $baseSql .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Count filtered users for pagination
$countSql = "SELECT COUNT(*) as total " . $baseSql;
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = (int) ($stmt->fetch()['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalUsers / $usersPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $usersPerPage;

// Paginated users query
$sql = "SELECT * " . $baseSql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$queryParams = array_merge($params, [$usersPerPage, $offset]);
$stmt->execute($queryParams);
$users = $stmt->fetchAll();

$startRow = $totalUsers > 0 ? $offset + 1 : 0;
$endRow = min($offset + $usersPerPage, $totalUsers);

$pageBaseQuery = $_GET;
unset($pageBaseQuery['page']);
$pageBasePath = strtok($_SERVER['REQUEST_URI'], '?');

$buildPageUrl = static function (int $page) use ($pageBaseQuery, $pageBasePath): string {
    $query = $pageBaseQuery;
    $query['page'] = $page;
    return $pageBasePath . '?' . http_build_query($query);
};

$pageTitle = 'User Management';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3"><i class="fa-solid fa-users"></i> User Management</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header"><i class="fa-solid fa-plus"></i> Create New User</h2>
    <form method="POST">
        <div class="admin-form-grid admin-form-grid-2">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
        </div>
        
        <div class="admin-form-grid admin-form-grid-3">
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
                    <?php foreach ($departmentOptions as $dept): ?>
                        <option value="<?php echo clean($dept); ?>"><?php echo clean($dept); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
    </form>
</div>

<div class="card">
    <div class="admin-users-toolbar">
        <h2 class="card-header admin-users-title">All Users (<?php echo $totalUsers; ?>)</h2>
        
        <form method="GET" class="admin-users-filters">
            <select name="role" onchange="this.form.submit()">
                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Students</option>
                <option value="staff" <?php echo $roleFilter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                <option value="moderator" <?php echo $roleFilter === 'moderator' ? 'selected' : ''; ?>>Moderators</option>
                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admins</option>
            </select>
            
            <input type="text" name="search" placeholder="Search..." value="<?php echo clean($searchQuery); ?>">
            <button type="submit" class="btn btn-secondary btn">Search</button>
        </form>
    </div>
    
    <?php if (count($users) > 0): ?>
        <div class="table-responsive">
            <table class="table users-table">
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
                                <div class="reward-actions">
                                    <button onclick="document.getElementById('modal-<?php echo $u['id']; ?>').style.display='block'" 
                                            class="btn btn-edit btn-sm">Edit</button>
                                    <form method="POST" class="admin-inline-form">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button
                                            type="submit"
                                            name="delete_user"
                                            class="btn btn-delete btn-sm action-delete-btn"
                                            onclick="return confirm('Delete this user? This action cannot be undone.');"
                                            title="Delete user"
                                            aria-label="Delete user"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- User Management Modal -->
                                <div id="modal-<?php echo $u['id']; ?>" class="admin-modal-overlay" style="display:none;">
                                    <div class="admin-modal-content">
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
                                            <div class="form-group">
                                                <label>Change Department</label>
                                                <select name="new_department">
                                                    <option value="" <?php echo empty($u['department']) ? 'selected' : ''; ?>>None</option>
                                                    <?php foreach ($departmentOptionsAll as $dept): ?>
                                                        <option value="<?php echo clean($dept); ?>" <?php echo $u['department'] === $dept ? 'selected' : ''; ?>>
                                                            <?php echo clean($dept); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" name="update_user_meta" class="btn btn-warning btn-block">Update Role & Department</button>
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
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrap">
                <p class="pagination-summary">
                    Showing <?php echo $startRow; ?>-<?php echo $endRow; ?> of <?php echo $totalUsers; ?> users
                </p>
                <nav class="pagination" aria-label="Users table pages">
                    <?php if ($currentPage > 1): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildPageUrl($currentPage - 1)); ?>">Previous</a>
                    <?php endif; ?>

                    <span class="pagination-page">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a class="btn btn-secondary btn-sm" href="<?php echo clean($buildPageUrl($currentPage + 1)); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted">No users found.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
