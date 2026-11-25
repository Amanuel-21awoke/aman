<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainee'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['tr_pay_msg'] ?? '';
$err   = $_SESSION['tr_pay_err'] ?? '';
unset($_SESSION['tr_pay_msg'], $_SESSION['tr_pay_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'payment.php'));

/**
 * Ensure `payment_receipts` exists and detect/ensure trainee foreign-key column
 * on both `enrollments` and `payment_receipts`.
 */
$enr_user_col = 'trainee_id';
$rec_user_col = 'user_id';
$has_enr_pay_status = false;

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `payment_receipts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `enrollment_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `receipt_ref` VARCHAR(120) NOT NULL,
            `amount_etb` DECIMAL(10,2) DEFAULT 0.00,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $enr_cols = [];
    $r_enr = $conn->query("SHOW COLUMNS FROM `enrollments`");
    if ($r_enr) {
        while ($c = $r_enr->fetch_assoc()) {
            $enr_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (in_array('user_id', $enr_cols, true)) {
        $enr_user_col = 'user_id';
    } elseif (in_array('trainee_id', $enr_cols, true)) {
        $enr_user_col = 'trainee_id';
    } elseif (in_array('student_id', $enr_cols, true)) {
        $enr_user_col = 'student_id';
    } else {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `trainee_id` INT NOT NULL DEFAULT 0");
        $enr_user_col = 'trainee_id';
    }

    if (!in_array('payment_status', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `payment_status` VARCHAR(30) NOT NULL DEFAULT 'Unpaid'");
    }
    $has_enr_pay_status = true;

    $rec_cols = [];
    $r_rec = $conn->query("SHOW COLUMNS FROM `payment_receipts`");
    if ($r_rec) {
        while ($c = $r_rec->fetch_assoc()) {
            $rec_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (in_array('user_id', $rec_cols, true)) {
        $rec_user_col = 'user_id';
    } elseif (in_array('trainee_id', $rec_cols, true)) {
        $rec_user_col = 'trainee_id';
    } else {
        $conn->query("ALTER TABLE `payment_receipts` ADD COLUMN `user_id` INT NOT NULL DEFAULT 0");
        $rec_user_col = 'user_id';
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_receipt') {
    $enr_id = (int)($_POST['enrollment_id'] ?? 0);
    $ref_no = htmlspecialchars_decode(clean_input(trim((string)($_POST['receipt_ref'] ?? ''))), ENT_QUOTES);
    $amount = max(0.0, (float)($_POST['amount_etb'] ?? 0));
    $notes  = htmlspecialchars_decode(clean_input(trim((string)($_POST['notes'] ?? ''))), ENT_QUOTES);

    $redirect_anchor = '#submit-receipt-panel';

    if ($uid <= 0) {
        $_SESSION['tr_pay_err'] = $is_am ? 'የተጠቃሚ መለያ አልተገኘም፤ እባክዎ እንደገና ይግቡ።' : 'User session not found. Please log in again.';
    } elseif ($enr_id <= 0 || $ref_no === '') {
        $_SESSION['tr_pay_err'] = $is_am ? 'እባክዎ ፕሮግራም እና የደረሰኝ ቁጥር ያስገቡ።' : 'Please select an enrollment and enter a receipt reference.';
    } else {
        try {
            $chk = $conn->prepare("SELECT id FROM `enrollments` WHERE `id` = ? AND `{$enr_user_col}` = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param("ii", $enr_id, $uid);
                $chk->execute();
                $cres = $chk->get_result();
                $valid_enr = ($cres && $cres->num_rows > 0);
                $chk->close();

                if (!$valid_enr) {
                    $_SESSION['tr_pay_err'] = $is_am ? 'የተመረጠው ምዝገባ አልተገኘም።' : 'Selected enrollment was not found.';
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO `payment_receipts` (`enrollment_id`, `{$rec_user_col}`, `receipt_ref`, `amount_etb`, `notes`)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    if ($stmt) {
                        $stmt->bind_param("iisds", $enr_id, $uid, $ref_no, $amount, $notes);
                        if ($stmt->execute()) {
                            if ($has_enr_pay_status) {
                                $upd = $conn->prepare("UPDATE `enrollments` SET `payment_status` = 'Submitted' WHERE `id` = ? AND `{$enr_user_col}` = ?");
                                if ($upd) {
                                    $upd->bind_param("ii", $enr_id, $uid);
                                    $upd->execute();
                                    $upd->close();
                                }
                            }
                            $new_rec_id = (int)$conn->insert_id;
                            $redirect_anchor = ($new_rec_id > 0) ? ('#rec-row-' . $new_rec_id) : '#receipts-history-panel';
                            $_SESSION['tr_pay_msg'] = $is_am ? 'የክፍያ ደረሰኝ መረጃ ተልኳል/ተቀምጧል።' : 'Payment receipt submitted successfully.';
                        } else {
                            $_SESSION['tr_pay_err'] = $is_am ? 'ደረሰኙን ማስቀመጥ አልተቻለም።' : 'Failed to save payment receipt.';
                        }
                        $stmt->close();
                    }
                }
            }
        } catch (Throwable $e) {
            $_SESSION['tr_pay_err'] = $e->getMessage();
        }
    }

    header("Location: " . $self_file . $redirect_anchor);
    exit;
}

$my_enrollments = [];
$my_receipts    = [];

try {
    if ($uid > 0) {
        $stmt_e = $conn->prepare("
            SELECT e.id, e.payment_status, e.status, p.code, p.title_en, p.title_am, p.fee_etb
            FROM `enrollments` e
            LEFT JOIN `training_programs` p ON e.program_id = p.id
            WHERE e.`{$enr_user_col}` = ?
            ORDER BY e.id DESC
        ");
        if ($stmt_e) {
            $stmt_e->bind_param("i", $uid);
            $stmt_e->execute();
            $re = $stmt_e->get_result();
            if ($re) {
                while ($row = $re->fetch_assoc()) {
                    $my_enrollments[] = $row;
                }
            }
            $stmt_e->close();
        }

        $stmt_r = $conn->prepare("
            SELECT pr.id, pr.receipt_ref, pr.amount_etb, pr.notes, pr.created_at,
                   p.code, p.title_en, p.title_am
            FROM `payment_receipts` pr
            LEFT JOIN `enrollments` e ON pr.enrollment_id = e.id
            LEFT JOIN `training_programs` p ON e.program_id = p.id
            WHERE pr.`{$rec_user_col}` = ?
            ORDER BY pr.id DESC
        ");
        if ($stmt_r) {
            $stmt_r->bind_param("i", $uid);
            $stmt_r->execute();
            $rr = $stmt_r->get_result();
            if ($rr) {
                while ($row = $rr->fetch_assoc()) {
                    $my_receipts[] = $row;
                }
            }
            $stmt_r->close();
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
            <h1><?php echo $is_am ? 'ክፍያ እና ደረሰኝ' : 'Payment & Receipts'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ክፍያ ደረሰኝ ማስገባት እና የክፍያ ሁኔታ መከታተል' : 'Submit training payment receipts and track payment status'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="submit-receipt-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የክፍያ ደረሰኝ አስገባ' : 'Submit Payment Receipt'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="submit_receipt">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የተመዘገቡበት ፕሮግራም *' : 'Enrolled Program *'; ?></label>
                <select name="enrollment_id" class="form-control" required>
                    <option value=""><?php echo $is_am ? '-- ይምረጡ --' : '-- Select --'; ?></option>
                    <?php foreach ($my_enrollments as $me):
                        $ptitle = ($is_am && !empty($me['title_am'])) ? $me['title_am'] : ($me['title_en'] ?? '');
                        $pcode  = (string)($me['code'] ?? '');
                        $pstat  = (string)($me['payment_status'] ?? 'Unpaid');
                    ?>
                        <option value="<?php echo (int)$me['id']; ?>">
                            <?php echo htmlspecialchars(trim($pcode . ($ptitle !== '' ? ' — ' . $ptitle : '')) . ' (' . $pstat . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የደረሰኝ ቁጥር / ማጣቀሻ *' : 'Receipt Ref *'; ?></label>
                <input type="text" name="receipt_ref" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'መጠን (ETB)' : 'Amount (ETB)'; ?></label>
                <input type="number" name="amount_etb" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'አስቀምጥ / ላክ' : 'Save Receipt'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="receipts-history-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የገቡ ደረሰኞች' : 'Submitted Receipts'; ?> (<?php echo count($my_receipts); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ደረሰኝ ቁጥር' : 'Receipt Ref'; ?></th>
                        <th><?php echo $is_am ? 'መጠን (ETB)' : 'Amount (ETB)'; ?></th>
                        <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($my_receipts)): ?>
                        <?php foreach ($my_receipts as $mr):
                            $rid    = (int)$mr['id'];
                            $ptitle = ($is_am && !empty($mr['title_am'])) ? $mr['title_am'] : ($mr['title_en'] ?? '');
                            $pcode  = (string)($mr['code'] ?? '');
                        ?>
                        <tr id="rec-row-<?php echo $rid; ?>">
                            <td><strong><?php echo htmlspecialchars($pcode); ?></strong><?php echo ($ptitle !== '' ? ' — ' . htmlspecialchars((string)$ptitle) : ''); ?></td>
                            <td><?php echo htmlspecialchars((string)($mr['receipt_ref'] ?? '')); ?></td>
                            <td><?php echo number_format((float)($mr['amount_etb'] ?? 0), 2); ?></td>
                            <td><?php echo htmlspecialchars((string)($mr['created_at'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ደረሰኝ አልገባም።' : 'No receipts submitted yet.')); ?></td></tr>
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