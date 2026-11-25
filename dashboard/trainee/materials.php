<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainer'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['trn_mat_msg'] ?? '';
$err   = $_SESSION['trn_mat_err'] ?? '';
unset($_SESSION['trn_mat_msg'], $_SESSION['trn_mat_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'materials.php'));

/**
 * Ensure `training_materials` table exists and check optional columns
 * so CRUD operations never fail on a fresh database.
 */
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `training_materials` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `program_id` INT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `file_url` VARCHAR(500) NULL,
            `material_type` VARCHAR(50) NOT NULL DEFAULT 'Document',
            `uploaded_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mat_cols = [];
    $r_mat = $conn->query("SHOW COLUMNS FROM `training_materials`");
    if ($r_mat) {
        while ($c = $r_mat->fetch_assoc()) {
            $mat_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (!in_array('program_id', $mat_cols, true)) {
        $conn->query("ALTER TABLE `training_materials` ADD COLUMN `program_id` INT NULL");
    }
    if (!in_array('description', $mat_cols, true)) {
        $conn->query("ALTER TABLE `training_materials` ADD COLUMN `description` TEXT NULL");
    }
    if (!in_array('file_url', $mat_cols, true)) {
        $conn->query("ALTER TABLE `training_materials` ADD COLUMN `file_url` VARCHAR(500) NULL");
    }
    if (!in_array('material_type', $mat_cols, true)) {
        $conn->query("ALTER TABLE `training_materials` ADD COLUMN `material_type` VARCHAR(50) NOT NULL DEFAULT 'Document'");
    }
    if (!in_array('uploaded_by', $mat_cols, true)) {
        $conn->query("ALTER TABLE `training_materials` ADD COLUMN `uploaded_by` INT NULL");
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

$allowed_types = ['Document', 'Slides', 'Handout', 'Video Link', 'Assignment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_material') {
        $prog_id  = (int)($_POST['program_id'] ?? 0);
        $title    = htmlspecialchars_decode(clean_input(trim((string)($_POST['title'] ?? ''))), ENT_QUOTES);
        $desc     = htmlspecialchars_decode(clean_input(trim((string)($_POST['description'] ?? ''))), ENT_QUOTES);
        $file_url = htmlspecialchars_decode(clean_input(trim((string)($_POST['file_url'] ?? ''))), ENT_QUOTES);
        $m_type   = trim((string)($_POST['material_type'] ?? 'Document'));
        if (!in_array($m_type, $allowed_types, true)) {
            $m_type = 'Document';
        }

        $redirect_anchor = '#add-material-panel';

        if ($title === '') {
            $_SESSION['trn_mat_err'] = $is_am ? 'እባክዎ የቁሳቁስ ርዕስ ያስገቡ።' : 'Please enter a material title.';
        } else {
            try {
                $pid_val = ($prog_id > 0) ? $prog_id : null;
                $uid_val = ($uid > 0) ? $uid : null;

                $stmt = $conn->prepare("
                    INSERT INTO `training_materials` (`program_id`, `title`, `description`, `file_url`, `material_type`, `uploaded_by`)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param("issssi", $pid_val, $title, $desc, $file_url, $m_type, $uid_val);
                    if ($stmt->execute()) {
                        $new_id = (int)$conn->insert_id;
                        $redirect_anchor = ($new_id > 0) ? ('#mat-row-' . $new_id) : '#materials-list-panel';
                        $_SESSION['trn_mat_msg'] = $is_am ? 'የትምህርት ቁሳቁሱ ተቀምጧል።' : 'Course material added successfully.';
                    } else {
                        $_SESSION['trn_mat_err'] = $is_am ? 'ቁሳቁሱን ማስቀመጥ አልተቻለም።' : 'Failed to save material.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['trn_mat_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . $redirect_anchor);
        exit;
    } elseif ($act === 'delete_material') {
        $mat_id = (int)($_POST['material_id'] ?? 0);
        if ($mat_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM `training_materials` WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $mat_id);
                    if ($stmt->execute()) {
                        $_SESSION['trn_mat_msg'] = $is_am ? 'ቁሳቁሱ ተሰርዟል።' : 'Course material deleted.';
                    } else {
                        $_SESSION['trn_mat_err'] = $is_am ? 'ቁሳቁሱን መሰረዝ አልተቻለም።' : 'Failed to delete material.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['trn_mat_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . "#materials-list-panel");
        exit;
    }
}

$programs  = [];
$materials = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am FROM `training_programs` ORDER BY id ASC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $rm = $conn->query("
        SELECT tm.*, p.code AS program_code, p.title_en, p.title_am
        FROM `training_materials` tm
        LEFT JOIN `training_programs` p ON tm.program_id = p.id
        ORDER BY tm.id DESC
    ");
    if ($rm) {
        while ($row = $rm->fetch_assoc()) {
            $materials[] = $row;
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
            <h1><?php echo $is_am ? 'የስልጠና ቁሳቁሶች (Course Materials)' : 'Course Materials'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ማስታወሻዎችን፣ ሰነዶችን እና መርጃ ሊንኮችን ማከል እና ማስተዳደር' : 'Upload or link training handouts, notes, and learning resources'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="add-material-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የትምህርት ቁሳቁስ ጨምር' : 'Add Course Material'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_material">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></label>
                <select name="program_id" class="form-control">
                    <option value="0"><?php echo $is_am ? 'አጠቃላይ (All)' : 'General'; ?></option>
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
                <label><?php echo $is_am ? 'ርዕስ *' : 'Title *'; ?></label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'አይነት' : 'Type'; ?></label>
                <select name="material_type" class="form-control">
                    <?php foreach ($allowed_types as $tp): ?>
                        <option value="<?php echo htmlspecialchars($tp); ?>"><?php echo htmlspecialchars($tp); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የፋይል / የድረ-ገጽ ሊንክ' : 'File / Resource URL'; ?></label>
                <input type="text" name="file_url" class="form-control" placeholder="https://...">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'መግለጫ' : 'Description'; ?></label>
                <input type="text" name="description" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-plus-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Material'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="materials-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የተመዘገቡ የትምህርት ቁሳቁሶች' : 'Course Materials List'; ?> (<?php echo count($materials); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ርዕስ / መግለጫ' : 'Title & Description'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'አይነት / ሊንክ' : 'Type & Link'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Action'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($materials)): ?>
                        <?php foreach ($materials as $m):
                            $mid    = (int)$m['id'];
                            $ptitle = ($is_am && !empty($m['title_am'])) ? $m['title_am'] : ($m['title_en'] ?? '');
                            $furl   = trim((string)($m['file_url'] ?? ''));
                        ?>
                        <tr id="mat-row-<?php echo $mid; ?>">
                            <td><?php echo $mid; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($m['title'] ?? '')); ?></strong>
                                <?php if (!empty($m['description'])): ?>
                                    <div style="font-size:0.8rem; color:#64748b;"><?php echo htmlspecialchars((string)$m['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $prog_label = trim((string)(($m['program_code'] ?? '') . ($ptitle !== '' ? ' — ' . $ptitle : '')));
                                echo htmlspecialchars($prog_label !== '' ? $prog_label : ($is_am ? 'አጠቃላይ' : 'General'));
                                ?>
                            </td>
                            <td>
                                <span class="badge"><?php echo htmlspecialchars((string)($m['material_type'] ?? 'Document')); ?></span>
                                <?php if ($furl !== ''): ?>
                                    <div style="margin-top:0.25rem;">
                                        <a href="<?php echo htmlspecialchars($furl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">
                                            <i class="bi bi-box-arrow-up-right"></i> <?php echo $is_am ? 'ክፈት' : 'Open'; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ቁሳቁስ መሰረዝ ይፈልጋሉ?' : 'Delete this material?'; ?>');">
                                    <input type="hidden" name="action" value="delete_material">
                                    <input type="hidden" name="material_id" value="<?php echo $mid; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የትምህርት ቁሳቁስ አልተገኘም።' : 'No course materials found.')); ?></td></tr>
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