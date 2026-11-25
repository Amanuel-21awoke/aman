<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['system_admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$msg = $_SESSION['adm_prg_msg'] ?? '';
$err = $_SESSION['adm_prg_err'] ?? '';
unset($_SESSION['adm_prg_msg'], $_SESSION['adm_prg_err']);

$allowed_prog_status = ['Open', 'Ongoing', 'Completed', 'Closed'];

$status_filter = trim((string)($_GET['status_filter'] ?? 'ALL'));
if ($status_filter !== 'ALL' && !in_array($status_filter, $allowed_prog_status, true)) {
    $status_filter = 'ALL';
}
$search_q = trim((string)($_GET['q'] ?? ''));

$build_prog_return = static function (string $anchor = '') use ($status_filter, $search_q): string {
    $params = [];
    if ($status_filter !== '' && $status_filter !== 'ALL') {
        $params['status_filter'] = $status_filter;
    }
    if ($search_q !== '') {
        $params['q'] = $search_q;
    }
    $qs = !empty($params) ? ('?' . http_build_query($params)) : '';
    return 'programs.php' . $qs . $anchor;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_program') {
        $code     = htmlspecialchars_decode(clean_input(trim((string)($_POST['code'] ?? ''))), ENT_QUOTES);
        $title_en = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_en'] ?? ''))), ENT_QUOTES);
        $title_am = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_am'] ?? ''))), ENT_QUOTES);
        $category = htmlspecialchars_decode(clean_input(trim((string)($_POST['category'] ?? 'Short-Term'))), ENT_QUOTES);
        $fee_etb  = max(0, (float)($_POST['fee_etb'] ?? 0));
        $status_v = trim((string)($_POST['status'] ?? 'Open'));
        if (!in_array($status_v, $allowed_prog_status, true)) {
            $status_v = 'Open';
        }

        if ($code === '' || $title_en === '') {
            $_SESSION['adm_prg_err'] = $is_am ? 'እባክዎ የፕሮግራም ኮድ እና ርዕስ ያስገቡ።' : 'Please enter program code and title.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO training_programs (code, title_en, title_am, category, fee_etb, status) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssds", $code, $title_en, $title_am, $category, $fee_etb, $status_v);
                    if ($stmt->execute()) {
                        $_SESSION['adm_prg_msg'] = $is_am ? 'ፕሮግራሙ ተመዝግቧል።' : 'Training program added.';
                    } else {
                        $_SESSION['adm_prg_err'] = $is_am ? 'ፕሮግራሙን ማስቀመጥ አልተቻለም።' : 'Failed to add program.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['adm_prg_err'] = $e->getMessage();
            }
        }
        header("Location: " . $build_prog_return('#add-program-panel'));
        exit;
    } elseif ($act === 'update_program_status') {
        $prog_id  = (int)($_POST['program_id'] ?? 0);
        $status_v = trim((string)($_POST['status'] ?? 'Open'));
        if (!in_array($status_v, $allowed_prog_status, true)) {
            $status_v = 'Open';
        }

        if ($prog_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE training_programs SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $status_v, $prog_id);
                    if ($stmt->execute()) {
                        $_SESSION['adm_prg_msg'] = $is_am ? 'የፕሮግራም ሁኔታ ተዘምኗል።' : 'Program status updated.';
                    } else {
                        $_SESSION['adm_prg_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update program status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['adm_prg_err'] = $e->getMessage();
            }
        }
        $anchor = ($prog_id > 0) ? '#prg-row-' . $prog_id : '#programs-list-panel';
        header("Location: " . $build_prog_return($anchor));
        exit;
    } elseif ($act === 'delete_program') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        if ($prog_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM training_programs WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $prog_id);
                    if ($stmt->execute()) {
                        $_SESSION['adm_prg_msg'] = $is_am ? 'ፕሮግራሙ ተሰርዟል።' : 'Program deleted.';
                    } else {
                        $_SESSION['adm_prg_err'] = $is_am ? 'ፕሮግራሙን መሰረዝ አልተቻለም።' : 'Failed to delete program.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['adm_prg_err'] = $e->getMessage();
            }
        }
        header("Location: " . $build_prog_return('#programs-list-panel'));
        exit;
    }
}

$all_programs_count = 0;
$programs_list = [];

try {
    $rc = $conn->query("SELECT COUNT(*) AS c FROM training_programs");
    if ($rc) {
        $all_programs_count = (int)($rc->fetch_assoc()['c'] ?? 0);
    }

    $where_sql = [];
    $types = '';
    $params = [];

    if ($status_filter !== 'ALL') {
        $where_sql[] = "status = ?";
        $types .= 's';
        $params[] = $status_filter;
    }
    if ($search_q !== '') {
        $where_sql[] = "(code LIKE ? OR title_en LIKE ? OR title_am LIKE ? OR category LIKE ?)";
        $types .= 'ssss';
        $like = '%' . $search_q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT id, code, title_en, title_am, category, fee_etb, status FROM training_programs";
    if (!empty($where_sql)) {
        $sql .= " WHERE " . implode(" AND ", $where_sql);
    }
    $sql .= " ORDER BY id ASC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $programs_list[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $programs_list[] = $row;
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
            <h1><?php echo $is_am ? 'ሁሉንም የስልጠና ፕሮግራሞች አስተዳድር' : 'Manage All Training Programs'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'በስርዓቱ ውስጥ ያሉ ሁሉንም ፕሮግራሞች ማየት፣ አዲስ መጨመር፣ ሁኔታ ማዘመን እና መሰረዝ' : 'View all training programs, add new ones, update statuses, and manage records'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ፕሮግራሞች' : 'Total Programs'; ?></p><h2><?php echo $all_programs_count; ?></h2></div>
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'የሚታዩ ፕሮግራሞች' : 'Showing Programs'; ?></p><h2><?php echo count($programs_list); ?></h2></div>
            <i class="bi bi-collection-fill"></i>
        </div>
    </div>

    <div class="card-box" id="add-program-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ ፕሮግራም ጨምር' : 'Add Training Program'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_program">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ኮድ *' : 'Code *'; ?></label>
                <input type="text" name="code" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (EN) *' : 'Title (EN) *'; ?></label>
                <input type="text" name="title_en" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (AM)' : 'Title (AM)'; ?></label>
                <input type="text" name="title_am" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ክፍያ (ETB)' : 'Fee (ETB)'; ?></label>
                <input type="number" name="fee_etb" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_prog_status as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-plus-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Program'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="programs-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'ሁሉም የስልጠና ፕሮግራሞች' : 'All Training Programs'; ?> (<?php echo count($programs_list); ?> / <?php echo $all_programs_count; ?>)
        </h3>

        <form method="GET" action="programs.php#programs-list-panel" style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:end; margin-bottom:1rem;">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ አጣራ' : 'Filter by Status'; ?></label>
                <select name="status_filter" class="form-control" style="min-width:165px;">
                    <option value="ALL" <?php echo ($status_filter === 'ALL') ? 'selected' : ''; ?>><?php echo $is_am ? 'ሁሉም ሁኔታዎች (All)' : 'All Statuses'; ?></option>
                    <?php foreach ($allowed_prog_status as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($status_filter === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፈልግ (ኮድ / ርዕስ)' : 'Search (Code / Title)'; ?></label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo $is_am ? 'ኮድ ወይም ርዕስ...' : 'Code or title...'; ?>">
            </div>
            <div class="form-group" style="margin:0; display:flex; gap:0.4rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> <?php echo $is_am ? 'አሳይ' : 'Show'; ?>
                </button>
                <a href="programs.php#programs-list-panel" class="btn btn-outline">
                    <?php echo $is_am ? 'ሁሉንም አሳይ' : 'Reset All'; ?>
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ኮድ / ፕሮግራም' : 'Code & Program'; ?></th>
                        <th><?php echo $is_am ? 'ክፍያ (ETB)' : 'Fee (ETB)'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($programs_list)): ?>
                        <?php foreach ($programs_list as $p):
                            $pid = (int)$p['id'];
                            $ptitle = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : $p['title_en'];
                        ?>
                        <tr id="prg-row-<?php echo $pid; ?>">
                            <td><?php echo $pid; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)$p['code']); ?></strong> —
                                <?php echo htmlspecialchars((string)$ptitle); ?>
                            </td>
                            <td><?php echo number_format((float)($p['fee_etb'] ?? 0), 2); ?></td>
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
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ፕሮግራም አልተገኘም።' : 'No programs found.')); ?></td></tr>
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