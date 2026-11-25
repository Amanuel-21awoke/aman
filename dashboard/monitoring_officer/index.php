<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['monitoring_officer'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['mon_msg'] ?? '';
$err = $_SESSION['mon_err'] ?? '';
unset($_SESSION['mon_msg'], $_SESSION['mon_err']);

$allowed_mon_status = ['Compliant', 'Needs Attention', 'Follow-up'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS monitoring_observations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_id INT NULL,
            observation_title VARCHAR(180) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'Compliant',
            findings TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_observation') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['observation_title'] ?? ''))), ENT_QUOTES);
        $st_val  = trim((string)($_POST['status'] ?? 'Compliant'));
        if (!in_array($st_val, $allowed_mon_status, true)) {
            $st_val = 'Compliant';
        }
        $findings = htmlspecialchars_decode(clean_input(trim((string)($_POST['findings'] ?? ''))), ENT_QUOTES);

        if ($title === '') {
            $_SESSION['mon_err'] = $is_am ? 'እባክዎ የክትትል/ምልከታ ርዕስ ያስገቡ።' : 'Please enter an observation title.';
        } else {
            try {
                $pid_val = ($prog_id > 0) ? $prog_id : null;
                $stmt = $conn->prepare("INSERT INTO monitoring_observations (program_id, observation_title, status, findings, created_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isssi", $pid_val, $title, $st_val, $findings, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['mon_msg'] = $is_am ? 'የክትትል መዝገብ ተቀምጧል።' : 'Monitoring observation saved.';
                    } else {
                        $_SESSION['mon_err'] = $is_am ? 'መዝገቡን ማስቀመጥ አልተቻለም።' : 'Failed to save observation.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['mon_err'] = $e->getMessage();
            }
        }
        header("Location: index.php#add-observation-panel");
        exit;
    } elseif ($act === 'update_observation_status') {
        $obs_id = (int)($_POST['observation_id'] ?? 0);
        $st_val = trim((string)($_POST['status'] ?? 'Compliant'));
        if (!in_array($st_val, $allowed_mon_status, true)) {
            $st_val = 'Compliant';
        }

        if ($obs_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE monitoring_observations SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $st_val, $obs_id);
                    if ($stmt->execute()) {
                        $_SESSION['mon_msg'] = $is_am ? 'የክትትል ሁኔታ ተዘምኗል።' : 'Observation status updated.';
                    } else {
                        $_SESSION['mon_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update observation status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['mon_err'] = $e->getMessage();
            }
        }
        $anchor = ($obs_id > 0) ? '#mon-row-' . $obs_id : '#monitoring-list-panel';
        header("Location: index.php" . $anchor);
        exit;
    }
}

$programs     = [];
$observations = [];
$prog_cnt     = 0;
$obs_cnt      = 0;

try {
    $r1 = $conn->query("SELECT COUNT(*) AS c FROM training_programs");
    if ($r1) { $prog_cnt = (int)($r1->fetch_assoc()['c'] ?? 0); }

    $rp = $conn->query("SELECT id, code, title_en, title_am FROM training_programs ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $ro = $conn->query("
        SELECT m.id, m.observation_title, m.status, m.findings, m.created_at, p.code, p.title_en, p.title_am
        FROM monitoring_observations m
        LEFT JOIN training_programs p ON m.program_id = p.id
        ORDER BY m.id DESC
    ");
    if ($ro) {
        while ($o = $ro->fetch_assoc()) {
            $observations[] = $o;
            $obs_cnt++;
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
            <h1><?php echo $is_am ? 'የክትትል ባለሙያ ዳሽቦርድ' : 'Monitoring Officer Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና አፈጻጸም ክትትል፣ ምልከታ እና የማስተካከያ ሪፖርት' : 'Monitor training delivery compliance, observations, and follow-ups'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ፕሮግራሞች' : 'Programs'; ?></p><h2><?php echo $prog_cnt; ?></h2></div>
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'የክትትል መዝገቦች' : 'Observations'; ?></p><h2><?php echo $obs_cnt; ?></h2></div>
            <i class="bi bi-clipboard-data-fill"></i>
        </div>
    </div>

    <div class="card-box" id="add-observation-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የክትትል ምልከታ መዝግብ' : 'Log Monitoring Observation'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_observation">
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
                <input type="text" name="observation_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_mon_status as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ዝርዝር ግኝት' : 'Findings'; ?></label>
                <input type="text" name="findings" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Observation'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="monitoring-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የክትትል መዝገቦች ዝርዝር' : 'Observations Log'; ?> (<?php echo count($observations); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Observation'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($observations)): ?>
                        <?php foreach ($observations as $o):
                            $oid = (int)$o['id'];
                            $ptitle = ($is_am && !empty($o['title_am'])) ? $o['title_am'] : ($o['title_en'] ?? '');
                        ?>
                        <tr id="mon-row-<?php echo $oid; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)$o['observation_title']); ?></strong>
                                <?php if (!empty($o['findings'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$o['findings']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)($o['code'] ? ($o['code'] . ' — ' . $ptitle) : ($is_am ? 'አጠቃላይ' : 'General'))); ?></td>
                            <td><?php echo status_badge((string)($o['status'] ?? 'Compliant')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_observation_status">
                                    <input type="hidden" name="observation_id" value="<?php echo $oid; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_mon_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($o['status'] ?? 'Compliant') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የክትትል መረጃ አልተገኘም።' : 'No observations found.')); ?></td></tr>
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