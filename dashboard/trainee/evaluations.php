<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainee'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['tr_eval_msg'] ?? '';
$err   = $_SESSION['tr_eval_err'] ?? '';
unset($_SESSION['tr_eval_msg'], $_SESSION['tr_eval_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'evaluations.php'));

/**
 * Ensure `evaluations` table exists and detect/ensure trainee foreign-key column
 * (`trainee_id`, `user_id`, or `student_id`) so queries never fail with `Unknown column`.
 */
$eval_user_col = 'trainee_id';
$has_prog_col  = false;

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `evaluations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `trainee_id` INT NOT NULL,
            `program_id` INT NULL,
            `rating` INT NOT NULL DEFAULT 5,
            `comments` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ev_cols = [];
    $r_ev = $conn->query("SHOW COLUMNS FROM `evaluations`");
    if ($r_ev) {
        while ($c = $r_ev->fetch_assoc()) {
            $ev_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (in_array('trainee_id', $ev_cols, true)) {
        $eval_user_col = 'trainee_id';
    } elseif (in_array('user_id', $ev_cols, true)) {
        $eval_user_col = 'user_id';
    } elseif (in_array('student_id', $ev_cols, true)) {
        $eval_user_col = 'student_id';
    } else {
        $conn->query("ALTER TABLE `evaluations` ADD COLUMN `trainee_id` INT NOT NULL DEFAULT 0");
        $eval_user_col = 'trainee_id';
    }

    if (!in_array('program_id', $ev_cols, true)) {
        $conn->query("ALTER TABLE `evaluations` ADD COLUMN `program_id` INT NULL");
    }
    $has_prog_col = true;
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_evaluation') {
    $prog_id  = (int)($_POST['program_id'] ?? 0);
    $rating   = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comments = htmlspecialchars_decode(clean_input(trim((string)($_POST['comments'] ?? ''))), ENT_QUOTES);

    $redirect_anchor = '#submit-evaluation-panel';

    if ($uid <= 0) {
        $_SESSION['tr_eval_err'] = $is_am ? 'የተጠቃሚ መለያ አልተገኘም፤ እባክዎ እንደገና ይግቡ።' : 'User session not found. Please log in again.';
    } elseif ($comments === '') {
        $_SESSION['tr_eval_err'] = $is_am ? 'እባክዎ የግምገማ አስተያየት ያስገቡ።' : 'Please enter your evaluation feedback.';
    } else {
        try {
            $pid_val = ($prog_id > 0) ? $prog_id : null;
            $stmt = $conn->prepare("
                INSERT INTO `evaluations` (`{$eval_user_col}`, `program_id`, `rating`, `comments`)
                VALUES (?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param("iiis", $uid, $pid_val, $rating, $comments);
                if ($stmt->execute()) {
                    $new_id = (int)$conn->insert_id;
                    $redirect_anchor = ($new_id > 0) ? ('#eval-row-' . $new_id) : '#evaluations-list-panel';
                    $_SESSION['tr_eval_msg'] = $is_am ? 'ግምገማዎ ተቀምጧል።' : 'Evaluation submitted successfully.';
                } else {
                    $_SESSION['tr_eval_err'] = $is_am ? 'ግምገማውን ማስቀመጥ አልተቻለም።' : 'Failed to save evaluation.';
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $_SESSION['tr_eval_err'] = $e->getMessage();
        }
    }

    header("Location: " . $self_file . $redirect_anchor);
    exit;
}

$programs    = [];
$evaluations = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am FROM `training_programs` ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    if ($uid > 0) {
        $prog_join = $has_prog_col ? "LEFT JOIN `training_programs` p ON ev.program_id = p.id" : "";
        $prog_cols = $has_prog_col ? ", p.code AS program_code, p.title_en, p.title_am" : ", NULL AS program_code, NULL AS title_en, NULL AS title_am";

        $stmt = $conn->prepare("
            SELECT ev.*{$prog_cols}
            FROM `evaluations` ev
            {$prog_join}
            WHERE ev.`{$eval_user_col}` = ?
            ORDER BY ev.id DESC
        ");
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $evaluations[] = $row;
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
            <h1><?php echo $is_am ? 'ግምገማዎች (Evaluations)' : 'My Evaluations'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ግምገማ አስተያየቶችን ማስገባት እና የቀድሞ ግምገማዎችን መመልከት' : 'Submit training evaluations and view your submitted feedback'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="submit-evaluation-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ ግምገማ አስገባ' : 'Submit Evaluation'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="submit_evaluation">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></label>
                <select name="program_id" class="form-control">
                    <option value="0"><?php echo $is_am ? 'አጠቃላይ' : 'General'; ?></option>
                    <?php foreach ($programs as $p):
                        $ptitle = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : ($p['title_en'] ?? '');
                    ?>
                        <option value="<?php echo (int)$p['id']; ?>">
                            <?php echo htmlspecialchars((string)(($p['code'] ?? '') . ' — ' . $ptitle)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ደረጃ (1 - 5)' : 'Rating (1 - 5)'; ?></label>
                <select name="rating" class="form-control">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> / 5</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0; grid-column:span 2;">
                <label><?php echo $is_am ? 'አስተያየት *' : 'Comments *'; ?></label>
                <input type="text" name="comments" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-send"></i> <?php echo $is_am ? 'ላክ / አስቀምጥ' : 'Submit'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="evaluations-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የገቡ ግምገማዎች' : 'Submitted Evaluations'; ?> (<?php echo count($evaluations); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ደረጃ' : 'Rating'; ?></th>
                        <th><?php echo $is_am ? 'አስተያየት' : 'Comments'; ?></th>
                        <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($evaluations)): ?>
                        <?php foreach ($evaluations as $ev):
                            $ev_id  = (int)$ev['id'];
                            $ptitle = ($is_am && !empty($ev['title_am'])) ? $ev['title_am'] : ($ev['title_en'] ?? '');
                            $plabel = trim((string)(($ev['program_code'] ?? '') . ($ptitle !== '' ? ' — ' . $ptitle : '')));
                        ?>
                        <tr id="eval-row-<?php echo $ev_id; ?>">
                            <td><?php echo $ev_id; ?></td>
                            <td><?php echo htmlspecialchars($plabel !== '' ? $plabel : ($is_am ? 'አጠቃላይ' : 'General')); ?></td>
                            <td><strong><?php echo (int)($ev['rating'] ?? 0); ?> / 5</strong></td>
                            <td><?php echo htmlspecialchars((string)($ev['comments'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($ev['created_at'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ግምገማ አልተገኘም።' : 'No evaluations found.')); ?></td></tr>
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