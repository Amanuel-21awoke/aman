<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['lab_technician'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['lab_msg'] ?? '';
$err = $_SESSION['lab_err'] ?? '';
unset($_SESSION['lab_msg'], $_SESSION['lab_err']);

$allowed_eq_status = ['Operational', 'Maintenance', 'Out of Service'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS lab_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lab_name VARCHAR(120) NOT NULL,
            item_name VARCHAR(150) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            status VARCHAR(40) NOT NULL DEFAULT 'Operational',
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_equipment') {
        $lab_name = htmlspecialchars_decode(clean_input(trim((string)($_POST['lab_name'] ?? ''))), ENT_QUOTES);
        $item_name = htmlspecialchars_decode(clean_input(trim((string)($_POST['item_name'] ?? ''))), ENT_QUOTES);
        $qty       = max(1, (int)($_POST['quantity'] ?? 1));
        $st_val    = trim((string)($_POST['status'] ?? 'Operational'));
        if (!in_array($st_val, $allowed_eq_status, true)) {
            $st_val = 'Operational';
        }
        $notes = htmlspecialchars_decode(clean_input(trim((string)($_POST['notes'] ?? ''))), ENT_QUOTES);

        if ($lab_name === '' || $item_name === '') {
            $_SESSION['lab_err'] = $is_am ? 'እባክዎ የላብራቶሪ ስም እና የመሳሪያ/ግብዓት ስም ያስገቡ።' : 'Please enter lab name and equipment name.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO lab_equipment (lab_name, item_name, quantity, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssissi", $lab_name, $item_name, $qty, $st_val, $notes, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['lab_msg'] = $is_am ? 'የላብራቶሪ መዝገብ ተቀምጧል።' : 'Lab equipment saved.';
                    } else {
                        $_SESSION['lab_err'] = $is_am ? 'መዝገቡን ማስቀመጥ አልተቻለም።' : 'Failed to save lab equipment.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['lab_err'] = $e->getMessage();
            }
        }
        header("Location: index.php#add-equipment-panel");
        exit;
    } elseif ($act === 'update_equipment_status') {
        $eq_id  = (int)($_POST['equipment_id'] ?? 0);
        $st_val = trim((string)($_POST['status'] ?? 'Operational'));
        if (!in_array($st_val, $allowed_eq_status, true)) {
            $st_val = 'Operational';
        }

        if ($eq_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE lab_equipment SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $st_val, $eq_id);
                    if ($stmt->execute()) {
                        $_SESSION['lab_msg'] = $is_am ? 'የመሳሪያ ሁኔታ ተዘምኗል።' : 'Equipment status updated.';
                    } else {
                        $_SESSION['lab_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update equipment status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['lab_err'] = $e->getMessage();
            }
        }
        $anchor = ($eq_id > 0) ? '#lab-row-' . $eq_id : '#lab-equipment-panel';
        header("Location: index.php" . $anchor);
        exit;
    }
}

$equipment_list = [];
$op_cnt = 0;
$mnt_cnt = 0;

try {
    $re = $conn->query("SELECT id, lab_name, item_name, quantity, status, notes, created_at FROM lab_equipment ORDER BY id DESC");
    if ($re) {
        while ($row = $re->fetch_assoc()) {
            $equipment_list[] = $row;
            if (($row['status'] ?? '') === 'Operational') {
                $op_cnt++;
            } else {
                $mnt_cnt++;
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
            <h1><?php echo $is_am ? 'የላብ ቴክኒሻን ዳሽቦርድ' : 'Lab Technician Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የላብራቶሪ መሳሪያዎች ክምችት፣ ጥገና እና የአገልግሎት ሁኔታ ክትትል' : 'Track lab inventory, maintenance status, and readiness'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'በስራ ላይ ያሉ' : 'Operational'; ?></p><h2><?php echo $op_cnt; ?></h2></div>
            <i class="bi bi-pc-display"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#b45309,#f59e0b);">
            <div><p><?php echo $is_am ? 'ጥገና / ተቋርጧል' : 'Maintenance / Out'; ?></p><h2><?php echo $mnt_cnt; ?></h2></div>
            <i class="bi bi-tools"></i>
        </div>
    </div>

    <div class="card-box" id="add-equipment-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የላብራቶሪ ንብረት መዝግብ' : 'Add Lab Equipment'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_equipment">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ላብራቶሪ *' : 'Lab Name *'; ?></label>
                <input type="text" name="lab_name" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የመሳሪያ ስም *' : 'Equipment Name *'; ?></label>
                <input type="text" name="item_name" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ብዛት' : 'Quantity'; ?></label>
                <input type="number" name="quantity" class="form-control" min="1" value="1">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_eq_status as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Equipment'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="lab-equipment-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የላብራቶሪ መሳሪያዎች ዝርዝር' : 'Lab Inventory'; ?> (<?php echo count($equipment_list); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ላብራቶሪ' : 'Lab'; ?></th>
                        <th><?php echo $is_am ? 'መሳሪያ / ብዛት' : 'Item & Qty'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($equipment_list)): ?>
                        <?php foreach ($equipment_list as $eq):
                            $eq_id = (int)$eq['id'];
                        ?>
                        <tr id="lab-row-<?php echo $eq_id; ?>">
                            <td><strong><?php echo htmlspecialchars((string)$eq['lab_name']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars((string)$eq['item_name']); ?> (<?php echo (int)$eq['quantity']; ?>)
                                <?php if (!empty($eq['notes'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$eq['notes']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo status_badge((string)($eq['status'] ?? 'Operational')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_equipment_status">
                                    <input type="hidden" name="equipment_id" value="<?php echo $eq_id; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_eq_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($eq['status'] ?? 'Operational') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የላብራቶሪ መዝገብ አልተገኘም።' : 'No lab equipment found.')); ?></td></tr>
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