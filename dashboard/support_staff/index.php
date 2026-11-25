<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['support_staff'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['sup_msg'] ?? '';
$err = $_SESSION['sup_err'] ?? '';
unset($_SESSION['sup_msg'], $_SESSION['sup_err']);

$allowed_ticket_status = ['Open', 'In Progress', 'Resolved'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requester_name VARCHAR(150) NOT NULL,
            subject VARCHAR(180) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'Open',
            resolution_notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_ticket') {
        $req_name = htmlspecialchars_decode(clean_input(trim((string)($_POST['requester_name'] ?? ''))), ENT_QUOTES);
        $subject  = htmlspecialchars_decode(clean_input(trim((string)($_POST['subject'] ?? ''))), ENT_QUOTES);
        $st_val   = trim((string)($_POST['status'] ?? 'Open'));
        if (!in_array($st_val, $allowed_ticket_status, true)) {
            $st_val = 'Open';
        }
        $notes    = htmlspecialchars_decode(clean_input(trim((string)($_POST['resolution_notes'] ?? ''))), ENT_QUOTES);

        if ($req_name === '' || $subject === '') {
            $_SESSION['sup_err'] = $is_am ? 'እባክዎ የጠያቂ ስም እና የድጋፍ ጥያቄ ርዕስ ያስገቡ።' : 'Please enter requester name and subject.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO support_tickets (requester_name, subject, status, resolution_notes, created_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssi", $req_name, $subject, $st_val, $notes, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['sup_msg'] = $is_am ? 'የድጋፍ መዝገብ ተቀምጧል።' : 'Support ticket saved.';
                    } else {
                        $_SESSION['sup_err'] = $is_am ? 'መዝገቡን ማስቀመጥ አልተቻለም።' : 'Failed to save support ticket.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['sup_err'] = $e->getMessage();
            }
        }
        header("Location: index.php#add-ticket-panel");
        exit;
    } elseif ($act === 'update_ticket_status') {
        $tkt_id = (int)($_POST['ticket_id'] ?? 0);
        $st_val = trim((string)($_POST['status'] ?? 'Open'));
        if (!in_array($st_val, $allowed_ticket_status, true)) {
            $st_val = 'Open';
        }

        if ($tkt_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $st_val, $tkt_id);
                    if ($stmt->execute()) {
                        $_SESSION['sup_msg'] = $is_am ? 'የድጋፍ ሁኔታ ተዘምኗል።' : 'Ticket status updated.';
                    } else {
                        $_SESSION['sup_err'] = $is_am ? 'ሁኔታውን ማዘመን አልተቻለም።' : 'Failed to update ticket status.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['sup_err'] = $e->getMessage();
            }
        }
        $anchor = ($tkt_id > 0) ? '#tkt-row-' . $tkt_id : '#tickets-list-panel';
        header("Location: index.php" . $anchor);
        exit;
    }
}

$tickets     = [];
$open_cnt    = 0;
$resolved_cnt = 0;

try {
    $rt = $conn->query("SELECT id, requester_name, subject, status, resolution_notes, created_at FROM support_tickets ORDER BY id DESC");
    if ($rt) {
        while ($t = $rt->fetch_assoc()) {
            $tickets[] = $t;
            $st = (string)($t['status'] ?? 'Open');
            if ($st === 'Resolved') {
                $resolved_cnt++;
            } else {
                $open_cnt++;
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
            <h1><?php echo $is_am ? 'የድጋፍ ሰራተኛ ዳሽቦርድ' : 'Support Staff Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የተጠቃሚ የድጋፍ ጥያቄዎችን መመዝገብ እና የመፍትሄ ሁኔታ ማዘመን' : 'Log support requests and update resolution status'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#b45309,#f59e0b);">
            <div><p><?php echo $is_am ? 'ክፍት / በሂደት' : 'Open / In Progress'; ?></p><h2><?php echo $open_cnt; ?></h2></div>
            <i class="bi bi-headset"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'የተፈቱ' : 'Resolved'; ?></p><h2><?php echo $resolved_cnt; ?></h2></div>
            <i class="bi bi-check2-circle"></i>
        </div>
    </div>

    <div class="card-box" id="add-ticket-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ የድጋፍ መዝገብ ጨምር' : 'Log Support Request'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_ticket">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የጠያቂ ስም *' : 'Requester Name *'; ?></label>
                <input type="text" name="requester_name" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ / ጉዳይ *' : 'Subject *'; ?></label>
                <input type="text" name="subject" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_ticket_status as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></label>
                <input type="text" name="resolution_notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Ticket'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="tickets-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የድጋፍ ጥያቄዎች ዝርዝር' : 'Support Tickets'; ?> (<?php echo count($tickets); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ጠያቂ' : 'Requester'; ?></th>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Subject'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tickets)): ?>
                        <?php foreach ($tickets as $t):
                            $tid = (int)$t['id'];
                        ?>
                        <tr id="tkt-row-<?php echo $tid; ?>">
                            <td><strong><?php echo htmlspecialchars((string)$t['requester_name']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars((string)$t['subject']); ?>
                                <?php if (!empty($t['resolution_notes'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$t['resolution_notes']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo status_badge((string)($t['status'] ?? 'Open')); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_ticket_status">
                                    <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                                    <select name="status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_ticket_status as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo (($t['status'] ?? 'Open') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የድጋፍ መዝገብ አልተገኘም።' : 'No support tickets found.')); ?></td></tr>
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