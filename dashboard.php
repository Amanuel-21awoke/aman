<?php
require_once 'includes/functions.php';
check_login('');
$role = $_SESSION['role'] ?? 'trainee';
$target = get_dashboard_url($role, '');
if ($target === 'dashboard.php' || $target === '') {
    $target = 'dashboard/role_workspace.php';
}
header("Location: " . $target);
exit();