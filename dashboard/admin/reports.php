<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);

$msg = $_SESSION['admin_rep_msg'] ?? '';
$err = $_SESSION['admin_rep_err'] ?? '';
unset($_SESSION['admin_rep_msg'], $_SESSION['admin_rep_err']);

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS admin_report_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_title VARCHAR(180) NOT NULL,
            summary_notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_report_note') {
    $r_title = htmlspecialchars_decode(clean_input(trim((string)($_POST['report_title'] ?? ''))), ENT_QUOTES);
    $r_notes = htmlspecialchars_decode(clean_input(trim((string)($_POST['summary_notes'] ?? ''))), ENT_QUOTES);

    if ($r_title === '') {
        $_SESSION['admin_rep_err'] = $is_am ? 'እባክዎ የሪፖርት ማጠቃለያ ርዕስ ያስገቡ።' : 'Please enter a report summary title.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO admin_report_notes (report_title, summary_notes, created_by) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssi", $r_title, $r_notes, $uid);
                if ($stmt->execute()) {
                    $_SESSION['admin_rep_msg'] = $is_am ? 'የሪፖርት ማስታወሻ ተቀምጧል።' : 'Report summary saved.';
                } else {
                    $_SESSION['admin_rep_err'] = $is_am ? 'ማስታወሻውን ማስቀመጥ አልተቻለም።' : 'Failed to save report summary.';
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $_SESSION['admin_rep_err'] = $e->getMessage();
        }
    }
    header("Location: reports.php#report-notes-panel");
    exit;
}

$total_users = 0;
$total_progs = 0;
$total_enr   = 0;
$notes_list  = [];

try {
    $r1 = $conn->query("SELECT COUNT(*) AS c FROM users");
    if ($r1) { $total_users = (int)($r1->fetch_assoc()['c'] ?? 0); }

    $r2 = $conn->query("SELECT COUNT(*) AS c FROM training_programs");
    if ($r2) { $total_progs = (int)($r2->fetch_assoc()['c'] ?? 0); }

    $r3 = $conn->query("SELECT COUNT(*) AS c FROM enrollments");
    if ($r3) { $total_enr = (int)($r3->fetch_assoc()['c'] ?? 0); }

    $res_n = $conn->query("SELECT report_title, summary_notes, created_at FROM admin_report_notes ORDER BY id DESC LIMIT 15");
    if ($res_n) {
        while ($row = $res_n->fetch_assoc()) {
            $notes_list[] = $row;
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
            <h1><?php echo htmlspecialchars($lang['reports_export'] ?? ($is_am ? 'ሪፖርቶች እና ማጠቃለያ' : 'Reports & Summary')); ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስርዓት ስታቲስቲክስ እና የሪፖርት ማስታወሻዎች' : 'System metrics and saved report notes'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ተጠቃሚዎች' : 'Users'; ?></p><h2><?php echo $total_users; ?></h2></div>
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'ፕሮግራሞች' : 'Programs'; ?></p><h2><?php echo $total_progs; ?></h2></div>
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
            <div><p><?php echo $is_am ? 'ምዝገባዎች' : 'Enrollments'; ?></p><h2><?php echo $total_enr; ?></h2></div>
            <i class="bi bi-clipboard-check-fill"></i>
        </div>
    </div>

    <div class="card-box" id="report-notes-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የሪፖርት ማጠቃለያ መዝገብ' : 'Save Report Summary Note'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0.75rem; align-items:end; margin-bottom:1rem;">
            <input type="hidden" name="action" value="save_report_note">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ *' : 'Title *'; ?></label>
                <input type="text" name="report_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ማስታወሻ' : 'Summary Notes'; ?></label>
                <input type="text" name="summary_notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Note'; ?>
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Title'; ?></th>
                        <th><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></th>
                        <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notes_list)): ?>
                        <?php foreach ($notes_list as $n): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string)$n['report_title']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)($n['summary_notes'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($n['created_at'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ማስታወሻ አልተገኘም።' : 'No report notes found.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
<?php include '../../includes/footer.php'; ?>