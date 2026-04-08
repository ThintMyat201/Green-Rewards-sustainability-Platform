<?php
session_start();
require_once __DIR__ . '/../config/functions.php';
session_destroy();
redirect('/auth/login.php');
?>
