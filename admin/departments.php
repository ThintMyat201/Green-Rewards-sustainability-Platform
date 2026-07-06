<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/auth/login.php', 'Access denied', 'danger');
}

$error = '';
$success = '';

// Process requests only on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_department'])) {
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            $error = 'Department name is required';
        } elseif (strlen($name) > 100) {
            $error = 'Department name must be 100 characters or less';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? LIMIT 1");
            $stmt->execute([$name]);

            if ($stmt->fetch()) {
                $error = 'Department already exists';
            } else {
                // Generate unique code from department name
                $code = strtoupper(preg_replace('/[^a-z0-9]/', '', $name));
                $code = substr($code, 0, 20);
                
                // Ensure code is unique
                $baseCode = $code;
                $counter = 1;
                while (true) {
                    $stmt = $pdo->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
                    $stmt->execute([$code]);
                    if (!$stmt->fetch()) {
                        break;
                    }
                    $code = $baseCode . $counter;
                    $counter++;
                }

                $stmt = $pdo->prepare("INSERT INTO departments (name, code) VALUES (?, ?)");
                if ($stmt->execute([$name, $code])) {
                    $success = 'Department created successfully!';
                } else {
                    $error = 'Failed to create department';
                }
            }
        }
    }

    if (isset($_POST['delete_department'])) {
        $departmentId = (int) ($_POST['department_id'] ?? 0);

        if ($departmentId <= 0) {
            $error = 'Invalid department selected';
        } else {
            $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ? LIMIT 1");
            $stmt->execute([$departmentId]);
            $department = $stmt->fetch();

            if (!$department) {
                $error = 'Department not found';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE department = ?");
                $stmt->execute([$department['name']]);
                $assignedUsers = (int) ($stmt->fetch()['cnt'] ?? 0);

                if ($assignedUsers > 0) {
                    $error = 'Cannot delete department with assigned users';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                    if ($stmt->execute([$departmentId])) {
                        $success = 'Department deleted successfully!';
                    } else {
                        $error = 'Failed to delete department';
                    }
                }
            }
        }
    }
}

$stmt = $pdo->query(
    "SELECT d.*, COUNT(u.id) as member_count
     FROM departments d
     LEFT JOIN users u ON u.department = d.name
     GROUP BY d.id
     ORDER BY d.name ASC"
);
$departments = $stmt->fetchAll();

$pageTitle = 'Department Management';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-3 departments-page-title"><i class="fa-solid fa-building"></i> Department Management</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo clean($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo clean($success); ?></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 class="card-header"><i class="fa-solid fa-plus"></i> Add Department</h2>
    <form method="POST" class="admin-form-grid admin-form-grid-2 departments-create-form">
        <div class="form-group">
            <label>Department Name *</label>
            <input type="text" name="name" maxlength="100" required placeholder="e.g., Environmental Science">
        </div>
        <div class="form-group departments-create-form-action">
            <button type="submit" name="create_department" class="btn btn-primary">Create Department</button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="card-header">Existing Departments</h2>

    <?php if (count($departments) > 0): ?>
        <div class="table-responsive">
            <table class="table users-table departments-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Members</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $department): ?>
                        <tr>
                            <td data-label="Name"><strong><?php echo clean($department['name']); ?></strong></td>
                            <td data-label="Members"><?php echo (int) $department['member_count']; ?></td>
                            <td data-label="Created"><?php echo formatDate($department['created_at']); ?></td>
                            <td>
                                <form method="POST" class="admin-inline-form departments-delete-form">
                                    <input type="hidden" name="department_id" value="<?php echo (int) $department['id']; ?>">
                                    <button type="submit" name="delete_department" class="btn btn-delete btn-sm action-delete-btn" onclick="return confirm('Delete this department?');" title="Delete department" aria-label="Delete department">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No departments found.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
