<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainer'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['tr_mat_msg'] ?? '';
$err = $_SESSION['tr_mat_err'] ?? '';
unset($_SESSION['tr_mat_msg'], $_SESSION['tr_mat_err']);

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS course_materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            resource_url VARCHAR(255) NULL,
            uploaded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_material') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['title'] ?? ''))), ENT_QUOTES);
        $url_val = trim((string)($_POST['resource_url'] ?? ''));

        if ($prog_id <= 0 || $title === '') {
            $_SESSION['tr_mat_err'] = $is_am ? 'እባክዎ ፕሮግራም እና የግብዓት ርዕስ ያስገቡ።' : 'Please select a program and enter a material title.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO course_materials (program_id, title, resource_url, uploaded_by) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("issi", $prog_id, $title, $url_val, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['tr_mat_msg'] = $is_am ? 'የኮርስ ግብዓት ተቀምጧል።' : 'Course material saved.';
                    } else {
                        $_SESSION['tr_mat_err'] = $is_am ? 'ግብዓቱን ማስቀመጥ አልተቻለም።' : 'Failed to save material.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['tr_mat_err'] = $e->getMessage();
            }
        }
        header("Location: materials.php#add-material-panel");
        exit;
    } elseif ($act === 'delete_material') {
        $mat_id = (int)($_POST['material_id'] ?? 0);
        if ($mat_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM course_materials WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $mat_id);
                    if ($stmt->execute()) {
                        $_SESSION['tr_mat_msg'] = $is_am ? 'ግብዓቱ ተሰርዟል።' : 'Material removed.';
                    } else {
                        $_SESSION['tr_mat_err'] = $is_am ? 'ግብዓቱን መሰረዝ አልተቻለም።' : 'Failed to delete material.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['tr_mat_err'] = $e->getMessage();
            }
        }
        header("Location: materials.php#materials-list-panel");
        exit;
    }
}

$programs  = [];
$materials = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am FROM training_programs ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $rm = $conn->query("
        SELECT m.id, m.title, m.resource_url, m.created_at, p.code, p.title_en, p.title_am
        FROM course_materials m
        LEFT JOIN training_programs p ON m.program_id = p.id
        ORDER BY m.id DESC
    ");
    if ($rm) {
        while ($m = $rm->fetch_assoc()) {
            $materials[] = $m;
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
            <h1><?php echo $is_am ? 'የኮርስ ግብዓቶች' : 'Course Materials'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'ለስልጠና ፕሮግራሞች የትምህርት ሊንኮች እና ግብዓቶች ማጋራት' : 'Add and manage learning resources for training programs'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="add-material-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የኮርስ ግብዓት ጨምር' : 'Add Course Material'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_material">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፕሮግራም *' : 'Program *'; ?></label>
                <select name="program_id" class="form-control" required>
                    <option value=""><?php echo $is_am ? '-- ይምረጡ --' : '-- Select --'; ?></option>
                    <?php foreach ($programs as $p):
                        $pt = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : $p['title_en'];
                    ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code'] . ' — ' . $pt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ *' : 'Title *'; ?></label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሊንክ / URL' : 'Resource URL'; ?></label>
                <input type="text" name="resource_url" class="form-control" placeholder="https://...">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-folder-plus"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Material'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="materials-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የተቀመጡ ግብዓቶች' : 'Saved Materials'; ?> (<?php echo count($materials); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Title'; ?></th>
                        <th><?php echo $is_am ? 'ሊንክ' : 'URL'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($materials)): ?>
                        <?php foreach ($materials as $m):
                            $pt = ($is_am && !empty($m['title_am'])) ? $m['title_am'] : ($m['title_en'] ?? '');
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string)($m['code'] ?? '')); ?></strong> — <?php echo htmlspecialchars((string)$pt); ?></td>
                            <td><?php echo htmlspecialchars((string)$m['title']); ?></td>
                            <td><?php echo htmlspecialchars((string)($m['resource_url'] ?? '-')); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ግብዓት መሰረዝ ይፈልጋሉ?' : 'Delete this material?'; ?>');">
                                    <input type="hidden" name="action" value="delete_material">
                                    <input type="hidden" name="material_id" value="<?php echo (int)$m['id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ግብዓት አልተገኘም።' : 'No materials found.')); ?></td></tr>
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