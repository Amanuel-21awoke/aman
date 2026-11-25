<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$section = (string)($_GET['section'] ?? 'overview');
$is_oversight_only = ($section === 'oversight');

$msg = $_SESSION['admin_idx_msg'] ?? '';
$err = $_SESSION['admin_idx_err'] ?? '';
unset($_SESSION['admin_idx_msg'], $_SESSION['admin_idx_err']);

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS role_oversight_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_role VARCHAR(60) NOT NULL,
            action_summary VARCHAR(200) NOT NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'log_oversight') {
    $t_role = htmlspecialchars_decode(clean_input(trim((string)($_POST['target_role'] ?? ''))), ENT_QUOTES);
    $a_sum  = htmlspecialchars_decode(clean_input(trim((string)($_POST['action_summary'] ?? ''))), ENT_QUOTES);
    $notes  = htmlspecialchars_decode(clean_input(trim((string)($_POST['notes'] ?? ''))), ENT_QUOTES);
    $ret_sec = (($_POST['return_section'] ?? '') === 'oversight') ? 'oversight' : 'overview';

    if ($t_role === '' || $a_sum === '') {
        $_SESSION['admin_idx_err'] = $is_am ? 'እባክዎ የሚና እና የተግባር ማጠቃለያ ያስገቡ።' : 'Please enter target role and action summary.';
    } else {
        $stmt = $conn->prepare("INSERT INTO role_oversight_logs (target_role, action_summary, notes, created_by) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssi", $t_role, $a_sum, $notes, $uid);
            if ($stmt->execute()) {
                $_SESSION['admin_idx_msg'] = $is_am ? 'የሚና ክትትል መዝገብ ተቀምጧል።' : 'Role oversight entry saved.';
            } else {
                $_SESSION['admin_idx_err'] = $is_am ? 'መዝገቡን ማስቀመጥ አልተቻለም።' : 'Failed to save oversight entry.';
            }
            $stmt->close();
        }
    }

    $target_loc = ($ret_sec === 'oversight')
        ? 'index.php?section=oversight#role-oversight'
        : 'index.php#role-oversight';
    header("Location: " . $target_loc);
    exit;
}

$users_cnt    = 0;
$programs_cnt = 0;
$enr_cnt      = 0;
$pay_cnt      = 0;
$logs         = [];

try {
    if (!$is_oversight_only) {
        $r1 = $conn->query("SELECT COUNT(*) AS c FROM users");
        if ($r1) { $users_cnt = (int)($r1->fetch_assoc()['c'] ?? 0); }

        $r2 = $conn->query("SELECT COUNT(*) AS c FROM training_programs");
        if ($r2) { $programs_cnt = (int)($r2->fetch_assoc()['c'] ?? 0); }

        $r3 = $conn->query("SELECT COUNT(*) AS c FROM enrollments");
        if ($r3) { $enr_cnt = (int)($r3->fetch_assoc()['c'] ?? 0); }

        $r4 = $conn->query("SELECT COUNT(*) AS c FROM enrollments WHERE payment_status = 'Pending'");
        if ($r4) { $pay_cnt = (int)($r4->fetch_assoc()['c'] ?? 0); }
    }

    $res_l = $conn->query("SELECT target_role, action_summary, notes, created_at FROM role_oversight_logs ORDER BY id DESC LIMIT 15");
    if ($res_l) {
        while ($row = $res_l->fetch_assoc()) {
            $logs[] = $row;
        }
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1>
                <?php echo $is_oversight_only
                    ? ($is_am ? 'የሚና ክትትል እና ማስተካከያ መዝገብ' : 'Role Oversight & Audit Log')
                    : htmlspecialchars($lang['my_dashboard'] ?? ($is_am ? 'የአስተዳዳሪ ዳሽቦርድ' : 'Admin Dashboard')); ?>
            </h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_oversight_only
                    ? ($is_am ? 'ለየብቻ የተከፈተ የሚና ክትትል እና ማስተካከያ መዝገብ' : 'Dedicated Role Oversight & Audit Log Workspace')
                    : ($is_am ? 'የስርዓት አጠቃላይ ቁጥጥር እና የሚና ክትትል' : 'System Overview & Role Oversight'); ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <?php if (!$is_oversight_only): ?>
    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ተጠቃሚዎች' : 'Total Users'; ?></p><h2><?php echo $users_cnt; ?></h2></div>
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'ፕሮግራሞች' : 'Programs'; ?></p><h2><?php echo $programs_cnt; ?></h2></div>
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
            <div><p><?php echo $is_am ? 'ምዝገባዎች' : 'Enrollments'; ?></p><h2><?php echo $enr_cnt; ?></h2></div>
            <i class="bi bi-clipboard-check-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#b45309,#f59e0b);">
            <div><p><?php echo $is_am ? 'በመጠባበቅ ላይ ያሉ ክፍያዎች' : 'Pending Payments'; ?></p><h2><?php echo $pay_cnt; ?></h2></div>
            <i class="bi bi-cash-coin"></i>
        </div>
    </div>
    <?php endif; ?>

    <div class="card-box" id="role-oversight">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የሚና ክትትል እና ማስተካከያ መዝገብ' : 'Role Oversight & Audit Log'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end; margin-bottom:1rem;">
            <input type="hidden" name="action" value="log_oversight">
            <input type="hidden" name="return_section" value="<?php echo $is_oversight_only ? 'oversight' : 'overview'; ?>">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሚና' : 'Target Role'; ?></label>
                <input type="text" name="target_role" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የተግባር ማጠቃለያ' : 'Action Summary'; ?></label>
                <input type="text" name="action_summary" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'መዝግብ' : 'Save Entry'; ?>
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ሚና' : 'Role'; ?></th>
                        <th><?php echo $is_am ? 'ማጠቃለያ' : 'Summary'; ?></th>
                        <th><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></th>
                        <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $lg): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lg['target_role']); ?></strong></td>
                            <td><?php echo htmlspecialchars($lg['action_summary']); ?></td>
                            <td><?php echo htmlspecialchars((string)($lg['notes'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)$lg['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም መዝገብ አልተገኘም።' : 'No records found.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target) {
            target.scrollIntoView({ behavior: 'auto', block: 'start' });
        }
    }
});
</script>
<?php include '../../includes/footer.php'; ?>