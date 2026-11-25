<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['coordinator'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['crd_enr_msg'] ?? '';
$err   = $_SESSION['crd_enr_err'] ?? '';
unset($_SESSION['crd_enr_msg'], $_SESSION['crd_enr_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'enrollments.php'));
$allowed_status = ['Pending', 'Approved', 'Rejected', 'Completed'];

$status_filter = trim((string)($_GET['status_filter'] ?? 'ALL'));
if ($status_filter !== 'ALL' && !in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'ALL';
}
$search_q = trim((string)($_GET['q'] ?? ''));

$build_return_url = static function (string $anchor = '') use ($self_file, $status_filter, $search_q): string {
    $params = [];
    if ($status_filter !== '' && $status_filter !== 'ALL') {
        $params['status_filter'] = $status_filter;
    }
    if ($search_q !== '') {
        $params['q'] = $search_q;
    }
    $qs = !empty($params) ? ('?' . http_build_query($params)) : '';
    return $self_file . $qs . $anchor;
};

/**
 * Detect or ensure trainee foreign-key column (`user_id`, `trainee_id`, or `student_id`)
 * as well as `assessment_score`, `attendance_pct`, and `payment_status` on `enrollments`.
 */
$enr_user_col   = 'trainee_id';
$has_pay_status = false;
$has_score_col  = false;
$has_att_col    = false;

try {
    $cols_res = $conn->query("SHOW COLUMNS FROM `enrollments`");
    $found_cols = [];
    if ($cols_res) {
        while ($c_row = $cols_res->fetch_assoc()) {
            $found_cols[] = (string)($c_row['Field'] ?? '');
        }
    }

    if (in_array('user_id', $found_cols, true)) {
        $enr_user_col = 'user_id';
    } elseif (in_array('trainee_id', $found_cols, true)) {
        $enr_user_col = 'trainee_id';
    } elseif (in_array('student_id', $found_cols, true)) {
        $enr_user_col = 'student_id';
    } else {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `trainee_id` INT NOT NULL DEFAULT 0");
        $enr_user_col = 'trainee_id';
    }

    if (!in_array('assessment_score', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `assessment_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
    $has_score_col = true;

    if (!in_array('attendance_pct', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `attendance_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
    $has_att_col = true;

    if (!in_array('payment_status', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `payment_status` VARCHAR(30) NOT NULL DEFAULT 'Unpaid'");
    }
    $has_pay_status = true;
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_enrollment_status') {
    $enr_id     = (int)($_POST['enrollment_id'] ?? 0);
    $new_status = trim((string)($_POST['status'] ?? 'Pending'));
    if (!in_array($new_status, $allowed_status, true)) {
        $new_status = 'Pending';
    }

    if ($enr_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE `enrollments` SET `status` = ? WHERE `id` = ?");
            if ($stmt) {
                $stmt->bind_param("si", $new_status, $enr_id);
                if ($stmt->execute()) {
                    $_SESSION['crd_enr_msg'] = $is_am ? 'የምዝገባ ሁኔታ ተዘምኗል።' : 'Enrollment status updated.';
                } else {
                    $_SESSION['crd_enr_err'] = $is_am ? 'የምዝገባ ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update enrollment status.';
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $_SESSION['crd_enr_err'] = $e->getMessage();
        }
    }

    $anchor = ($enr_id > 0) ? ('#crd-enr-row-' . $enr_id) : '#coord-enrollments-panel';
    header("Location: " . $build_return_url($anchor));
    exit;
}

$all_enrollments_count = 0;
$enrollments_list      = [];

try {
    $rc = $conn->query("SELECT COUNT(*) AS c FROM `enrollments`");
    if ($rc) {
        $all_enrollments_count = (int)($rc->fetch_assoc()['c'] ?? 0);
    }

    $where_sql = [];
    $types     = '';
    $params    = [];

    if ($status_filter !== 'ALL') {
        $where_sql[] = "e.status = ?";
        $types .= 's';
        $params[] = $status_filter;
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

    $pay_col   = $has_pay_status ? ", e.payment_status" : ", 'Unpaid' AS payment_status";
    $score_col = $has_score_col ? ", e.assessment_score" : ", 0 AS assessment_score";
    $att_col   = $has_att_col ? ", e.attendance_pct" : ", 0 AS attendance_pct";

    $sql = "
        SELECT e.id, e.status{$pay_col}{$score_col}{$att_col},
               u.name AS trainee_name, u.email AS trainee_email,
               p.code AS program_code, p.title_en, p.title_am
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
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo $is_am ? 'የሰልጣኞች ምዝገባ አስተዳደር' : 'Manage Trainee Enrollments'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'ሁሉንም የሰልጣኞች ምዝገባዎች ማየት፣ ማጣራት እና ሁኔታ ማዘመን' : 'View, filter, and update enrollment statuses across training programs'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ምዝገባዎች' : 'Total Enrollments'; ?></p><h2><?php echo $all_enrollments_count; ?></h2></div>
            <i class="bi bi-card-checklist"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'የሚታዩ ምዝገባዎች' : 'Showing Enrollments'; ?></p><h2><?php echo count($enrollments_list); ?></h2></div>
            <i class="bi bi-list-check"></i>
        </div>
    </div>

    <div class="card-box" id="coord-enrollments-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የምዝገባ ዝርዝር' : 'All Enrollments'; ?> (<?php echo count($enrollments_list); ?> / <?php echo $all_enrollments_count; ?>)
        </h3>

        <form method="GET" action="<?php echo htmlspecialchars($self_file . '#coord-enrollments-panel'); ?>" style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:end; margin-bottom:1rem;">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ አጣራ' : 'Filter by Status'; ?></label>
                <select name="status_filter" class="form-control" style="min-width:165px;">
                    <option value="ALL" <?php echo ($status_filter === 'ALL') ? 'selected' : ''; ?>><?php echo $is_am ? 'ሁሉም ሁኔታዎች' : 'All Statuses'; ?></option>
                    <?php foreach ($allowed_status as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($status_filter === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፈልግ (ሰልጣኝ / ፕሮግራም)' : 'Search (Trainee / Program)'; ?></label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo $is_am ? 'ስም ወይም ፕሮግራም...' : 'Trainee or program...'; ?>">
            </div>
            <div class="form-group" style="margin:0; display:flex; gap:0.4rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> <?php echo $is_am ? 'አሳይ' : 'Show'; ?>
                </button>
                <a href="<?php echo htmlspecialchars($self_file . '#coord-enrollments-panel'); ?>" class="btn btn-outline">
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
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ / ክፍያ' : 'Status & Payment'; ?></th>
                        <th><?php echo $is_am ? 'ውጤት / መገኘት' : 'Score / Att.'; ?></th>
                        <th><?php echo $is_am ? 'ቀይር' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($enrollments_list)): ?>
                        <?php foreach ($enrollments_list as $e):
                            $eid    = (int)$e['id'];
                            $ptitle = ($is_am && !empty($e['title_am'])) ? $e['title_am'] : ($e['title_en'] ?? '');
                        ?>
                        <tr id="crd-enr-row-<?php echo $eid; ?>">
                            <td><?php echo $eid; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($e['trainee_name'] ?? '')); ?></strong>
                                <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)($e['trainee_email'] ?? '')); ?></div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($e['program_code'] ?? '')); ?></strong> —
                                <?php echo htmlspecialchars((string)$ptitle); ?>
                            </td>
                            <td>
                                <?php echo status_badge((string)($e['status'] ?? 'Pending')); ?>
                                <div style="font-size:0.78rem; color:#64748b; margin-top:0.2rem;"><?php echo htmlspecialchars((string)($e['payment_status'] ?? 'Unpaid')); ?></div>
                            </td>
                            <td>
                                <?php echo number_format((float)($e['assessment_score'] ?? 0), 1); ?>% /
                                <?php echo number_format((float)($e['attendance_pct'] ?? 0), 1); ?>%
                            </td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_enrollment_status">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($e['status'] ?? 'Pending') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                        <tr><td colspan="6"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የምዝገባ መረጃ አልተገኘም።' : 'No enrollments found.')); ?></td></tr>
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