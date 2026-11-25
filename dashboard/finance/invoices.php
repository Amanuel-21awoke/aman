<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['finance_officer', 'finance'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['fin_inv_msg'] ?? '';
$err   = $_SESSION['fin_inv_err'] ?? '';
unset($_SESSION['fin_inv_msg'], $_SESSION['fin_inv_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'invoices.php'));
$allowed_inv_status = ['Issued', 'Paid', 'Partial', 'Cancelled'];

/**
 * Ensure `finance_invoices` exists and self-heal missing columns (`invoice_no`,
 * `payer_name`, `amount_etb`, `status`, `created_by`) so queries never fail.
 */
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `finance_invoices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_no` VARCHAR(80) NOT NULL,
            `payer_name` VARCHAR(150) NOT NULL,
            `amount_etb` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(30) NOT NULL DEFAULT 'Issued',
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $inv_cols = [];
    $r_inv = $conn->query("SHOW COLUMNS FROM `finance_invoices`");
    if ($r_inv) {
        while ($c = $r_inv->fetch_assoc()) {
            $inv_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (!in_array('invoice_no', $inv_cols, true)) {
        $conn->query("ALTER TABLE `finance_invoices` ADD COLUMN `invoice_no` VARCHAR(80) NOT NULL DEFAULT ''");
    }
    if (!in_array('payer_name', $inv_cols, true)) {
        $conn->query("ALTER TABLE `finance_invoices` ADD COLUMN `payer_name` VARCHAR(150) NOT NULL DEFAULT ''");
    }
    if (!in_array('amount_etb', $inv_cols, true)) {
        $conn->query("ALTER TABLE `finance_invoices` ADD COLUMN `amount_etb` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('status', $inv_cols, true)) {
        $conn->query("ALTER TABLE `finance_invoices` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'Issued'");
    }
    if (!in_array('created_by', $inv_cols, true)) {
        $conn->query("ALTER TABLE `finance_invoices` ADD COLUMN `created_by` INT NULL");
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_invoice') {
        $inv_no   = htmlspecialchars_decode(clean_input(trim((string)($_POST['invoice_no'] ?? ''))), ENT_QUOTES);
        $payer    = htmlspecialchars_decode(clean_input(trim((string)($_POST['payer_name'] ?? ''))), ENT_QUOTES);
        $amount   = max(0.0, (float)($_POST['amount_etb'] ?? 0));
        $status_v = trim((string)($_POST['status'] ?? 'Issued'));

        if (!in_array($status_v, $allowed_inv_status, true)) {
            $status_v = 'Issued';
        }

        $redirect_anchor = '#add-invoice-panel';

        if ($inv_no === '' || $payer === '') {
            $_SESSION['fin_inv_err'] = $is_am ? 'እባክዎ የደረሰኝ/ኢንቮይስ ቁጥር እና የከፋይ ስም ያስገቡ።' : 'Please enter an invoice number and payer name.';
        } else {
            try {
                $creator_val = ($uid > 0) ? $uid : null;
                $stmt = $conn->prepare("
                    INSERT INTO `finance_invoices` (`invoice_no`, `payer_name`, `amount_etb`, `status`, `created_by`)
                    VALUES (?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param("ssdsi", $inv_no, $payer, $amount, $status_v, $creator_val);
                    if ($stmt->execute()) {
                        $new_id = (int)$conn->insert_id;
                        $redirect_anchor = ($new_id > 0) ? ('#inv-row-' . $new_id) : '#invoices-list-panel';
                        $_SESSION['fin_inv_msg'] = $is_am ? 'ኢንቮይስ ተቀምጧል።' : 'Invoice recorded.';
                    } else {
                        $_SESSION['fin_inv_err'] = $is_am ? 'ኢንቮይሱን ማስቀመጥ አልተቻለም።' : 'Failed to save invoice.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['fin_inv_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . $redirect_anchor);
        exit;
    } elseif ($act === 'update_invoice_status') {
        $inv_id   = (int)($_POST['invoice_id'] ?? 0);
        $status_v = trim((string)($_POST['status'] ?? 'Issued'));

        if (!in_array($status_v, $allowed_inv_status, true)) {
            $status_v = 'Issued';
        }

        if ($inv_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE `finance_invoices` SET `status` = ? WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $status_v, $inv_id);
                    if ($stmt->execute()) {
                        $_SESSION['fin_inv_msg'] = $is_am ? 'የኢንቮይስ ሁኔታ ተዘምኗል።' : 'Invoice status updated.';
                    } else {
                        $_SESSION['fin_inv_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update invoice status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['fin_inv_err'] = $e->getMessage();
            }
        }

        $anchor = ($inv_id > 0) ? ('#inv-row-' . $inv_id) : '#invoices-list-panel';
        header("Location: " . $self_file . $anchor);
        exit;
    } elseif ($act === 'delete_invoice') {
        $inv_id = (int)($_POST['invoice_id'] ?? 0);
        if ($inv_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM `finance_invoices` WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $inv_id);
                    if ($stmt->execute()) {
                        $_SESSION['fin_inv_msg'] = $is_am ? 'ኢንቮይስ ተሰርዟል።' : 'Invoice deleted.';
                    } else {
                        $_SESSION['fin_inv_err'] = $is_am ? 'ኢንቮይሱን መሰረዝ አልተቻለም።' : 'Failed to delete invoice.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['fin_inv_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . "#invoices-list-panel");
        exit;
    }
}

$invoices       = [];
$total_billed   = 0.0;
$total_paid_inv = 0.0;

try {
    $res = $conn->query("SELECT id, invoice_no, payer_name, amount_etb, status, created_at FROM `finance_invoices` ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $invoices[] = $row;
            $amt = (float)($row['amount_etb'] ?? 0);
            if (($row['status'] ?? 'Issued') !== 'Cancelled') {
                $total_billed += $amt;
            }
            if (($row['status'] ?? '') === 'Paid') {
                $total_paid_inv += $amt;
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
            <h1><?php echo $is_am ? 'ኢንቮይሶች እና ደረሰኞች' : 'Invoices & Billing'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የክፍያ ኢንቮይሶችን መመዝገብ እና የክፍያ ሁኔታ ማዘመን' : 'Record billing invoices and update payment status'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ኢንቮይስ (ETB)' : 'Total Billed (ETB)'; ?></p><h2><?php echo number_format($total_billed, 2); ?></h2></div>
            <i class="bi bi-receipt-cutoff"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'የተከፈለ (Paid ETB)' : 'Paid Invoices (ETB)'; ?></p><h2><?php echo number_format($total_paid_inv, 2); ?></h2></div>
            <i class="bi bi-check2-circle"></i>
        </div>
    </div>

    <div class="card-box" id="add-invoice-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ ኢንቮይስ መዝግብ' : 'Create New Invoice'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_invoice">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የኢንቮይስ ቁጥር *' : 'Invoice No *'; ?></label>
                <input type="text" name="invoice_no" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የከፋይ / አካል ስም *' : 'Payer / Entity *'; ?></label>
                <input type="text" name="payer_name" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'መጠን (ETB)' : 'Amount (ETB)'; ?></label>
                <input type="number" name="amount_etb" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_inv_status as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-receipt"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Invoice'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="invoices-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የኢንቮይሶች ዝርዝር' : 'Invoices List'; ?> (<?php echo count($invoices); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ቁጥር' : 'Invoice No'; ?></th>
                        <th><?php echo $is_am ? 'ከፋይ' : 'Payer'; ?></th>
                        <th><?php echo $is_am ? 'መጠን (ETB)' : 'Amount (ETB)'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoices)): ?>
                        <?php foreach ($invoices as $inv):
                            $iid = (int)$inv['id'];
                        ?>
                        <tr id="inv-row-<?php echo $iid; ?>">
                            <td><strong><?php echo htmlspecialchars((string)($inv['invoice_no'] ?? '')); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)($inv['payer_name'] ?? '')); ?></td>
                            <td><?php echo number_format((float)($inv['amount_etb'] ?? 0), 2); ?></td>
                            <td><?php echo status_badge((string)($inv['status'] ?? 'Issued')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_invoice_status">
                                    <input type="hidden" name="invoice_id" value="<?php echo $iid; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_inv_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($inv['status'] ?? 'Issued') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check2"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ኢንቮይስ መሰረዝ ይፈልጋሉ?' : 'Delete this invoice?'; ?>');">
                                    <input type="hidden" name="action" value="delete_invoice">
                                    <input type="hidden" name="invoice_id" value="<?php echo $iid; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ኢንቮይስ አልተገኘም።' : 'No invoices found.')); ?></td></tr>
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