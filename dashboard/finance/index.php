<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['finance', 'finance_officer', 'sponsor_liaison'], '../../');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$msg   = $_SESSION['fin_idx_msg'] ?? '';
$err   = $_SESSION['fin_idx_err'] ?? '';
unset($_SESSION['fin_idx_msg'], $_SESSION['fin_idx_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));
$allowed_pay_status = ['Unpaid', 'Pending', 'Submitted', 'Paid', 'Waived', 'Rejected'];

$pay_filter = trim((string)($_GET['pay_filter'] ?? 'ALL'));
if ($pay_filter !== 'ALL' && !in_array($pay_filter, $allowed_pay_status, true)) {
    $pay_filter = 'ALL';
}
$search_q = trim((string)($_GET['q'] ?? ''));

$build_return_url = static function (string $anchor = '') use ($self_file, $pay_filter, $search_q): string {
    $params = [];
    if ($pay_filter !== '' && $pay_filter !== 'ALL') {
        $params['pay_filter'] = $pay_filter;
    }
    if ($search_q !== '') {
        $params['q'] = $search_q;
    }
    $qs = !empty($params) ? ('?' . http_build_query($params)) : '';
    return $self_file . $qs . $anchor;
};

$enr_user_col   = 'trainee_id';
$has_pay_status = false;

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `payment_receipts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `enrollment_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `receipt_ref` VARCHAR(120) NOT NULL,
            `amount_etb` DECIMAL(10,2) DEFAULT 0.00,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $enr_cols = [];
    $r_enr = $conn->query("SHOW COLUMNS FROM `enrollments`");
    if ($r_enr) {
        while ($c = $r_enr->fetch_assoc()) {
            $enr_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (in_array('user_id', $enr_cols, true)) {
        $enr_user_col = 'user_id';
    } elseif (in_array('trainee_id', $enr_cols, true)) {
        $enr_user_col = 'trainee_id';
    } elseif (in_array('student_id', $enr_cols, true)) {
        $enr_user_col = 'student_id';
    } else {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `trainee_id` INT NOT NULL DEFAULT 0");
        $enr_user_col = 'trainee_id';
    }

    if (!in_array('payment_status', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `payment_status` VARCHAR(30) NOT NULL DEFAULT 'Unpaid'");
    }
    $has_pay_status = true;
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = $_POST['action'] ?? '';
    $csrf_val = (string)($_POST['csrf_token'] ?? '');

    if ($act === 'update_payment_status') {
        $enr_id = (int)($_POST['enrollment_id'] ?? 0);
        $anchor = ($enr_id > 0) ? ('#fin-enr-row-' . $enr_id) : '#finance-enrollments-panel';

        if (empty($csrf_val) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_val)) {
            $_SESSION['fin_idx_err'] = $is_am
                ? 'የደህንነት ማረጋገጫ (CSRF) አልተሳካም። እባክዎ ገጹን እንደገና ይጫኑ።'
                : 'Invalid security token (CSRF). Please refresh and try again.';
            header("Location: " . $build_return_url($anchor));
            exit;
        }

        $new_pay_st = trim((string)($_POST['payment_status'] ?? 'Unpaid'));
        if (!in_array($new_pay_st, $allowed_pay_status, true)) {
            $new_pay_st = 'Unpaid';
        }

        if ($enr_id > 0 && $has_pay_status) {
            try {
                $stmt = $conn->prepare("UPDATE `enrollments` SET `payment_status` = ? WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_pay_st, $enr_id);
                    if ($stmt->execute()) {
                        $_SESSION['fin_idx_msg'] = $is_am ? 'የክፍያ ሁኔታ ተዘምኗል።' : 'Payment status updated.';
                    } else {
                        $_SESSION['fin_idx_err'] = $is_am ? 'የክፍያ ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update payment status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['fin_idx_err'] = $e->getMessage();
            }
        }

        header("Location: " . $build_return_url($anchor));
        exit;
    }
}

$total_expected_etb = 0.0;
$total_receipts_etb = 0.0;
$receipts_count     = 0;
$enrollments_list   = [];

try {
    $r_exp = $conn->query("
        SELECT COALESCE(SUM(p.fee_etb), 0) AS total_fee
        FROM `enrollments` e
        LEFT JOIN `training_programs` p ON e.program_id = p.id
    ");
    if ($r_exp) {
        $total_expected_etb = (float)($r_exp->fetch_assoc()['total_fee'] ?? 0);
    }

    $r_rec = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount_etb), 0) AS total_paid FROM `payment_receipts`");
    if ($r_rec) {
        $rec_summary        = $r_rec->fetch_assoc();
        $receipts_count     = (int)($rec_summary['cnt'] ?? 0);
        $total_receipts_etb = (float)($rec_summary['total_paid'] ?? 0);
    }

    $where_sql = [];
    $types     = '';
    $params    = [];

    if ($pay_filter !== 'ALL') {
        $where_sql[] = "e.payment_status = ?";
        $types .= 's';
        $params[] = $pay_filter;
    }

    if ($search_q !== '') {
        $where_sql[] = "(u.name LIKE ? OR u.email LIKE ? OR p.code LIKE ? OR p.title_en LIKE ? OR p.title_am LIKE ?)";
        $types .= 'sssss';
        $like = '%' . $search_q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT e.id, e.status, e.payment_status,
               u.name AS trainee_name, u.email AS trainee_email,
               p.code AS program_code, p.title_en, p.title_am, p.fee_etb
        FROM `enrollments` e
        LEFT JOIN `users` u ON e.`{$enr_user_col}` = u.id
        LEFT JOIN `training_programs` p ON e.program_id = p.id
    ";
    if (!empty($where_sql)) {
        $sql .= " WHERE " . implode(" AND ", $where_sql);
    }
    $sql .= " ORDER BY e.id DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $enrollments_list[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $enrollments_list[] = $row;
            }
        }
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<style>
.fin-dash-page,
.fin-dash-page *,
.fin-dash-page *::before,
.fin-dash-page *::after {
    box-sizing: border-box;
}
.fin-dash-page .form-control {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.fin-dash-page .form-control:focus {
    outline: none;
    border-color: var(--primary-color, #17663f);
    box-shadow: 0 0 0 3px rgba(23, 102, 63, 0.14);
}
@media (max-width: 640px) {
    .fin-dash-page .card-box {
        padding: 1rem 0.85rem;
    }
}
</style>

<div class="content-with-sidebar fin-dash-page">
    <div class="dashboard-header" style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:0.75rem;">
        <div>
            <h1><?php echo $is_am ? 'የፋይናንስ ዳሽቦርድ' : 'Finance Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የሰልጣኞች ክፍያ ሁኔታ እና የደረሰኝ መረጃዎች' : 'Manage trainee payment statuses and program fee collections'; ?>
            </p>
        </div>
        <div>
            <a href="sponsor_task.php" class="btn btn-outline btn-sm">
                <i class="bi bi-building-fill"></i> <?php echo $is_am ? 'የስፖንሰር ተግባር' : 'Sponsor Task'; ?>
            </a>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ የፕሮግራም ክፍያ (ETB)' : 'Total Expected Fees (ETB)'; ?></p><h2><?php echo number_format($total_expected_etb, 2); ?></h2></div>
            <i class="bi bi-cash-stack"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'የገቡ ደረሰኞች (ETB)' : 'Submitted Receipts (ETB)'; ?></p><h2><?php echo number_format($total_receipts_etb, 2); ?> (<?php echo $receipts_count; ?>)</h2></div>
            <i class="bi bi-receipt"></i>
        </div>
    </div>

    <div class="card-box" id="finance-enrollments-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የምዝገባ ክፍያ ሁኔታዎች' : 'Enrollment Payment Statuses'; ?> (<?php echo count($enrollments_list); ?>)
        </h3>

        <form method="GET" action="<?php echo htmlspecialchars($self_file . '#finance-enrollments-panel', ENT_QUOTES, 'UTF-8'); ?>" style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:end; margin-bottom:1rem;">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የክፍያ ሁኔታ አጣራ' : 'Filter by Payment Status'; ?></label>
                <select name="pay_filter" class="form-control" style="min-width:165px;">
                    <option value="ALL" <?php echo ($pay_filter === 'ALL') ? 'selected' : ''; ?>><?php echo $is_am ? 'ሁሉም ሁኔታዎች' : 'All Statuses'; ?></option>
                    <?php foreach ($allowed_pay_status as $pst): ?>
                        <option value="<?php echo htmlspecialchars($pst, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($pay_filter === $pst) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pst, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፈልግ' : 'Search'; ?></label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $is_am ? 'ሰልጣኝ ወይም ፕሮግራም...' : 'Trainee or program...'; ?>">
            </div>
            <div class="form-group" style="margin:0; display:flex; gap:0.4rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> <?php echo $is_am ? 'አሳይ' : 'Show'; ?>
                </button>
                <a href="<?php echo htmlspecialchars($self_file . '#finance-enrollments-panel', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">
                    <?php echo $is_am ? 'ሁሉንም አሳይ' : 'Reset All'; ?>
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ሰልጣኝ' : 'Trainee'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም / ክፍያ' : 'Program & Fee'; ?></th>
                        <th><?php echo $is_am ? 'ምዝገባ / ክፍያ ሁኔታ' : 'Enrollment & Payment'; ?></th>
                        <th><?php echo $is_am ? 'ቀይር' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($enrollments_list)): ?>
                        <?php foreach ($enrollments_list as $e):
                            $eid    = (int)$e['id'];
                            $ptitle = ($is_am && !empty($e['title_am'])) ? $e['title_am'] : ($e['title_en'] ?? '');
                            $cur_ps = (string)($e['payment_status'] ?? 'Unpaid');
                        ?>
                        <tr id="fin-enr-row-<?php echo $eid; ?>">
                            <td><?php echo $eid; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($e['trainee_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)($e['trainee_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($e['program_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong> —
                                <?php echo htmlspecialchars((string)$ptitle, ENT_QUOTES, 'UTF-8'); ?>
                                <div style="font-size:0.78rem; color:#64748b;"><?php echo number_format((float)($e['fee_etb'] ?? 0), 2); ?> ETB</div>
                            </td>
                            <td>
                                <?php echo status_badge((string)($e['status'] ?? 'Pending')); ?>
                                <div style="margin-top:0.25rem;"><?php echo status_badge($cur_ps); ?></div>
                            </td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="update_payment_status">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                    <select name="payment_status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_pay_status as $pst): ?>
                                            <option value="<?php echo htmlspecialchars($pst, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($cur_ps === $pst) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pst, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check2"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የምዝገባ መረጃ አልተገኘም።' : 'No enrollment records found.'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
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