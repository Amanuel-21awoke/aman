<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['finance', 'finance_officer', 'sponsor_liaison'], '../../');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['sp_task_msg'] ?? '';
$err   = $_SESSION['sp_task_err'] ?? '';
unset($_SESSION['sp_task_msg'], $_SESSION['sp_task_err']);

$self_file         = basename((string)($_SERVER['PHP_SELF'] ?? 'sponsor_task.php'));
$allowed_sp_status = ['Active', 'Pending', 'Completed'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `sponsor_partnerships` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sponsor_name` VARCHAR(150) NOT NULL,
            `pledge_etb` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(40) NOT NULL DEFAULT 'Active',
            `notes` TEXT NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = $_POST['action'] ?? '';
    $csrf_val = (string)($_POST['csrf_token'] ?? '');

    if ($act === 'add_sponsor_task') {
        if (empty($csrf_val) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_val)) {
            $_SESSION['sp_task_err'] = $is_am
                ? 'የደህንነት ማረጋገጫ (CSRF) አልተሳካም። እባክዎ ገጹን እንደገና ይጫኑ።'
                : 'Invalid security token (CSRF). Please refresh and try again.';
            header("Location: " . $self_file . "#add-sponsor-task-panel");
            exit;
        }

        $sp_name = htmlspecialchars_decode(clean_input(trim((string)($_POST['sponsor_name'] ?? ''))), ENT_QUOTES);
        $pledge  = max(0, (float)($_POST['pledge_etb'] ?? 0));
        $st_val  = trim((string)($_POST['status'] ?? 'Active'));
        if (!in_array($st_val, $allowed_sp_status, true)) {
            $st_val = 'Active';
        }
        $notes = htmlspecialchars_decode(clean_input(trim((string)($_POST['notes'] ?? ''))), ENT_QUOTES);

        if ($sp_name === '') {
            $_SESSION['sp_task_err'] = $is_am ? 'እባክዎ የስፖንሰር/አጋር ድርጅት ስም ያስገቡ።' : 'Please enter sponsor organization name.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO `sponsor_partnerships` (`sponsor_name`, `pledge_etb`, `status`, `notes`, `created_by`) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sdssi", $sp_name, $pledge, $st_val, $notes, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['sp_task_msg'] = $is_am ? 'የስፖንሰር ተግባር ተመዝግቧል።' : 'Sponsor task saved.';
                    } else {
                        $_SESSION['sp_task_err'] = $is_am ? 'የስፖንሰር ተግባር ማስቀመጥ አልተቻለም።' : 'Failed to save sponsor task.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['sp_task_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . "#add-sponsor-task-panel");
        exit;
    } elseif ($act === 'update_sponsor_task_status') {
        $sp_id  = (int)($_POST['sponsor_id'] ?? 0);
        $anchor = ($sp_id > 0) ? ('#sp-task-row-' . $sp_id) : '#sponsor-tasks-list-panel';

        if (empty($csrf_val) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_val)) {
            $_SESSION['sp_task_err'] = $is_am
                ? 'የደህንነት ማረጋገጫ (CSRF) አልተሳካም። እባክዎ ገጹን እንደገና ይጫኑ።'
                : 'Invalid security token (CSRF). Please refresh and try again.';
            header("Location: " . $self_file . $anchor);
            exit;
        }

        $st_val = trim((string)($_POST['status'] ?? 'Active'));
        if (!in_array($st_val, $allowed_sp_status, true)) {
            $st_val = 'Active';
        }

        if ($sp_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE `sponsor_partnerships` SET `status` = ? WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $st_val, $sp_id);
                    if ($stmt->execute()) {
                        $_SESSION['sp_task_msg'] = $is_am ? 'የስፖንሰር ተግባር ሁኔታ ተዘምኗል።' : 'Sponsor task status updated.';
                    } else {
                        $_SESSION['sp_task_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update sponsor task status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['sp_task_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . $anchor);
        exit;
    }
}

$sponsors_list    = [];
$total_pledge_etb = 0.0;

try {
    $r_sp = $conn->query("SELECT id, sponsor_name, pledge_etb, status, notes, created_at FROM `sponsor_partnerships` ORDER BY id DESC");
    if ($r_sp) {
        while ($sp_row = $r_sp->fetch_assoc()) {
            $sponsors_list[] = $sp_row;
            $total_pledge_etb += (float)($sp_row['pledge_etb'] ?? 0);
        }
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<style>
.sp-task-page,
.sp-task-page *,
.sp-task-page *::before,
.sp-task-page *::after {
    box-sizing: border-box;
}
.sp-task-page .form-control {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.sp-task-page .form-control:focus {
    outline: none;
    border-color: var(--primary-color, #17663f);
    box-shadow: 0 0 0 3px rgba(23, 102, 63, 0.14);
}
@media (max-width: 640px) {
    .sp-task-page .card-box {
        padding: 1rem 0.85rem;
    }
}
</style>

<div class="content-with-sidebar sp-task-page">
    <div class="dashboard-header" style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:0.75rem;">
        <div>
            <h1><?php echo $is_am ? 'የስፖንሰር ተግባር (Sponsor Liaison Task)' : 'Sponsor Liaison Task'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስፖንሰር ተግባራት፣ ቃል-ገቢ እና ሁኔታ አስተዳደር' : 'Manage sponsor liaison tasks, pledges, and collaboration statuses'; ?>
            </p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline btn-sm">
                <i class="bi bi-speedometer2"></i> <?php echo $is_am ? 'ወደ ፋይናንስ ዳሽቦርድ' : 'Finance Dashboard'; ?>
            </a>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ የስፖንሰር ተግባራት' : 'Sponsor Tasks'; ?></p><h2><?php echo count($sponsors_list); ?></h2></div>
            <i class="bi bi-building-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ቃል-ገቢ (ETB)' : 'Total Pledged (ETB)'; ?></p><h2><?php echo number_format($total_pledge_etb, 2); ?></h2></div>
            <i class="bi bi-cash-stack"></i>
        </div>
    </div>

    <div class="card-box" id="add-sponsor-task-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የስፖንሰር ተግባር መዝግብ' : 'Add Sponsor Task'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="add_sponsor_task">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የስፖንሰር ስም *' : 'Sponsor Name *'; ?></label>
                <input type="text" name="sponsor_name" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ቃል-ገቢ (ETB)' : 'Pledge (ETB)'; ?></label>
                <input type="number" name="pledge_etb" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_sp_status as $st): ?>
                        <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የተግባር ማስታወሻ' : 'Task Notes'; ?></label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Task'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="sponsor-tasks-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የስፖንሰር ተግባራት ዝርዝር' : 'Sponsor Tasks Directory'; ?> (<?php echo count($sponsors_list); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $is_am ? 'ስፖንሰር / ተግባር' : 'Sponsor & Task'; ?></th>
                        <th><?php echo $is_am ? 'ቃል-ገቢ (ETB)' : 'Pledge (ETB)'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'አዘምን' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sponsors_list)): ?>
                        <?php foreach ($sponsors_list as $sp):
                            $sp_id = (int)$sp['id'];
                        ?>
                        <tr id="sp-task-row-<?php echo $sp_id; ?>">
                            <td><?php echo $sp_id; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)$sp['sponsor_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($sp['notes'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$sp['notes'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format((float)($sp['pledge_etb'] ?? 0), 2); ?></td>
                            <td><?php echo status_badge((string)($sp['status'] ?? 'Active')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="update_sponsor_task_status">
                                    <input type="hidden" name="sponsor_id" value="<?php echo $sp_id; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_sp_status as $st): ?>
                                            <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($sp['status'] ?? 'Active') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
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
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የስፖንሰር ተግባር አልተገኘም።' : 'No sponsor tasks found.'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
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