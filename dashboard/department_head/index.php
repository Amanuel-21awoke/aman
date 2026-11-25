<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['department_head'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$view  = trim((string)($_GET['view'] ?? 'overview'));
$only_programs = ($view === 'programs');

$msg = $_SESSION['dept_msg'] ?? '';
$err = $_SESSION['dept_err'] ?? '';
unset($_SESSION['dept_msg'], $_SESSION['dept_err']);

$allowed_prog_status = ['Open', 'Ongoing', 'Completed', 'Closed'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS dept_head_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_id INT NULL,
            note_title VARCHAR(180) NOT NULL,
            note_body TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $ret_q = $only_programs ? '?view=programs' : '';

    if ($act === 'update_program_status') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $st_val  = trim((string)($_POST['status'] ?? 'Open'));
        if (!in_array($st_val, $allowed_prog_status, true)) {
            $st_val = 'Open';
        }

        if ($prog_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE training_programs SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $st_val, $prog_id);
                    if ($stmt->execute()) {
                        $_SESSION['dept_msg'] = $is_am ? 'የፕሮግራም ሁኔታ ተዘምኗል።' : 'Program status updated.';
                    } else {
                        $_SESSION['dept_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update program status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['dept_err'] = $e->getMessage();
            }
        }
        $anchor = ($prog_id > 0) ? '#dept-prog-row-' . $prog_id : '#dept-programs-panel';
        header("Location: index.php" . $ret_q . $anchor);
        exit;
    } elseif ($act === 'save_dept_note') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['note_title'] ?? ''))), ENT_QUOTES);
        $body    = htmlspecialchars_decode(clean_input(trim((string)($_POST['note_body'] ?? ''))), ENT_QUOTES);

        if ($title === '') {
            $_SESSION['dept_err'] = $is_am ? 'እባክዎ የማስታወሻ ርዕስ ያስገቡ።' : 'Please enter a note title.';
        } else {
            try {
                $pid_val = ($prog_id > 0) ? $prog_id : null;
                $stmt = $conn->prepare("INSERT INTO dept_head_notes (program_id, note_title, note_body, created_by) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("issi", $pid_val, $title, $body, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['dept_msg'] = $is_am ? 'የክፍል ኃላፊ ማስታወሻ ተቀምጧል።' : 'Department note saved.';
                    } else {
                        $_SESSION['dept_err'] = $is_am ? 'ማስታወሻውን ማስቀመጥ አልተቻለም።' : 'Failed to save note.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['dept_err'] = $e->getMessage();
            }
        }
        header("Location: index.php" . $ret_q . "#dept-notes-panel");
        exit;
    }
}

$programs   = [];
$dept_notes = [];
$prog_cnt   = 0;
$enr_cnt    = 0;

try {
    if (!$only_programs) {
        $r1 = $conn->query("SELECT COUNT(*) AS c FROM training_programs");
        if ($r1) { $prog_cnt = (int)($r1->fetch_assoc()['c'] ?? 0); }

        $r2 = $conn->query("SELECT COUNT(*) AS c FROM enrollments");
        if ($r2) { $enr_cnt = (int)($r2->fetch_assoc()['c'] ?? 0); }
    }

    $rp = $conn->query("
        SELECT p.id, p.code, p.title_en, p.title_am, p.category, p.status, u.name AS trainer_name
        FROM training_programs p
        LEFT JOIN users u ON p.trainer_id = u.id
        ORDER BY p.id DESC
    ");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $rn = $conn->query("SELECT id, note_title, note_body, created_at FROM dept_head_notes ORDER BY id DESC LIMIT 15");
    if ($rn) {
        while ($n = $rn->fetch_assoc()) {
            $dept_notes[] = $n;
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
                <?php echo $only_programs
                    ? ($is_am ? 'የዲፓርትመንት ፕሮግራሞች' : 'Department Programs')
                    : ($is_am ? 'የክፍል ኃላፊ ዳሽቦርድ' : 'Department Head Dashboard'); ?>
            </h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የዲፓርትመንት ፕሮግራሞች ሁኔታ እና የክፍል ማስታወሻዎች' : 'Department program supervision and operational notes'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <?php if (!$only_programs): ?>
    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ፕሮግራሞች' : 'Programs'; ?></p><h2><?php echo $prog_cnt; ?></h2></div>
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
            <div><p><?php echo $is_am ? 'ምዝገባዎች' : 'Enrollments'; ?></p><h2><?php echo $enr_cnt; ?></h2></div>
            <i class="bi bi-clipboard-check-fill"></i>
        </div>
    </div>
    <?php endif; ?>

    <div class="card-box" id="dept-programs-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የዲፓርትመንት ፕሮግራሞች' : 'Department Programs'; ?> (<?php echo count($programs); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ኮድ' : 'Code'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($programs)): ?>
                        <?php foreach ($programs as $p):
                            $pid = (int)$p['id'];
                            $ptitle = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : $p['title_en'];
                        ?>
                        <tr id="dept-prog-row-<?php echo $pid; ?>">
                            <td><strong><?php echo htmlspecialchars((string)$p['code']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)$ptitle); ?></td>
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

    <div class="card-box" id="dept-notes-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የክፍል ኃላፊ ማስታወሻዎች' : 'Department Notes'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end; margin-bottom:1rem;">
            <input type="hidden" name="action" value="save_dept_note">
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
                <label><?php echo $is_am ? 'ርዕስ *' : 'Title *'; ?></label>
                <input type="text" name="note_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ዝርዝር' : 'Notes'; ?></label>
                <input type="text" name="note_body" class="form-control">
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
                    <?php if (!empty($dept_notes)): ?>
                        <?php foreach ($dept_notes as $n): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string)$n['note_title']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)($n['note_body'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($n['created_at'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ማስታወሻ አልተገኘም።' : 'No notes found.')); ?></td></tr>
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