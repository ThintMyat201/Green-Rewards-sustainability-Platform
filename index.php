<?php
session_start();
require_once __DIR__ . '/config/functions.php';

if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('/admin/dashboard.php');
        case 'moderator':
            redirect('/mod/verify.php');
        case 'student':
            redirect('/student/dashboard.php');
        case 'staff':
            redirect('/staff/dashboard.php');
    }
}

redirect('/auth/login.php');
?>
