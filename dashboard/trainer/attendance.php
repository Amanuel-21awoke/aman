<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainer'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['trn_att_msg'] ?? '';
$err = $_SESSION['trn_att_err'] ?? '';
unset($_SESSION['trn_att_msg'], $_SESSION['trn_att_err']);

/**
 * Ensure `attendance_pct`, `assessment_score`, and a trainee foreign-key column
 * (`user_id`, `trainee_id`, or `student_id`) exist on `enrollments`.
 */
$enr_user_col = 'trainee_id';

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

    if (!in_array('attendance_pct', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `attendance_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('assessment_score', $found_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `assessment_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_attendance_grade') {
    $enr_id = (int)($_POST['enrollment_id'] ?? 0);
    $att    = max(0.0, min(100.0, (float)($_POST['attendance_pct'] ?? 0)));
    $score  = max(0.0, min(100.0, (float)($_POST['assessment_score'] ?? 0)));

    if ($enr_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE `enrollments` SET `attendance_pct` = ?, `assessment_score` = ? WHERE `id` = ?");
            if ($stmt) {
                $stmt->bind_param("ddi", $att, $score, $enr_id);
                if ($stmt->execute()) {
                    $_SESSION['trn_att_msg'] = $is_am ? 'የመገኘት መቶኛ እና የምዘና ውጤት ተቀምጧል።' : 'Attendance percentage and assessment score saved.';
                } else {
                    $_SESSION['trn_att_err'] = $is_am ? 'መረጃውን ማስቀመጥ አልተቻለም።' : 'Failed to save attendance and score.';
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $_SESSION['trn_att_err'] = $e->getMessage();
        }
    }

    $anchor = ($enr_id > 0) ? '#att-row-' . $enr_id : '#attendance-grading-panel';
    header("Location: " . basename((string)($_SERVER['PHP_SELF'] ?? 'attendance.php')) . $anchor);
    exit;
}

$records = [];
try {
    $sql = "
        SELECT e.id, e.status, e.attendance_pct, e.assessment_score,
               u.name AS trainee_name, u.email AS trainee_email,
               p.code AS program_code, p.title_en, p.title_am
        FROM `enrollments` e
        LEFT JOIN `users` u ON e.`{$enr_user_col}` = u.id
        LEFT JOIN `training_programs` p ON e.program_id = p.id
        ORDER BY e.id DESC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $records[] = $row;
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
            <h1><?php echo $is_am ? 'መገኘት እና ውጤት (Attendance & Grading)' : 'Attendance & Grading'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የሰልጣኞችን የመገኘት መቶኛ እና የምዘና ውጤት ይመዝግቡ' : 'Record trainee attendance percentage and assessment scores'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="attendance-grading-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የሰልጣኞች መገኘት እና ውጤት መዝገብ' : 'Trainee Attendance & Assessment Scores'; ?> (<?php echo count($records); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ሰልጣኝ' : 'Trainee'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'መገኘት (%) / ውጤት (%)' : 'Attendance (%) / Score (%)'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $r):
                            $eid = (int)$r['id'];
                            $ptitle = ($is_am && !empty($r['title_am'])) ? $r['title_am'] : ($r['title_en'] ?? '');
                        ?>
                        <tr id="att-row-<?php echo $eid; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)($r['trainee_name'] ?? '')); ?></strong>
                                <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)($r['trainee_email'] ?? '')); ?></div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($r['program_code'] ?? '')); ?></strong> —
                                <?php echo htmlspecialchars((string)$ptitle); ?>
                            </td>
                            <td><?php echo status_badge((string)($r['status'] ?? 'Pending')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="save_attendance_grade">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                    <input type="number" name="attendance_pct" class="form-control" style="width:82px; padding:0.25rem 0.4rem; font-size:0.82rem;" min="0" max="100" step="0.5" value="<?php echo htmlspecialchars((string)($r['attendance_pct'] ?? 0)); ?>" title="<?php echo $is_am ? 'መገኘት %' : 'Attendance %'; ?>">
                                    <input type="number" name="assessment_score" class="form-control" style="width:82px; padding:0.25rem 0.4rem; font-size:0.82rem;" min="0" max="100" step="0.5" value="<?php echo htmlspecialchars((string)($r['assessment_score'] ?? 0)); ?>" title="<?php echo $is_am ? 'ውጤት %' : 'Score %'; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check2"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የምዝገባ መረጃ አልተገኘም።' : 'No enrollment records found.')); ?></td></tr>
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