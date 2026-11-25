<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$selected_prog_id = (int)($_GET['program_id'] ?? 0);
$msg = $_SESSION['admin_prog_msg'] ?? '';
$err = $_SESSION['admin_prog_err'] ?? '';
unset($_SESSION['admin_prog_msg'], $_SESSION['admin_prog_err']);

$allowed_status = ['Open', 'Ongoing', 'Completed', 'Closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $anchor = '#all-programs-panel';

    if ($act === 'add_program') {
        $anchor = '#add-program-panel';
        $code       = strtoupper(htmlspecialchars_decode(clean_input(trim((string)($_POST['code'] ?? ''))), ENT_QUOTES));
        $title_en   = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_en'] ?? ''))), ENT_QUOTES);
        $title_am   = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_am'] ?? ''))), ENT_QUOTES);
        $category   = htmlspecialchars_decode(clean_input(trim((string)($_POST['category'] ?? 'Short-Term Training'))), ENT_QUOTES);
        $trainer_id = (int)($_POST['trainer_id'] ?? 0);
        $duration   = max(1, (int)($_POST['duration_days'] ?? 7));
        $fee_etb    = max(0, (float)($_POST['fee_etb'] ?? 0));
        $status     = $_POST['status'] ?? 'Open';
        if (!in_array($status, $allowed_status, true)) {
            $status = 'Open';
        }
        $desc_en = htmlspecialchars_decode(clean_input(trim((string)($_POST['description_en'] ?? ''))), ENT_QUOTES);
        $desc_am = htmlspecialchars_decode(clean_input(trim((string)($_POST['description_am'] ?? ''))), ENT_QUOTES);

        if ($title_am === '' && $title_en !== '') { $title_am = $title_en; }
        if ($title_en === '' && $title_am !== '') { $title_en = $title_am; }
        if ($desc_am === '' && $desc_en !== '') { $desc_am = $desc_en; }
        if ($desc_en === '' && $desc_am !== '') { $desc_en = $desc_am; }

        if ($code === '' || $title_en === '') {
            $_SESSION['admin_prog_err'] = $is_am ? 'እባክዎ የፕሮግራም ኮድ እና ርዕስ ያስገቡ።' : 'Please enter both program code and title.';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO training_programs
                        (code, title_en, title_am, category, trainer_id, duration_days, fee_etb, status, description_en, description_am)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $tid_val = $trainer_id > 0 ? $trainer_id : null;
                    $stmt->bind_param(
                        "ssssiidsss",
                        $code,
                        $title_en,
                        $title_am,
                        $category,
                        $tid_val,
                        $duration,
                        $fee_etb,
                        $status,
                        $desc_en,
                        $desc_am
                    );
                    if ($stmt->execute()) {
                        $new_id = (int)$conn->insert_id;
                        $_SESSION['admin_prog_msg'] = $is_am ? 'አዲስ ፕሮግራም ተጨምሯል፤ በ Sidebar ዝርዝርም ተካቷል።' : 'Program added and available in the Sidebar dropdown.';
                        header("Location: programs.php?program_id=" . $new_id . "#program-edit-panel");
                        exit;
                    } else {
                        $_SESSION['admin_prog_err'] = $is_am ? 'ፕሮግራሙን ማከል አልተቻለም።' : 'Failed to add training program.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_prog_err'] = $e->getMessage();
            }
        }
        header("Location: programs.php" . $anchor);
        exit;
    } elseif ($act === 'update_program') {
        $prog_id    = (int)($_POST['program_id'] ?? 0);
        $anchor     = ($prog_id > 0) ? '#program-edit-panel' : '#all-programs-panel';
        $code       = strtoupper(htmlspecialchars_decode(clean_input(trim((string)($_POST['code'] ?? ''))), ENT_QUOTES));
        $title_en   = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_en'] ?? ''))), ENT_QUOTES);
        $title_am   = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_am'] ?? ''))), ENT_QUOTES);
        $category   = htmlspecialchars_decode(clean_input(trim((string)($_POST['category'] ?? 'Short-Term Training'))), ENT_QUOTES);
        $trainer_id = (int)($_POST['trainer_id'] ?? 0);
        $duration   = max(1, (int)($_POST['duration_days'] ?? 7));
        $fee_etb    = max(0, (float)($_POST['fee_etb'] ?? 0));
        $status     = $_POST['status'] ?? 'Open';
        if (!in_array($status, $allowed_status, true)) {
            $status = 'Open';
        }
        $desc_en = htmlspecialchars_decode(clean_input(trim((string)($_POST['description_en'] ?? ''))), ENT_QUOTES);
        $desc_am = htmlspecialchars_decode(clean_input(trim((string)($_POST['description_am'] ?? ''))), ENT_QUOTES);

        if ($title_am === '' && $title_en !== '') { $title_am = $title_en; }
        if ($title_en === '' && $title_am !== '') { $title_en = $title_am; }
        if ($desc_am === '' && $desc_en !== '') { $desc_am = $desc_en; }
        if ($desc_en === '' && $desc_am !== '') { $desc_en = $desc_am; }

        if ($prog_id <= 0 || $code === '' || $title_en === '') {
            $_SESSION['admin_prog_err'] = $is_am ? 'እባክዎ ትክክለኛ የፕሮግራም መረጃ ያስገቡ።' : 'Please provide valid program details.';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE training_programs
                    SET code = ?, title_en = ?, title_am = ?, category = ?, trainer_id = ?,
                        duration_days = ?, fee_etb = ?, status = ?, description_en = ?, description_am = ?
                    WHERE id = ?
                ");
                if ($stmt) {
                    $tid_val = $trainer_id > 0 ? $trainer_id : null;
                    $stmt->bind_param(
                        "ssssiidsssi",
                        $code,
                        $title_en,
                        $title_am,
                        $category,
                        $tid_val,
                        $duration,
                        $fee_etb,
                        $status,
                        $desc_en,
                        $desc_am,
                        $prog_id
                    );
                    if ($stmt->execute()) {
                        $_SESSION['admin_prog_msg'] = $is_am ? 'የፕሮግራም ዝርዝር መረጃ በተሳካ ሁኔታ ተዘምኗል።' : 'Program details updated successfully.';
                    } else {
                        $_SESSION['admin_prog_err'] = $is_am ? 'ፕሮግራሙን ማዘመን አልተቻለም።' : 'Failed to update program.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_prog_err'] = $e->getMessage();
            }
        }
        $redir = ($prog_id > 0) ? ("programs.php?program_id=" . $prog_id . "#program-edit-panel") : "programs.php#all-programs-panel";
        header("Location: " . $redir);
        exit;
    } elseif ($act === 'delete_program') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        if ($prog_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM training_programs WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $prog_id);
                    if ($stmt->execute()) {
                        $_SESSION['admin_prog_msg'] = $is_am ? 'ፕሮግራሙ ተሰርዟል።' : 'Program deleted.';
                    } else {
                        $_SESSION['admin_prog_err'] = $is_am ? 'ፕሮግራሙን መሰረዝ አልተቻለም።' : 'Failed to delete program.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_prog_err'] = $e->getMessage();
            }
        }
        header("Location: programs.php#all-programs-panel");
        exit;
    }
}

$programs = [];
$trainers = [];
$editing_program = null;

try {
    $res_tr = $conn->query("SELECT id, name FROM users WHERE role = 'trainer' AND status = 'active' ORDER BY name ASC");
    if ($res_tr) {
        while ($t = $res_tr->fetch_assoc()) {
            $trainers[] = $t;
        }
    }

    $sql_p = "
        SELECT p.id, p.code, p.title_en, p.title_am, p.category, p.trainer_id,
               p.duration_days, p.fee_etb, p.status, p.description_en, p.description_am,
               u.name AS trainer_name
        FROM training_programs p
        LEFT JOIN users u ON p.trainer_id = u.id
        ORDER BY p.id DESC
    ";
    $res_p = $conn->query($sql_p);
    if ($res_p) {
        while ($pr = $res_p->fetch_assoc()) {
            $programs[] = $pr;
            if ($selected_prog_id > 0 && (int)$pr['id'] === $selected_prog_id) {
                $editing_program = $pr;
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
            <h1><?php echo htmlspecialchars($lang['manage_programs'] ?? ($is_am ? 'ፕሮግራሞችን አስተዳድር' : 'Manage Programs')); ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am
                    ? 'በ Sidebar Dropdown ውስጥ ፕሮግራም ሲመረጥ ዝርዝር ማርሚያ ቅጽ እዚህ ይከፈታል'
                    : 'Selecting a program in the Sidebar dropdown opens its dedicated detailed edit form here'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <?php if ($editing_program !== null):
        $ep_id = (int)$editing_program['id'];
        $ep_title_disp = ($is_am && !empty($editing_program['title_am'])) ? $editing_program['title_am'] : $editing_program['title_en'];
    ?>
    <div class="card-box" id="program-edit-panel" style="margin-bottom:1.25rem; border:2px solid var(--primary-color);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem; padding-bottom:0.65rem; border-bottom:1px solid #e2e8f0;">
            <div>
                <h3 style="color:var(--primary-color); margin:0;">
                    <i class="bi bi-pencil-square"></i>
                    <?php echo $is_am ? 'የፕሮግራም ዝርዝር ማርሚያ ቅጽ፦ ' : 'Detailed Edit Form — '; ?>
                    <?php echo htmlspecialchars($editing_program['code'] . ' (' . $ep_title_disp . ')'); ?>
                </h3>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="window.location.href='programs.php#all-programs-panel'">
                <i class="bi bi-x-lg"></i> <?php echo $is_am ? 'ዝጋ / ወደ ሁሉም ተመለስ' : 'Close / All Programs'; ?>
            </button>
        </div>

        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:0.85rem; align-items:end;">
            <input type="hidden" name="action" value="update_program">
            <input type="hidden" name="program_id" value="<?php echo $ep_id; ?>">

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ኮድ *' : 'Program Code *'; ?></label>
                <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($editing_program['code']); ?>" required>
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (English) *' : 'Title (English) *'; ?></label>
                <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($editing_program['title_en']); ?>" required>
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (አማርኛ)' : 'Title (Amharic)'; ?></label>
                <input type="text" name="title_am" class="form-control" value="<?php echo htmlspecialchars((string)($editing_program['title_am'] ?? '')); ?>">
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ምድብ' : 'Category'; ?></label>
                <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars((string)($editing_program['category'] ?? 'Short-Term Training')); ?>">
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'አሰልጣኝ' : 'Assigned Trainer'; ?></label>
                <select name="trainer_id" class="form-control">
                    <option value="0"><?php echo $is_am ? '-- አልተመደበም --' : '-- Unassigned --'; ?></option>
                    <?php foreach ($trainers as $tr): ?>
                        <option value="<?php echo (int)$tr['id']; ?>" <?php echo ((int)($editing_program['trainer_id'] ?? 0) === (int)$tr['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tr['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ቀናት' : 'Duration (Days)'; ?></label>
                <input type="number" name="duration_days" class="form-control" min="1" value="<?php echo (int)($editing_program['duration_days'] ?? 7); ?>">
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ክፍያ (ETB)' : 'Fee (ETB)'; ?></label>
                <input type="number" name="fee_etb" class="form-control" min="0" step="0.01" value="<?php echo (float)($editing_program['fee_etb'] ?? 0); ?>">
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_status as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo (($editing_program['status'] ?? 'Open') === $st) ? 'selected' : ''; ?>>
                            <?php echo $st; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0; grid-column:1 / -1;">
                <label><?php echo $is_am ? 'መግለጫ (English)' : 'Description (English)'; ?></label>
                <textarea name="description_en" class="form-control" rows="2"><?php echo htmlspecialchars((string)($editing_program['description_en'] ?? '')); ?></textarea>
            </div>

            <div class="form-group" style="margin:0; grid-column:1 / -1;">
                <label><?php echo $is_am ? 'መግለጫ (አማርኛ)' : 'Description (Amharic)'; ?></label>
                <textarea name="description_am" class="form-control" rows="2"><?php echo htmlspecialchars((string)($editing_program['description_am'] ?? '')); ?></textarea>
            </div>

            <div class="form-group" style="margin:0; grid-column:1 / -1; display:flex; gap:0.6rem; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'ለውጦችን አስቀምጥ' : 'Save Program Changes'; ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card-box" id="add-program-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <i class="bi bi-plus-circle-fill"></i> <?php echo $is_am ? 'አዲስ የስልጠና ፕሮግራም ጨምር' : 'Add New Training Program'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_program">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ኮድ *' : 'Code *'; ?></label>
                <input type="text" name="code" class="form-control" placeholder="e.g. ST-105" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (English) *' : 'Title (English) *'; ?></label>
                <input type="text" name="title_en" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (አማርኛ)' : 'Title (Amharic)'; ?></label>
                <input type="text" name="title_am" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ምድብ' : 'Category'; ?></label>
                <input type="text" name="category" class="form-control" value="Short-Term Training">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'አሰልጣኝ' : 'Trainer'; ?></label>
                <select name="trainer_id" class="form-control">
                    <option value="0"><?php echo $is_am ? '-- አልተመደበም --' : '-- Unassigned --'; ?></option>
                    <?php foreach ($trainers as $tr): ?>
                        <option value="<?php echo (int)$tr['id']; ?>"><?php echo htmlspecialchars($tr['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ቀናት' : 'Duration (Days)'; ?></label>
                <input type="number" name="duration_days" class="form-control" min="1" value="7">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ክፍያ (ETB)' : 'Fee (ETB)'; ?></label>
                <input type="number" name="fee_etb" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <option value="Open">Open</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                    <option value="Closed">Closed</option>
                </select>
            </div>
            <div class="form-group" style="margin:0; grid-column:1 / -1;">
                <label><?php echo $is_am ? 'መግለጫ (English)' : 'Description (English)'; ?></label>
                <input type="text" name="description_en" class="form-control">
            </div>
            <div class="form-group" style="margin:0; grid-column:1 / -1;">
                <label><?php echo $is_am ? 'መግለጫ (አማርኛ)' : 'Description (Amharic)'; ?></label>
                <input type="text" name="description_am" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'ፕሮግራም ጨምር' : 'Add Program'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="all-programs-panel">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
            <h3 style="color:var(--primary-color); margin:0;">
                <?php echo $is_am ? 'ሁሉም የስልጠና ፕሮግራሞች' : 'All Training Programs'; ?> (<?php echo count($programs); ?>)
            </h3>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ኮድ' : 'Code'; ?></th>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Title'; ?></th>
                        <th><?php echo $is_am ? 'ምድብ' : 'Category'; ?></th>
                        <th><?php echo $is_am ? 'አሰልጣኝ' : 'Trainer'; ?></th>
                        <th><?php echo $is_am ? 'ቀናት / ክፍያ' : 'Days / Fee'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($programs)): ?>
                        <?php foreach ($programs as $p):
                            $pid = (int)$p['id'];
                            $ptitle = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : $p['title_en'];
                            $is_highlighted = ($selected_prog_id === $pid);
                        ?>
                        <tr id="program-row-<?php echo $pid; ?>" style="<?php echo $is_highlighted ? 'background:#fef3c7;' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($p['code']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($ptitle); ?></strong>
                                <?php if (!empty($p['title_am']) && !empty($p['title_en']) && $p['title_am'] !== $p['title_en']): ?>
                                    <div style="font-size:0.78rem; color:#64748b;">
                                        <?php echo htmlspecialchars($is_am ? $p['title_en'] : $p['title_am']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)($p['category'] ?? 'Short-Term Training')); ?></td>
                            <td><?php echo htmlspecialchars((string)($p['trainer_name'] ?? ($is_am ? 'አልተመደበም' : 'Unassigned'))); ?></td>
                            <td>
                                <?php echo (int)($p['duration_days'] ?? 0); ?> <?php echo $is_am ? 'ቀናት' : 'd'; ?> •
                                <?php echo ((float)($p['fee_etb'] ?? 0) > 0) ? number_format((float)$p['fee_etb'], 2) . ' ETB' : ($is_am ? 'ነጻ' : 'Free'); ?>
                            </td>
                            <td><?php echo status_badge((string)($p['status'] ?? 'Open')); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn btn-primary btn-sm" onclick="window.location.href='programs.php?program_id=<?php echo $pid; ?>#program-edit-panel'">
                                    <i class="bi bi-pencil-square"></i> <?php echo $is_am ? 'ዝርዝር አርም' : 'Edit Details'; ?>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ፕሮግራም መሰረዝ ይፈልጋሉ?' : 'Delete this program?'; ?>');">
                                    <input type="hidden" name="action" value="delete_program">
                                    <input type="hidden" name="program_id" value="<?php echo $pid; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ፕሮግራም አልተገኘም።' : 'No programs found.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target) {
            target.scrollIntoView({ behavior: 'auto', block: 'start' });
        }
    }
});
</script>
<?php include '../../includes/footer.php'; ?>