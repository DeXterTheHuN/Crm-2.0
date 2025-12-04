<?php
require_once 'config.php';
require_once 'audit_helper.php';

if (isLoggedIn()) {
    logLogout($pdo, $_SESSION['user_id']);
}

session_destroy();
redirect('login.php');

