<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if (isLoggedIn()) {
    redirect('/index.php');
}

$error = '';
$success = '';
$departmentOptions = [];

$departmentOptions = getDepartmentOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = clean($_POST['role'] ?? 'student');
    $department = clean($_POST['department'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!isValidApuEmail($email)) {
        $error = 'Only @mail.apu.edu.my email addresses are allowed';
    } elseif (!in_array($role, ['student', 'staff'])) {
        $error = 'Invalid role selected';
    } elseif (in_array($role, ['student', 'staff']) && empty($department)) {
        $error = 'Please select a department';
    } elseif (!empty($department) && !isValidDepartment($department, true)) {
        $error = 'Invalid department selected';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Create new user
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, department, points_total, streak_count) 
                 VALUES (?, ?, ?, ?, ?, 0, 0)"
            );
            
            if ($stmt->execute([$name, $email, $passwordHash, $role, $department])) {
                $userId = $pdo->lastInsertId();
                
                // Award 'First Steps' achievement
                $stmt = $pdo->prepare(
                    "INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, 1)"
                );
                $stmt->execute([$userId]);
                
                $success = 'Registration successful! Please login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Green Rewards</title>
    <link rel="stylesheet" href="<?php echo appUrl('/css/style.css'); ?>">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><i class="fa-solid fa-seedling"></i> Green Rewards</h1>
                <p>Create your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <br><a href="<?php echo appUrl('/auth/login.php'); ?>" class="btn btn-primary mt-2">Go to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo $name ?? ''; ?>" 
                               placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo $email ?? ''; ?>" 
                               placeholder="john@mail.apu.edu.my">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="student" <?php echo ($role ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="staff" <?php echo ($role ?? '') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departmentOptions as $dept): ?>
                                <option value="<?php echo clean($dept); ?>" <?php echo ($department ?? '') === $dept ? 'selected' : ''; ?>>
                                    <?php echo clean($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password * (min 6 characters)</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Register</button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="<?php echo appUrl('/auth/login.php'); ?>">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
