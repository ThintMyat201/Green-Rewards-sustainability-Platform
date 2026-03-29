<?php
session_start();

if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /admin/dashboard.php');
            break;
        case 'moderator':
            header('Location: /mod/verify.php');
            break;
        case 'student':
            header('Location: /student/dashboard.php');
            break;
        case 'staff':
            header('Location: /staff/dashboard.php');
            break;
    }
    exit;
}

header('Location: /login.php');
exit;
?>