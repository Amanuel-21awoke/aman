<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['qa_evaluator'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['qa_msg'] ?? '';
$err = $_SESSION['qa_err'] ?? '';
unset($_SESSION['qa_msg'], $_SESSION['qa_err']);

$allowed_qa_status = ['Passed', 'Action Required', 'Pending'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS qa_evaluations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_id INT NULL,
            eval_title VARCHAR(180) NOT NULL,
            score_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(40) NOT NULL DEFAULT 'Pending',
            remarks TEXT NULL,
            evaluated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_evaluation') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['eval_title'] ?? ''))), ENT_QUOTES);
        $score   = max(0, min(100, (float)($_POST['score_pct'] ?? 0)));
        $st_val  = trim((string)($_POST['status'] ?? 'Pending'));
        if (!in_array($st_val, $allowed_qa_status, true)) {
            $st_val = 'Pending';
        }
        $remarks = htmlspecialchars_decode(clean_input(trim((string)($_POST['remarks'] ?? ''))), ENT_QUOTES);

        if ($title === '') {
            $_SESSION['qa_err'] = $is_am ? 'እባክዎ የግምገማ ርዕስ ያስገቡ።' : 'Please enter an evaluation title.';
        } else {
            try {
                $pid_val = ($prog_id > 0) ? $prog_id : null;
                $stmt = $conn->prepare("INSERT INTO qa_evaluations (program_id, eval_title, score_pct, status, remarks, evaluated_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isdssi", $pid_val, $title, $score, $st_val, $remarks, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['qa_msg'] = $is_am ? 'የጥራት ግምገማ ተቀምጧል።' : 'QA evaluation saved.';
                    } else {
                        $_SESSION['qa_err'] = $is_am ? 'ግምገማውን ማስቀመጥ አልተቻለም።' : 'Failed to save QA evaluation.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['qa_err'] = $e->getMessage();
            }
        }
        header("Location: index.php#add-qa-panel");
        exit;
    } elseif ($act === 'update_qa_status') {
        $ev_id  = (int)($_POST['evaluation_id'] ?? 0);
        $st_val = trim((string)($_POST['status'] ?? 'Pending'));
        if (!in_array($st_val, $allowed_qa_status, true)) {
            $st_val = 'Pending';
        }

        if ($ev_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE qa_evaluations SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $st_val, $ev_id);
                    if ($stmt->execute()) {
                        $_SESSION['qa_msg'] = $is_am ? 'የግምገማ ሁኔታ ተዘምኗል።' : 'Evaluation status updated.';
                    } else {
                        $_SESSION['qa_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update evaluation status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['qa_err'] = $e->getMessage();
            }
        }
        $anchor = ($ev_id > 0) ? '#qa-row-' . $ev_id : '#qa-list-panel';
        header("Location: index.php" . $anchor);
        exit;
    }
}

$programs    = [];
$evaluations = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am FROM training_programs ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $re = $conn->query("
        SELECT q.id, q.eval_title, q.score_pct, q.status, q.remarks, q.created_at, p.code, p.title_en, p.title_am
        FROM qa_evaluations q
        LEFT JOIN training_programs p ON q.program_id = p.id
        ORDER BY q.id DESC
    ");
    if ($re) {
        while ($row = $re->fetch_assoc()) {
            $evaluations[] = $row;
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
            <h1><?php echo $is_am ? 'የጥራት ገምጋሚ (QA) ዳሽቦርድ' : 'QA Evaluator Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ጥራት ግምገማ፣ የውጤት መቶኛ እና የማሻሻያ ክትትል' : 'Conduct quality assurance evaluations, score audits, and follow-ups'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ግምገማዎች' : 'QA Evaluations'; ?></p><h2><?php echo count($evaluations); ?></h2></div>
            <i class="bi bi-clipboard2-check-fill"></i>
        </div>
    </div>

    <div class="card-box" id="add-qa-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የጥራት ግምገማ መዝግብ' : 'Record QA Evaluation'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_evaluation">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></label>
                <select name="program_id" class="form-control">
                    <option value="0"><?php echo $is_am ? '-- አጠቃላይ --' : '-- General --'; ?></option>
                    <?php foreach ($programs as $p):
                        $ptitle = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : $p['title_en'];
                    ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code'] . ' — ' . $ptitle); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የግምገማ ርዕስ *' : 'Evaluation Title *'; ?></label>
                <input type="text" name="eval_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ውጤት (%)' : 'Score (%)'; ?></label>
                <input type="number" name="score_pct" class="form-control" min="0" max="100" step="0.5" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_qa_status as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'አስተያየት' : 'Remarks'; ?></label>
                <input type="text" name="remarks" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Evaluation'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="qa-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የግምገማዎች ዝርዝር' : 'QA Evaluations List'; ?> (<?php echo count($evaluations); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ግምገማ' : 'Evaluation'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም / ውጤት' : 'Program & Score'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($evaluations)): ?>
                        <?php foreach ($evaluations as $ev):
                            $ev_id = (int)$ev['id'];
                            $ptitle = ($is_am && !empty($ev['title_am'])) ? $ev['title_am'] : ($ev['title_en'] ?? '');
                        ?>
                        <tr id="qa-row-<?php echo $ev_id; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)$ev['eval_title']); ?></strong>
                                <?php if (!empty($ev['remarks'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$ev['remarks']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string)($ev['code'] ? ($ev['code'] . ' — ' . $ptitle) : ($is_am ? 'አጠቃላይ' : 'General'))); ?>
                                <div style="font-size:0.78rem; color:#64748b;"><?php echo number_format((float)($ev['score_pct'] ?? 0), 1); ?>%</div>
                            </td>
                            <td><?php echo status_badge((string)($ev['status'] ?? 'Pending')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_qa_status">
                                    <input type="hidden" name="evaluation_id" value="<?php echo $ev_id; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_qa_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($ev['status'] ?? 'Pending') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የግምገማ መዝገብ አልተገኘም።' : 'No QA evaluations found.')); ?></td></tr>
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