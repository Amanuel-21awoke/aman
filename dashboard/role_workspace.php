<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../backend/db_connect.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$role = (string)($_SESSION['role'] ?? 'trainee');
if ($role === 'certificate_officer') {
    header("Location: certificate_officer/index.php");
    exit;
}
if ($role === 'sponsor_liaison' || $role === 'finance' || $role === 'finance_officer') {
    header("Location: finance/index.php");
    exit;
}

if (!isset($lang)) {
    $lang_code = $_SESSION['lang'] ?? 'en';
    $lang_path = __DIR__ . "/../includes/lang_" . $lang_code . ".php";
    $lang = file_exists($lang_path) ? include($lang_path) : include(__DIR__ . "/../includes/lang_en.php");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg   = $_SESSION['rw_msg'] ?? '';
$err   = $_SESSION['rw_err'] ?? '';
unset($_SESSION['rw_msg'], $_SESSION['rw_err']);

$base_path = '../';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<style>
.rw-page,
.rw-page *,
.rw-page *::before,
.rw-page *::after {
    box-sizing: border-box;
}
.rw-page .form-control {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.rw-page .form-control:focus {
    outline: none;
    border-color: var(--primary-color, #17663f);
    box-shadow: 0 0 0 3px rgba(23, 102, 63, 0.14);
}
.rw-quick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.rw-quick-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.1rem;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}
.rw-quick-card h4 {
    margin: 0 0 0.35rem 0;
    font-size: 0.98rem;
    color: var(--primary-color, #17663f);
}
.rw-quick-card p {
    margin: 0 0 0.75rem 0;
    font-size: 0.84rem;
    color: #64748b;
}
@media (max-width: 640px) {
    .rw-page .card-box {
        padding: 1rem 0.85rem;
    }
}
</style>

<div class="content-with-sidebar rw-page">
    <div class="dashboard-header">
        <div>
            <h1><?php echo $is_am ? 'የስራ ቦታ ዳሽቦርድ' : 'Role Workspace'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የመለያዎን የስራ ክፍሎች፣ መልዕክቶች እና ፕሮፋይል ያስተዳድሩ' : 'Manage your workspace tasks, messages, and profile'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="rw-quick-grid">
        <div class="rw-quick-card">
            <h4><i class="bi bi-envelope-fill"></i> <?php echo htmlspecialchars($lang['messages'] ?? ($is_am ? 'መልዕክቶች' : 'Messages'), ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><?php echo $is_am ? 'የተላኩ እና የተቀበሉ መልዕክቶችን ይመልከቱ።' : 'View and send workspace messages.'; ?></p>
            <a href="../messages.php" class="btn btn-primary btn-sm"><?php echo $is_am ? 'ክፈት' : 'Open'; ?></a>
        </div>
        <div class="rw-quick-card">
            <h4><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($lang['profile'] ?? ($is_am ? 'ፕሮፋይል' : 'Profile'), ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><?php echo $is_am ? 'የግል መረጃዎን እና ፎቶዎን ያዘምኑ።' : 'Update your profile information and photo.'; ?></p>
            <a href="../profile.php" class="btn btn-outline btn-sm"><?php echo $is_am ? 'ክፈት' : 'Open'; ?></a>
        </div>
        <div class="rw-quick-card">
            <h4><i class="bi bi-gear-fill"></i> <?php echo htmlspecialchars($lang['settings'] ?? ($is_am ? 'ቅንብሮች' : 'Settings'), ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><?php echo $is_am ? 'የቋንቋ እና የመለያ ቅንብሮችን ያስተካክሉ።' : 'Manage language and account preferences.'; ?></p>
            <a href="../settings.php" class="btn btn-outline btn-sm"><?php echo $is_am ? 'ክፈት' : 'Open'; ?></a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash) {
        const el = document.querySelector(window.location.hash);
        if (el) el.scrollIntoView({ behavior: 'auto', block: 'start' });
    }
});
</script>
<?php include '../includes/footer.php'; ?>