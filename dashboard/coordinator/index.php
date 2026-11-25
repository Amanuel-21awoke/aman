<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['coordinator'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['crd_msg'] ?? '';
$err   = $_SESSION['crd_err'] ?? '';
unset($_SESSION['crd_msg'], $_SESSION['crd_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));

$allowed_prog_status = ['Open', 'Ongoing', 'Completed', 'Closed'];
$allowed_enr_status  = ['Pending', 'Approved', 'Rejected', 'Completed'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'update_program_status') {
        $prog_id  = (int)($_POST['program_id'] ?? 0);
        $status_v = trim((string)($_POST['status'] ?? 'Open'));
        if (!in_array($status_v, $allowed_prog_status, true)) {
            $status_v = 'Open';
        }

        if ($prog_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE `training_programs` SET `status` = ? WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $status_v, $prog_id);
                    if ($stmt->execute()) {
                        $_SESSION['crd_msg'] = $is_am ? 'የፕሮግራም ሁኔታ ተዘምኗል።' : 'Program status updated.';
                    } else {
                        $_SESSION['crd_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update program status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['crd_err'] = $e->getMessage();
            }
        }

        $anchor = ($prog_id > 0) ? ('#coord-prog-row-' . $prog_id) : '#coord-programs-panel';
        header("Location: " . $self_file . $anchor);
        exit;
    } elseif ($act === 'update_enrollment_status') {
        $enr_id     = (int)($_POST['enrollment_id'] ?? 0);
        $new_status = trim((string)($_POST['status'] ?? 'Pending'));
        if (!in_array($new_status, $allowed_enr_status, true)) {
            $new_status = 'Pending';
        }

        if ($enr_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE `enrollments` SET `status` = ? WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_status, $enr_id);
                    if ($stmt->execute()) {
                        $_SESSION['crd_msg'] = $is_am ? 'የምዝገባ ሁኔታ ተዘምኗል።' : 'Enrollment status updated.';
                    } else {
                        $_SESSION['crd_err'] = $is_am ? 'የምዝገባ ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update enrollment status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['crd_err'] = $e->getMessage();
            }
        }

        $anchor = ($enr_id > 0) ? ('#coord-enr-row-' . $enr_id) : '#coord-enrollments-panel';
        header("Location: " . $self_file . $anchor);
        exit;
    }
}

$programs    = [];
$enrollments = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am, category, duration_days, fee_etb, status FROM `training_programs` ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $pay_col   = $has_pay_status ? ", e.payment_status" : ", 'Unpaid' AS payment_status";
    $score_col = $has_score_col ? ", e.assessment_score" : ", 0 AS assessment_score";
    $att_col   = $has_att_col ? ", e.attendance_pct" : ", 0 AS attendance_pct";

    $sql_e = "
        SELECT e.id, e.status{$pay_col}{$score_col}{$att_col},
               u.name AS trainee_name, u.email AS trainee_email,
               p.code AS program_code, p.title_en, p.title_am
        FROM `enrollments` e
        LEFT JOIN `users` u ON e.`{$enr_user_col}` = u.id
        LEFT JOIN `training_programs` p ON e.program_id = p.id
        ORDER BY e.id DESC
    ";
    $re = $conn->query($sql_e);
    if ($re) {
        while ($row = $re->fetch_assoc()) {
            $enrollments[] = $row;
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
            <h1><?php echo $is_am ? 'የአስተባባሪ (Coordinator) ዳሽቦርድ' : 'Coordinator Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ፕሮግራሞችን እና የሰልጣኞችን ምዝገባ ሁኔታ ማስተባበር' : 'Coordinate training programs and manage trainee enrollment statuses'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ፕሮግራሞች' : 'Programs'; ?></p><h2><?php echo count($programs); ?></h2></div>
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'ምዝገባዎች' : 'Enrollments'; ?></p><h2><?php echo count($enrollments); ?></h2></div>
            <i class="bi bi-people-fill"></i>
        </div>
    </div>

    <div class="card-box" id="coord-programs-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የስልጠና ፕሮግራሞች ሁኔታ' : 'Training Programs Coordination'; ?> (<?php echo count($programs); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ኮድ / ፕሮግራም' : 'Code & Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ቀይር' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($programs)): ?>
                        <?php foreach ($programs as $p):
                            $pid    = (int)$p['id'];
                            $ptitle = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : ($p['title_en'] ?? '');
                        ?>
                        <tr id="coord-prog-row-<?php echo $pid; ?>">
                            <td><?php echo $pid; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($p['code'] ?? '')); ?></strong> —
                                <?php echo htmlspecialchars((string)$ptitle); ?>
                            </td>
                            <td><?php echo status_badge((string)($p['status'] ?? 'Open')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_program_status">
                                    <input type="hidden" name="program_id" value="<?php echo $pid; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_prog_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($p['status'] ?? 'Open') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ፕሮግራም አልተገኘም።' : 'No programs found.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-box" id="coord-enrollments-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የሰልጣኞች ምዝገባዎች' : 'Trainee Enrollments'; ?> (<?php echo count($enrollments); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ሰልጣኝ' : 'Trainee'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ / ክፍያ' : 'Status & Payment'; ?></th>
                        <th><?php echo $is_am ? 'ቀይር' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($enrollments)): ?>
                        <?php foreach ($enrollments as $e):
                            $eid    = (int)$e['id'];
                            $ptitle = ($is_am && !empty($e['title_am'])) ? $e['title_am'] : ($e['title_en'] ?? '');
                        ?>
                        <tr id="coord-enr-row-<?php echo $eid; ?>">
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
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_enrollment_status">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_enr_status as $st): ?>
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
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የምዝገባ መረጃ አልተገኘም።' : 'No enrollments found.')); ?></td></tr>
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