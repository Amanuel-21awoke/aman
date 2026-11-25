<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['finance_officer', 'finance'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['fin_bud_msg'] ?? '';
$err   = $_SESSION['fin_bud_err'] ?? '';
unset($_SESSION['fin_bud_msg'], $_SESSION['fin_bud_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'budgets.php'));

/**
 * Ensure `finance_budgets` exists and self-heal missing columns (`department`,
 * `allocated_etb`, `spent_etb`, `fiscal_year`, `created_by`) so queries never fail.
 */
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `finance_budgets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `department` VARCHAR(120) NOT NULL,
            `allocated_etb` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `spent_etb` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `fiscal_year` VARCHAR(40) NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $b_cols = [];
    $r_b = $conn->query("SHOW COLUMNS FROM `finance_budgets`");
    if ($r_b) {
        while ($c = $r_b->fetch_assoc()) {
            $b_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (!in_array('department', $b_cols, true)) {
        $conn->query("ALTER TABLE `finance_budgets` ADD COLUMN `department` VARCHAR(120) NOT NULL DEFAULT ''");
    }
    if (!in_array('allocated_etb', $b_cols, true)) {
        $conn->query("ALTER TABLE `finance_budgets` ADD COLUMN `allocated_etb` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('spent_etb', $b_cols, true)) {
        $conn->query("ALTER TABLE `finance_budgets` ADD COLUMN `spent_etb` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('fiscal_year', $b_cols, true)) {
        $conn->query("ALTER TABLE `finance_budgets` ADD COLUMN `fiscal_year` VARCHAR(40) NULL");
    }
    if (!in_array('created_by', $b_cols, true)) {
        $conn->query("ALTER TABLE `finance_budgets` ADD COLUMN `created_by` INT NULL");
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_budget') {
        $dept      = htmlspecialchars_decode(clean_input(trim((string)($_POST['department'] ?? ''))), ENT_QUOTES);
        $allocated = max(0.0, (float)($_POST['allocated_etb'] ?? 0));
        $spent     = max(0.0, (float)($_POST['spent_etb'] ?? 0));
        $fy        = htmlspecialchars_decode(clean_input(trim((string)($_POST['fiscal_year'] ?? ''))), ENT_QUOTES);

        $redirect_anchor = '#add-budget-panel';

        if ($dept === '') {
            $_SESSION['fin_bud_err'] = $is_am ? 'እባክዎ የክፍል/ዲፓርትመንት ስም ያስገቡ።' : 'Please enter a department or category name.';
        } else {
            try {
                $creator_val = ($uid > 0) ? $uid : null;
                $stmt = $conn->prepare("
                    INSERT INTO `finance_budgets` (`department`, `allocated_etb`, `spent_etb`, `fiscal_year`, `created_by`)
                    VALUES (?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param("sddsi", $dept, $allocated, $spent, $fy, $creator_val);
                    if ($stmt->execute()) {
                        $new_id = (int)$conn->insert_id;
                        $redirect_anchor = ($new_id > 0) ? ('#bud-row-' . $new_id) : '#budgets-list-panel';
                        $_SESSION['fin_bud_msg'] = $is_am ? 'የበጀት መረጃ ተቀምጧል።' : 'Budget allocation saved.';
                    } else {
                        $_SESSION['fin_bud_err'] = $is_am ? 'በጀቱን ማስቀመጥ አልተቻለም።' : 'Failed to save budget.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['fin_bud_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . $redirect_anchor);
        exit;
    } elseif ($act === 'update_spent') {
        $bud_id = (int)($_POST['budget_id'] ?? 0);
        $spent  = max(0.0, (float)($_POST['spent_etb'] ?? 0));

        if ($bud_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE `finance_budgets` SET `spent_etb` = ? WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("di", $spent, $bud_id);
                    if ($stmt->execute()) {
                        $_SESSION['fin_bud_msg'] = $is_am ? 'ወጪው ተዘምኗል።' : 'Spent amount updated.';
                    } else {
                        $_SESSION['fin_bud_err'] = $is_am ? 'ወጪውን ማዘመን አልተቻለም።' : 'Failed to update spent amount.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['fin_bud_err'] = $e->getMessage();
            }
        }

        $anchor = ($bud_id > 0) ? ('#bud-row-' . $bud_id) : '#budgets-list-panel';
        header("Location: " . $self_file . $anchor);
        exit;
    } elseif ($act === 'delete_budget') {
        $bud_id = (int)($_POST['budget_id'] ?? 0);
        if ($bud_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM `finance_budgets` WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $bud_id);
                    if ($stmt->execute()) {
                        $_SESSION['fin_bud_msg'] = $is_am ? 'የበጀት መዝገብ ተሰርዟል።' : 'Budget record deleted.';
                    } else {
                        $_SESSION['fin_bud_err'] = $is_am ? 'መዝገቡን መሰረዝ አልተቻለም።' : 'Failed to delete budget record.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['fin_bud_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . "#budgets-list-panel");
        exit;
    }
}

$budgets     = [];
$total_alloc = 0.0;
$total_spent = 0.0;

try {
    $res = $conn->query("SELECT id, department, allocated_etb, spent_etb, fiscal_year, created_at FROM `finance_budgets` ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $budgets[] = $row;
            $total_alloc += (float)($row['allocated_etb'] ?? 0);
            $total_spent += (float)($row['spent_etb'] ?? 0);
        }
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

$total_rem = $total_alloc - $total_spent;

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo $is_am ? 'በጀት እና ወጪ ክትትል' : 'Budgets & Expenses'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የተመደበ በጀት፣ የወጪ መጠን እና ቀሪ ሂሳብ ማስተዳደር' : 'Manage allocated budgets, spent amounts, and remaining balances'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ የተመደበ (ETB)' : 'Total Allocated (ETB)'; ?></p><h2><?php echo number_format($total_alloc, 2); ?></h2></div>
            <i class="bi bi-wallet2"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ወጪ (ETB)' : 'Total Spent (ETB)'; ?></p><h2><?php echo number_format($total_spent, 2); ?></h2></div>
            <i class="bi bi-cash-coin"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ቀሪ (ETB)' : 'Total Remaining (ETB)'; ?></p><h2><?php echo number_format($total_rem, 2); ?></h2></div>
            <i class="bi bi-piggy-bank"></i>
        </div>
    </div>

    <div class="card-box" id="add-budget-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የበጀት ምደባ ጨምር' : 'Add Budget Allocation'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_budget">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ክፍል / ዘርፍ *' : 'Department / Unit *'; ?></label>
                <input type="text" name="department" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የተመደበ (ETB)' : 'Allocated (ETB)'; ?></label>
                <input type="number" name="allocated_etb" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የወጣ / Spent (ETB)' : 'Spent (ETB)'; ?></label>
                <input type="number" name="spent_etb" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የበጀት ዓመት' : 'Fiscal Year'; ?></label>
                <input type="text" name="fiscal_year" class="form-control" placeholder="2018/2019 E.C.">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-wallet2"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Budget'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="budgets-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የበጀት ዝርዝር' : 'Budget Allocations'; ?> (<?php echo count($budgets); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ክፍል / ዘርፍ' : 'Department'; ?></th>
                        <th><?php echo $is_am ? 'የተመደበ (ETB)' : 'Allocated (ETB)'; ?></th>
                        <th><?php echo $is_am ? 'ቀሪ (ETB)' : 'Remaining (ETB)'; ?></th>
                        <th><?php echo $is_am ? 'የወጣ / ድርጊት' : 'Spent & Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($budgets)): ?>
                        <?php foreach ($budgets as $b):
                            $bid   = (int)$b['id'];
                            $alloc = (float)($b['allocated_etb'] ?? 0);
                            $spent = (float)($b['spent_etb'] ?? 0);
                            $rem   = $alloc - $spent;
                        ?>
                        <tr id="bud-row-<?php echo $bid; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)($b['department'] ?? '')); ?></strong>
                                <?php if (!empty($b['fiscal_year'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$b['fiscal_year']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($alloc, 2); ?></td>
                            <td><strong><?php echo number_format($rem, 2); ?></strong></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_spent">
                                    <input type="hidden" name="budget_id" value="<?php echo $bid; ?>">
                                    <input type="number" name="spent_etb" class="form-control" style="width:105px; padding:0.25rem 0.45rem; font-size:0.82rem;"
                                           min="0" step="0.01" value="<?php echo htmlspecialchars((string)$spent); ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check2"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን የበጀት መዝገብ መሰረዝ ይፈልጋሉ?' : 'Delete this budget record?'; ?>');">
                                    <input type="hidden" name="action" value="delete_budget">
                                    <input type="hidden" name="budget_id" value="<?php echo $bid; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የበጀት መረጃ አልተገኘም።' : 'No budget records found.')); ?></td></tr>
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