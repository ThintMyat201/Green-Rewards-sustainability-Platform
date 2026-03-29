<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (isLoggedIn()) {
    redirect('/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    redirect('/admin/dashboard.php', 'Welcome back, ' . $user['name'] . '!', 'success');
                    break;
                case 'moderator':
                    redirect('/mod/verify.php', 'Welcome back, ' . $user['name'] . '!', 'success');
                    break;
                case 'student':
                    redirect('/student/dashboard.php', 'Welcome back, ' . $user['name'] . '!', 'success');
                    break;
                case 'staff':
                    redirect('/staff/dashboard.php', 'Welcome back, ' . $user['name'] . '!', 'success');
                    break;
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Green Rewards</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>🌿 Green Rewards</h1>
                <p>Login to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo $email ?? ''; ?>" 
                           placeholder="student@green.edu">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="/register.php">Register here</a></p>
            </div>
            
            <div class="demo-credentials">
                <h3>Demo Accounts</h3>
                <p><strong>Admin:</strong> admin@green.edu / admin123</p>
                <p><strong>Moderator:</strong> mod@green.edu / mod123</p>
                <p><strong>Student:</strong> student1@green.edu / student123</p>
                <p><strong>Staff:</strong> staff1@green.edu / staff123</p>
            </div>
        </div>
    </div>
</body>
</html>