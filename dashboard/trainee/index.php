<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainee'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['trn_usr_msg'] ?? '';
$err   = $_SESSION['trn_usr_err'] ?? '';
unset($_SESSION['trn_usr_msg'], $_SESSION['trn_usr_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));

/**
 * Detect or ensure trainee foreign-key column (`user_id`, `trainee_id`, or `student_id`)
 * as well as `assessment_score`, `attendance_pct`, `payment_status`, and `created_at` on `enrollments`.
 */
$enr_user_col   = 'trainee_id';
$has_pay_status = false;
$has_created_at = false;

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
    if (!in_array('attendance_pct', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `attendance_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('payment_status', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `payment_status` VARCHAR(30) NOT NULL DEFAULT 'Unpaid'");
    }
    $has_pay_status = true;

    if (!in_array('created_at', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
    $has_created_at = true;
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'enroll_program') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        if ($uid <= 0) {
            $_SESSION['trn_usr_err'] = $is_am ? 'የተጠቃሚ መለያ አልተገኘም፤ እባክዎ እንደገና ይግቡ።' : 'User session not found. Please log in again.';
        } elseif ($prog_id > 0) {
            try {
                $chk = $conn->prepare("SELECT id FROM `enrollments` WHERE `{$enr_user_col}` = ? AND `program_id` = ? LIMIT 1");
                $exists = false;
                if ($chk) {
                    $chk->bind_param("ii", $uid, $prog_id);
                    $chk->execute();
                    $r = $chk->get_result();
                    if ($r && $r->num_rows > 0) {
                        $exists = true;
                    }
                    $chk->close();
                }

                if ($exists) {
                    $_SESSION['trn_usr_err'] = $is_am ? 'ቀደም ብለው በዚህ ፕሮግራም ተመዝግበዋል።' : 'You are already enrolled in this program.';
                } else {
                    if ($has_pay_status) {
                        $stmt = $conn->prepare("INSERT INTO `enrollments` (`{$enr_user_col}`, `program_id`, `status`, `payment_status`) VALUES (?, ?, 'Pending', 'Unpaid')");
                    } else {
                        $stmt = $conn->prepare("INSERT INTO `enrollments` (`{$enr_user_col}`, `program_id`, `status`) VALUES (?, ?, 'Pending')");
                    }
                    if ($stmt) {
                        $stmt->bind_param("ii", $uid, $prog_id);
                        if ($stmt->execute()) {
                            $_SESSION['trn_usr_msg'] = $is_am ? 'የምዝገባ ጥያቄዎ ተልኳል።' : 'Enrollment request submitted.';
                        } else {
                            $_SESSION['trn_usr_err'] = $is_am ? 'ምዝገባውን ማጠናቀቅ አልተቻለም።' : 'Failed to submit enrollment.';
                        }
                        $stmt->close();
                    }
                }
            } catch (Throwable $e) {
                $_SESSION['trn_usr_err'] = $e->getMessage();
            }
        }
        $anchor = ($prog_id > 0) ? ('#avail-prog-row-' . $prog_id) : '#available-programs-panel';
        header("Location: " . $self_file . $anchor);
        exit;
    } elseif ($act === 'cancel_enrollment') {
        $enr_id = (int)($_POST['enrollment_id'] ?? 0);
        if ($uid <= 0) {
            $_SESSION['trn_usr_err'] = $is_am ? 'የተጠቃሚ መለያ አልተገኘም፤ እባክዎ እንደገና ይግቡ።' : 'User session not found. Please log in again.';
        } elseif ($enr_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM `enrollments` WHERE `id` = ? AND `{$enr_user_col}` = ? AND `status` = 'Pending'");
                if ($stmt) {
                    $stmt->bind_param("ii", $enr_id, $uid);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $_SESSION['trn_usr_msg'] = $is_am ? 'ምዝገባዎ ተሰርዟል።' : 'Pending enrollment cancelled.';
                    } else {
                        $_SESSION['trn_usr_err'] = $is_am ? 'ሊሰረዝ የሚችል በመጠባበቅ ላይ ያለ ምዝገባ አልተገኘም።' : 'No pending enrollment found to cancel.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['trn_usr_err'] = $e->getMessage();
            }
        }
        header("Location: " . $self_file . "#my-enrollments-panel");
        exit;
    }
}

$available_programs = [];
$my_enrollments     = [];
$enrolled_prog_ids  = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am, category, fee_etb, status FROM `training_programs` ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $available_programs[] = $p;
        }
    }

    if ($uid > 0) {
        $created_select = $has_created_at ? ", e.created_at" : ", NULL AS created_at";
        $stmt = $conn->prepare("
            SELECT e.id, e.program_id, e.status, e.payment_status, e.assessment_score, e.attendance_pct{$created_select},
                   p.code, p.title_en, p.title_am, p.fee_etb
            FROM `enrollments` e
            LEFT JOIN `training_programs` p ON e.program_id = p.id
            WHERE e.`{$enr_user_col}` = ?
            ORDER BY e.id DESC
        ");
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $my_enrollments[] = $row;
                    $enrolled_prog_ids[(int)($row['program_id'] ?? 0)] = true;
                }
            }
            $stmt->close();
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
            <h1><?php echo $is_am ? 'የሰልጣኝ (Trainee) ዳሽቦርድ' : 'Trainee Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'ለስልጠና ፕሮግራሞች መመዝገብ እና የምዝገባ፣ የውጤት እና የመገኘት ሁኔታ መከታተል' : 'Browse available programs, manage enrollments, and track progress'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ያሉ ፕሮግራሞች' : 'Available Programs'; ?></p><h2><?php echo count($available_programs); ?></h2></div>
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'የእኔ ምዝገባዎች' : 'My Enrollments'; ?></p><h2><?php echo count($my_enrollments); ?></h2></div>
            <i class="bi bi-check2-square"></i>
        </div>
    </div>

    <div class="card-box" id="available-programs-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'ክፍት የስልጠና ፕሮግራሞች' : 'Available Training Programs'; ?>
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ኮድ / ፕሮግራም' : 'Code & Program'; ?></th>
                        <th><?php echo $is_am ? 'ክፍያ (ETB)' : 'Fee (ETB)'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Enroll'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($available_programs)): ?>
                        <?php foreach ($available_programs as $p):
                            $pid     = (int)$p['id'];
                            $ptitle  = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : ($p['title_en'] ?? '');
                            $already = isset($enrolled_prog_ids[$pid]);
                        ?>
                        <tr id="avail-prog-row-<?php echo $pid; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)($p['code'] ?? '')); ?></strong> —
                                <?php echo htmlspecialchars((string)$ptitle); ?>
                            </td>
                            <td><?php echo number_format((float)($p['fee_etb'] ?? 0), 2); ?></td>
                            <td><?php echo status_badge((string)($p['status'] ?? 'Open')); ?></td>
                            <td>
                                <?php if ($already): ?>
                                    <span class="badge"><?php echo $is_am ? 'ተመዝግበዋል' : 'Enrolled'; ?></span>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="enroll_program">
                                        <input type="hidden" name="program_id" value="<?php echo $pid; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-circle"></i> <?php echo $is_am ? 'ተመዝገብ' : 'Enroll'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ፕሮግራም አልተገኘም።' : 'No programs available.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-box" id="my-enrollments-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የእኔ ምዝገባዎች እና ውጤት' : 'My Enrollments & Progress'; ?>
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ምዝገባ / ክፍያ' : 'Status & Payment'; ?></th>
                        <th><?php echo $is_am ? 'ውጤት / መገኘት' : 'Score / Att.'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Action'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($my_enrollments)): ?>
                        <?php foreach ($my_enrollments as $e):
                            $eid    = (int)$e['id'];
                            $ptitle = ($is_am && !empty($e['title_am'])) ? $e['title_am'] : ($e['title_en'] ?? '');
                        ?>
                        <tr id="my-enr-row-<?php echo $eid; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)($e['code'] ?? '')); ?></strong> —
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
                            <td>
                                <?php if (($e['status'] ?? '') === 'Pending'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ምዝገባ መሰረዝ ይፈልጋሉ?' : 'Cancel this pending enrollment?'; ?>');">
                                        <input type="hidden" name="action" value="cancel_enrollment">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                            <i class="bi bi-x-circle"></i> <?php echo $is_am ? 'ሰርዝ' : 'Cancel'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'እስካሁን የተመዘገቡበት ፕሮግራም የለም።' : 'No enrollments yet.')); ?></td></tr>
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