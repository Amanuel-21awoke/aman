<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainee'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['tr_prog_msg'] ?? '';
$err   = $_SESSION['tr_prog_err'] ?? '';
unset($_SESSION['tr_prog_msg'], $_SESSION['tr_prog_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'programs.php'));

/**
 * Detect or ensure trainee foreign-key column and `payment_status` on `enrollments`,
 * and detect optional trainer foreign-key column on `training_programs`.
 */
$enr_user_col   = 'trainee_id';
$has_pay_status = false;
$prog_trn_col   = null;

try {
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

    $prog_cols = [];
    $r_prog = $conn->query("SHOW COLUMNS FROM `training_programs`");
    if ($r_prog) {
        while ($c = $r_prog->fetch_assoc()) {
            $prog_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (in_array('trainer_id', $prog_cols, true)) {
        $prog_trn_col = 'trainer_id';
    } elseif (in_array('instructor_id', $prog_cols, true)) {
        $prog_trn_col = 'instructor_id';
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enroll_program') {
    $prog_id = (int)($_POST['program_id'] ?? 0);

    if ($uid <= 0) {
        $_SESSION['tr_prog_err'] = $is_am ? 'የተጠቃሚ መለያ አልተገኘም፤ እባክዎ እንደገና ይግቡ።' : 'User session not found. Please log in again.';
        header("Location: " . $self_file . "#available-programs-panel");
        exit;
    }

    if ($prog_id <= 0) {
        $_SESSION['tr_prog_err'] = $is_am ? 'የተሳሳተ ፕሮግራም ተመርጧል።' : 'Invalid program selection.';
        header("Location: " . $self_file . "#available-programs-panel");
        exit;
    }

    try {
        $chk_p = $conn->prepare("SELECT id, fee_etb, status FROM `training_programs` WHERE `id` = ? LIMIT 1");
        $prog_row = null;
        if ($chk_p) {
            $chk_p->bind_param("i", $prog_id);
            $chk_p->execute();
            $res_p = $chk_p->get_result();
            if ($res_p) {
                $prog_row = $res_p->fetch_assoc();
            }
            $chk_p->close();
        }

        if (!$prog_row) {
            $_SESSION['tr_prog_err'] = $is_am ? 'ፕሮግራሙ አልተገኘም።' : 'Training program not found.';
        } else {
            $chk_e = $conn->prepare("SELECT id FROM `enrollments` WHERE `{$enr_user_col}` = ? AND `program_id` = ? LIMIT 1");
            $already = false;
            if ($chk_e) {
                $chk_e->bind_param("ii", $uid, $prog_id);
                $chk_e->execute();
                $res_e = $chk_e->get_result();
                $already = ($res_e && $res_e->num_rows > 0);
                $chk_e->close();
            }

            if ($already) {
                $_SESSION['tr_prog_msg'] = $is_am ? 'ቀድሞ በዚህ ፕሮግራም ተመዝግበዋል።' : 'You are already enrolled in this program.';
            } else {
                $fee_val = (float)($prog_row['fee_etb'] ?? 0);
                $pay_st  = ($fee_val > 0) ? 'Pending' : 'Waived';
                $enr_st  = 'Pending';

                if ($has_pay_status) {
                    $ins = $conn->prepare("INSERT INTO `enrollments` (`{$enr_user_col}`, `program_id`, `status`, `payment_status`) VALUES (?, ?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param("iiss", $uid, $prog_id, $enr_st, $pay_st);
                        if ($ins->execute()) {
                            $_SESSION['tr_prog_msg'] = $is_am ? 'ለስልጠና ፕሮግራሙ ምዝገባዎ በተሳካ ሁኔታ ተልኳል።' : 'Enrollment request submitted successfully.';
                        } else {
                            $_SESSION['tr_prog_err'] = $is_am ? 'ምዝገባውን ማስቀመጥ አልተቻለም።' : 'Failed to submit enrollment.';
                        }
                        $ins->close();
                    }
                } else {
                    $ins = $conn->prepare("INSERT INTO `enrollments` (`{$enr_user_col}`, `program_id`, `status`) VALUES (?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param("iis", $uid, $prog_id, $enr_st);
                        if ($ins->execute()) {
                            $_SESSION['tr_prog_msg'] = $is_am ? 'ለስልጠና ፕሮግራሙ ምዝገባዎ በተሳካ ሁኔታ ተልኳል።' : 'Enrollment request submitted successfully.';
                        } else {
                            $_SESSION['tr_prog_err'] = $is_am ? 'ምዝገባውን ማስቀመጥ አልተቻለም።' : 'Failed to submit enrollment.';
                        }
                        $ins->close();
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $_SESSION['tr_prog_err'] = $e->getMessage();
    }

    header("Location: " . $self_file . "#prog-row-" . $prog_id);
    exit;
}

$programs = [];
$enrolled_map = [];

try {
    if ($uid > 0) {
        $pay_select = $has_pay_status ? ", `payment_status`" : "";
        $stmt_en = $conn->prepare("SELECT `program_id`, `status`{$pay_select} FROM `enrollments` WHERE `{$enr_user_col}` = ?");
        if ($stmt_en) {
            $stmt_en->bind_param("i", $uid);
            $stmt_en->execute();
            $ren = $stmt_en->get_result();
            if ($ren) {
                while ($er = $ren->fetch_assoc()) {
                    $enrolled_map[(int)$er['program_id']] = $er;
                }
            }
            $stmt_en->close();
        }
    }

    if ($prog_trn_col !== null) {
        $sql_p = "
            SELECT p.id, p.code, p.title_en, p.title_am, p.category, p.duration_days, p.fee_etb, p.status,
                   u.name AS trainer_name
            FROM `training_programs` p
            LEFT JOIN `users` u ON p.`{$prog_trn_col}` = u.id
            ORDER BY p.id DESC
        ";
    } else {
        $sql_p = "
            SELECT p.id, p.code, p.title_en, p.title_am, p.category, p.duration_days, p.fee_etb, p.status,
                   NULL AS trainer_name
            FROM `training_programs` p
            ORDER BY p.id DESC
        ";
    }

    $rp = $conn->query($sql_p);
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
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
            <h1><?php echo htmlspecialchars($lang['available_programs'] ?? ($is_am ? 'ክፍት የስልጠና ፕሮግራሞች' : 'Available Programs')); ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ፕሮግራሞችን መመልከት እና ለምዝገባ ማመልከት' : 'Browse training programs and submit enrollment requests'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="available-programs-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የስልጠና ፕሮግራሞች ዝርዝር' : 'Training Programs'; ?> (<?php echo count($programs); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ኮድ' : 'Code'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'አሰልጣኝ' : 'Trainer'; ?></th>
                        <th><?php echo $is_am ? 'ቀናት / ክፍያ' : 'Days / Fee'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ምዝገባ' : 'Enrollment'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($programs)): ?>
                        <?php foreach ($programs as $p):
                            $pid = (int)$p['id'];
                            $ptitle = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : ($p['title_en'] ?? '');
                            $is_enr = isset($enrolled_map[$pid]);
                        ?>
                        <tr id="prog-row-<?php echo $pid; ?>">
                            <td><strong><?php echo htmlspecialchars((string)($p['code'] ?? '')); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)$ptitle); ?></td>
                            <td><?php echo htmlspecialchars((string)($p['trainer_name'] ?? ($is_am ? 'አልተመደበም' : 'Unassigned'))); ?></td>
                            <td>
                                <?php echo (int)($p['duration_days'] ?? 0); ?> <?php echo $is_am ? 'ቀናት' : 'd'; ?> •
                                <?php echo ((float)($p['fee_etb'] ?? 0) > 0) ? number_format((float)$p['fee_etb'], 2) . ' ETB' : ($is_am ? 'ነጻ' : 'Free'); ?>
                            </td>
                            <td><?php echo status_badge((string)($p['status'] ?? 'Open')); ?></td>
                            <td style="white-space:nowrap;">
                                <?php if ($is_enr): ?>
                                    <?php echo status_badge((string)($enrolled_map[$pid]['status'] ?? 'Pending')); ?>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="enroll_program">
                                        <input type="hidden" name="program_id" value="<?php echo $pid; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'ተመዝገብ' : 'Enroll'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ፕሮግራም አልተገኘም።' : 'No programs available.')); ?></td></tr>
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