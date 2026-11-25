<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['curriculum_developer'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['curr_msg'] ?? '';
$err = $_SESSION['curr_err'] ?? '';
unset($_SESSION['curr_msg'], $_SESSION['curr_err']);

$allowed_curr_status = ['Draft', 'In Review', 'Approved'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS curriculum_modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_id INT NULL,
            module_title VARCHAR(180) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'Draft',
            syllabus_notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_module') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['module_title'] ?? ''))), ENT_QUOTES);
        $st_val  = trim((string)($_POST['status'] ?? 'Draft'));
        if (!in_array($st_val, $allowed_curr_status, true)) {
            $st_val = 'Draft';
        }
        $notes = htmlspecialchars_decode(clean_input(trim((string)($_POST['syllabus_notes'] ?? ''))), ENT_QUOTES);

        if ($title === '') {
            $_SESSION['curr_err'] = $is_am ? 'እባክዎ የካሪኩለም/ሞጁል ርዕስ ያስገቡ።' : 'Please enter a module title.';
        } else {
            try {
                $pid_val = ($prog_id > 0) ? $prog_id : null;
                $stmt = $conn->prepare("INSERT INTO curriculum_modules (program_id, module_title, status, syllabus_notes, created_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isssi", $pid_val, $title, $st_val, $notes, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['curr_msg'] = $is_am ? 'የካሪኩለም ሞጁል ተቀምጧል።' : 'Curriculum module saved.';
                    } else {
                        $_SESSION['curr_err'] = $is_am ? 'ሞጁሉን ማስቀመጥ አልተቻለም።' : 'Failed to save module.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['curr_err'] = $e->getMessage();
            }
        }
        header("Location: index.php#add-module-panel");
        exit;
    } elseif ($act === 'update_module_status') {
        $mod_id = (int)($_POST['module_id'] ?? 0);
        $st_val = trim((string)($_POST['status'] ?? 'Draft'));
        if (!in_array($st_val, $allowed_curr_status, true)) {
            $st_val = 'Draft';
        }

        if ($mod_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE curriculum_modules SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $st_val, $mod_id);
                    if ($stmt->execute()) {
                        $_SESSION['curr_msg'] = $is_am ? 'የሞጁል ሁኔታ ተዘምኗል።' : 'Module status updated.';
                    } else {
                        $_SESSION['curr_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update module status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['curr_err'] = $e->getMessage();
            }
        }
        $anchor = ($mod_id > 0) ? '#curr-row-' . $mod_id : '#curriculum-list-panel';
        header("Location: index.php" . $anchor);
        exit;
    }
}

$programs = [];
$modules  = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am FROM training_programs ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $rm = $conn->query("
        SELECT m.id, m.module_title, m.status, m.syllabus_notes, m.created_at, p.code, p.title_en, p.title_am
        FROM curriculum_modules m
        LEFT JOIN training_programs p ON m.program_id = p.id
        ORDER BY m.id DESC
    ");
    if ($rm) {
        while ($row = $rm->fetch_assoc()) {
            $modules[] = $row;
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
            <h1><?php echo $is_am ? 'የካሪኩለም ዳሽቦርድ' : 'Curriculum Developer Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ሲላበስ እና ሞጁሎች ዝግጅት፣ ግምገማ እና ማጽደቅ' : 'Develop, review, and manage training modules and syllabi'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="add-module-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የካሪኩለም ሞጁል ጨምር' : 'Add Curriculum Module'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_module">
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
                <label><?php echo $is_am ? 'የሞጁል ርዕስ *' : 'Module Title *'; ?></label>
                <input type="text" name="module_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_curr_status as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የሲላበስ ማስታወሻ' : 'Syllabus Notes'; ?></label>
                <input type="text" name="syllabus_notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-journal-plus"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Module'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="curriculum-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የካሪኩለም ሞጁሎች ዝርዝር' : 'Curriculum Modules'; ?> (<?php echo count($modules); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ሞጁል' : 'Module'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($modules)): ?>
                        <?php foreach ($modules as $m):
                            $mid = (int)$m['id'];
                            $ptitle = ($is_am && !empty($m['title_am'])) ? $m['title_am'] : ($m['title_en'] ?? '');
                        ?>
                        <tr id="curr-row-<?php echo $mid; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)$m['module_title']); ?></strong>
                                <?php if (!empty($m['syllabus_notes'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$m['syllabus_notes']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)($m['code'] ? ($m['code'] . ' — ' . $ptitle) : ($is_am ? 'አጠቃላይ' : 'General'))); ?></td>
                            <td><?php echo status_badge((string)($m['status'] ?? 'Draft')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_module_status">
                                    <input type="hidden" name="module_id" value="<?php echo $mid; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_curr_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($m['status'] ?? 'Draft') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ሞጁል አልተገኘም።' : 'No curriculum modules found.')); ?></td></tr>
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