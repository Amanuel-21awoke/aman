<?php
// includes/functions.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'am') ? 'am' : 'en';
}

$lang_file = __DIR__ . "/lang_" . $_SESSION['lang'] . ".php";
$lang = file_exists($lang_file) ? include($lang_file) : include(__DIR__ . "/lang_en.php");

function sanitize_phone_10($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    return substr($digits, 0, 10);
}

function clean_input($data) {
    return htmlspecialchars(stripslashes(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

function check_login($base_path = '') {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $base_path . "login.php");
        exit();
    }
}

function require_role($allowed_roles, $base_path = '../../') {
    check_login($base_path);
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, (array)$allowed_roles, true)) {
        header("Location: " . get_dashboard_url($role, $base_path));
        exit();
    }
}

function get_dashboard_url($role, $base_path = '') {
    switch ($role) {
        case 'admin':
            return $base_path . 'dashboard/admin/index.php';
        case 'coordinator':
            return $base_path . 'dashboard/coordinator/index.php';
        case 'trainer':
            return $base_path . 'dashboard/trainer/index.php';
        case 'trainee':
            return $base_path . 'dashboard/trainee/index.php';
        case 'finance_officer':
            return $base_path . 'dashboard/finance/index.php';
        case 'qa_evaluator':
            return $base_path . 'dashboard/qa/index.php';
        case 'registrar':
            return $base_path . 'dashboard/registrar/index.php';
        case 'lab_technician':
            return $base_path . 'dashboard/lab_technician/index.php';
        case 'curriculum_developer':
            return $base_path . 'dashboard/curriculum_developer/index.php';
        case 'sponsor_liaison':
            return $base_path . 'dashboard/sponsor_liaison/index.php';
        case 'certificate_officer':
            return $base_path . 'dashboard/certificate_officer/index.php';
        case 'events_coordinator':
            return $base_path . 'dashboard/events_coordinator/index.php';
        case 'college_dean':
            return $base_path . 'dashboard/college_dean/index.php';
        case 'department_head':
            return $base_path . 'dashboard/department_head/index.php';
        case 'monitoring_officer':
            return $base_path . 'dashboard/monitoring_officer/index.php';
        case 'support_staff':
            return $base_path . 'dashboard/support_staff/index.php';
        case 'alumni_coordinator':
            return $base_path . 'dashboard/alumni_coordinator/index.php';
        default:
            return $base_path . 'dashboard.php';
    }
}

function get_profile_image($conn, $user_id) {
    if (!$conn || !$user_id) return "assets/default_avatar.svg";
    $stmt = $conn->prepare("SELECT photo_url FROM profiles WHERE user_id = ?");
    if (!$stmt) return "assets/default_avatar.svg";
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($res && !empty($res['photo_url'])) {
        $base_dir = dirname(__DIR__) . '/';
        $candidate = str_replace('assets/default_avatar.png', 'assets/default_avatar.svg', $res['photo_url']);
        if (file_exists($base_dir . $candidate)) {
            return $candidate;
        }
        if (file_exists($base_dir . $res['photo_url'])) {
            return $res['photo_url'];
        }
    }
    return "assets/default_avatar.svg";
}

function get_unread_messages_count($conn, $user_id) {
    if (!$conn || !$user_id) return 0;
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}

function get_pending_enrollments_count($conn) {
    if (!$conn) return 0;
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE status = 'Pending'");
    return $res ? (int)($res->fetch_assoc()['cnt'] ?? 0) : 0;
}

function get_pending_payments_count($conn) {
    if (!$conn) return 0;
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM financial_transactions WHERE status = 'Pending'");
    return $res ? (int)($res->fetch_assoc()['cnt'] ?? 0) : 0;
}

function get_role_workspace_logs_count($conn, $role_name = null) {
    if (!$conn) return 0;
    try {
        if ($role_name !== null && $role_name !== '') {
            $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM role_workspace_logs WHERE role_name = ?");
            if (!$stmt) return 0;
            $stmt->bind_param("s", $role_name);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int)($row['cnt'] ?? 0);
        }
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM role_workspace_logs");
        return $res ? (int)($res->fetch_assoc()['cnt'] ?? 0) : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function status_badge($status) {
    $map = [
        'Approved' => '#00b894',
        'Verified' => '#00b894',
        'Endorsed' => '#00b894',
        'On Track' => '#00b894',
        'Resolved' => '#00b894',
        'Paid' => '#00b894',
        'Sponsored' => '#0984e3',
        'Completed' => '#6c5ce7',
        'Open' => '#00b894',
        'Ongoing' => '#0984e3',
        'Scheduled' => '#0984e3',
        'In Progress' => '#0984e3',
        'Pending' => '#f39c12',
        'Pending Verification' => '#f39c12',
        'Revision Requested' => '#f39c12',
        'Action Needed' => '#e17055',
        'Escalated' => '#e17055',
        'Deferred' => '#636e72',
        'Waitlist' => '#e17055',
        'Rejected' => '#d63031',
        'Unpaid' => '#d63031',
        'active' => '#00b894',
        'inactive' => '#d63031'
    ];
    $color = $map[$status] ?? '#636e72';
    return "<span class='badge-status' style='background: {$color};'>" . htmlspecialchars($status) . "</span>";
}

if (!function_exists('__t')) {
    function __t(string $key, string $en, string $am): string {
        global $lang;
        if (isset($lang[$key]) && $lang[$key] !== '') {
            return (string)$lang[$key];
        }
        $current_lang = $_SESSION['lang'] ?? 'en';
        return ($current_lang === 'am') ? $am : $en;
    }
}
if (!function_exists('e_t')) {
    function e_t(string $key, string $en, string $am): string {
        return htmlspecialchars(__t($key, $en, $am), ENT_QUOTES, 'UTF-8');
    }
}