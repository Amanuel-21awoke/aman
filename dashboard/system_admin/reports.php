<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['system_admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$msg = $_SESSION['adm_rep_msg'] ?? '';
$err = $_SESSION['adm_rep_err'] ?? '';
unset($_SESSION['adm_rep_msg'], $_SESSION['adm_rep_err']);

$allowed_filters = ['ALL', 'Pending', 'Approved', 'Rejected', 'Completed'];

$filter_status = trim((string)($_GET['status'] ?? 'ALL'));
if (!in_array($filter_status, $allowed_filters, true)) {
    $filter_status = 'ALL';
}
$search_q = trim((string)($_GET['q'] ?? ''));

$build_rep_url = static function (string $st, string $q, string $anchor = '#reports-summary-panel'): string {
    $params = [];
    if ($st !== '' && $st !== 'ALL') {
        $params['status'] = $st;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    $qs = !empty($params) ? ('?' . http_build_query($params)) : '';
    return 'reports.php' . $qs . $anchor;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'filter_report') {
        $chosen_st = trim((string)($_POST['status'] ?? 'ALL'));
        if (!in_array($chosen_st, $allowed_filters, true)) {
            $chosen_st = 'ALL';
        }
        $chosen_q = htmlspecialchars_decode(clean_input(trim((string)($_POST['q'] ?? ''))), ENT_QUOTES);
        $_SESSION['adm_rep_msg'] = $is_am ? 'የሪፖርት ዝርዝር ተዘምኗል።' : 'Report filter applied.';
        header("Location: " . $build_rep_url($chosen_st, $chosen_q, '#reports-summary-panel'));
        exit;
    } elseif ($act === 'reset_report') {
        $_SESSION['adm_rep_msg'] = $is_am ? 'ሁሉም የሪፖርት መዝገቦች እየታዩ ነው።' : 'Showing all report records.';
        header("Location: reports.php#reports-summary-panel");
        exit;
    }
}

$all_records_count = 0;
$total_fees_shown  = 0.0;
$rows = [];

try {
    $rc = $conn->query("SELECT COUNT(*) AS c FROM enrollments");
    if ($rc) {
        $all_records_count = (int)($rc->fetch_assoc()['c'] ?? 0);
    }

    $where_sql = [];
    $types = '';
    $params = [];

    if ($filter_status !== 'ALL') {
        $where_sql[] = "e.status = ?";
        $types .= 's';
        $params[] = $filter_status;
    }
    if ($search_q !== '') {
        $where_sql[] = "(u.name LIKE ? OR u.email LIKE ? OR p.code LIKE ? OR p.title_en LIKE ? OR p.title_am LIKE ?)";
        $types .= 'sssss';
        $like = '%' . $search_q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT e.id, e.status, e.payment_status, e.assessment_score, e.attendance_pct, e.created_at,
               u.name AS trainee_name, u.email AS trainee_email,
               p.code AS program_code, p.title_en, p.title_am, p.fee_etb
        FROM enrollments e
        LEFT JOIN users u ON e.user_id = u.id
        LEFT JOIN training_programs p ON e.program_id = p.id
    ";
    if (!empty($where_sql)) {
        $sql .= " WHERE " . implode(" AND ", $where_sql);
    }
    $sql .= " ORDER BY e.id ASC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                    $total_fees_shown += (float)($r['fee_etb'] ?? 0);
                }
            }
            $stmt->close();
        }
    } else {
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
                $total_fees_shown += (float)($r['fee_etb'] ?? 0);
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
            <h1><?php echo $is_am ? 'ሁሉንም የስርዓት ሪፖርቶች አሳይ' : 'System Reports (All Records)'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የሁሉም ምዝገባዎች፣ የክፍያ እና የፕሮግራም ሪፖርቶች ሙሉ ዝርዝር' : 'Complete report view of all enrollments, program fees, and statuses'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'የሚታዩ ሪፖርቶች' : 'Showing Records'; ?></p><h2><?php echo count($rows); ?> / <?php echo $all_records_count; ?></h2></div>
            <i class="bi bi-bar-chart-line-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
            <div><p><?php echo $is_am ? 'ድምር ክፍያ (ETB)' : 'Total Fee (ETB)'; ?></p><h2><?php echo number_format($total_fees_shown, 2); ?></h2></div>
            <i class="bi bi-cash-stack"></i>
        </div>
    </div>

    <div class="card-box" id="reports-summary-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የሪፖርት ማጣሪያ እና ሙሉ ዝርዝር' : 'Report Filter & Full Summary'; ?> (<?php echo count($rows); ?>)
        </h3>

        <form method="POST" style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:end; margin-bottom:1rem;">
            <input type="hidden" name="action" value="filter_report">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የምዝገባ ሁኔታ' : 'Enrollment Status'; ?></label>
                <select name="status" class="form-control" style="min-width:170px;">
                    <?php foreach ($allowed_filters as $flt): ?>
                        <option value="<?php echo $flt; ?>" <?php echo ($filter_status === $flt) ? 'selected' : ''; ?>><?php echo $flt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፈልግ (ሰልጣኝ / ፕሮግራም)' : 'Search (Trainee / Program)'; ?></label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo $is_am ? 'ስም ወይም ፕሮግራም...' : 'Trainee or program...'; ?>">
            </div>
            <div class="form-group" style="margin:0; display:flex; gap:0.4rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> <?php echo $is_am ? 'አስቀምጥ / አጣራ' : 'Save Filter'; ?>
                </button>
            </div>
        </form>

        <form method="POST" style="display:inline-block; margin-bottom:0.85rem;">
            <input type="hidden" name="action" value="reset_report">
            <button type="submit" class="btn btn-outline btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> <?php echo $is_am ? 'ሁሉንም አሳይ (Reset)' : 'Show All'; ?>
            </button>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ሰልጣኝ' : 'Trainee'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ / ክፍያ' : 'Status & Payment'; ?></th>
                        <th><?php echo $is_am ? 'ክፍያ (ETB)' : 'Fee (ETB)'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $r):
                            $rid = (int)$r['id'];
                            $ptitle = ($is_am && !empty($r['title_am'])) ? $r['title_am'] : ($r['title_en'] ?? '');
                        ?>
                        <tr id="rep-row-<?php echo $rid; ?>">
                            <td><?php echo $rid; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($r['trainee_name'] ?? '')); ?></strong>
                                <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)($r['trainee_email'] ?? '')); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars((string)(($r['program_code'] ?? '') . ' — ' . $ptitle)); ?></td>
                            <td>
                                <?php echo status_badge((string)($r['status'] ?? 'Pending')); ?>
                                <div style="font-size:0.78rem; color:#64748b; margin-top:0.2rem;"><?php echo htmlspecialchars((string)($r['payment_status'] ?? 'Unpaid')); ?></div>
                            </td>
                            <td><?php echo number_format((float)($r['fee_etb'] ?? 0), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የሪፖርት መረጃ አልተገኘም።' : 'No report records found.')); ?></td></tr>
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