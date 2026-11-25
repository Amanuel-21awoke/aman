<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['events_coordinator', 'admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg   = '';
$err   = '';

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS role_workspace_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_name VARCHAR(50) NOT NULL,
            item_title VARCHAR(180) NOT NULL,
            target_ref VARCHAR(120) NULL,
            status_label VARCHAR(50) DEFAULT 'Logged',
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rwl_role (role_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'log_event') {
    $item_title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['item_title'] ?? ''))), ENT_QUOTES);
    $target_ref   = htmlspecialchars_decode(clean_input(trim((string)($_POST['target_ref'] ?? ''))), ENT_QUOTES);
    $status_label = htmlspecialchars_decode(clean_input(trim((string)($_POST['status_label'] ?? 'Scheduled'))), ENT_QUOTES);
    $notes        = htmlspecialchars_decode(clean_input(trim((string)($_POST['notes'] ?? ''))), ENT_QUOTES);

    if ($item_title === '') {
        $err = $is_am ? 'እባክዎ የዝግጅት ርዕስ ያስገቡ።' : 'Please enter an event title.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO role_workspace_logs (role_name, item_title, target_ref, status_label, notes, created_by)
            VALUES ('events_coordinator', ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("ssssi", $item_title, $target_ref, $status_label, $notes, $uid);
            if ($stmt->execute()) {
                $msg = $is_am ? 'የዝግጅት መዝገብ ተቀምጧል።' : 'Event schedule record saved.';
            } else {
                $err = $is_am ? 'መዝገቡን ማስቀመጥ አልተቻለም።' : 'Failed to save event record.';
            }
            $stmt->close();
        }
    }
}

$ev_logs = [];
try {
    $stmt_l = $conn->prepare("SELECT item_title, target_ref, status_label, notes, created_at FROM role_workspace_logs WHERE role_name = 'events_coordinator' ORDER BY id DESC LIMIT 12");
    if ($stmt_l) {
        $stmt_l->execute();
        $res_l = $stmt_l->get_result();
        while ($row = $res_l->fetch_assoc()) {
            $ev_logs[] = $row;
        }
        $stmt_l->close();
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
            <h1><?php echo $is_am ? 'የዝግጅት አስተባባሪ ዳሽቦርድ' : 'Events Coordinator Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ዝግጅቶች፣ የመመረቂያ መርሃ ግብሮች እና የአዳራሽ ክትትል' : 'Training Events, Ceremonies & Schedule Log'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የዝግጅት መዝገብ ያክሉ' : 'Log Event / Schedule'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end; margin-bottom:1rem;">
            <input type="hidden" name="action" value="log_event">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የዝግጅት ርዕስ *' : 'Event Title *'; ?></label>
                <input type="text" name="item_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ቦታ/ቀን ማጣቀሻ' : 'Venue / Date Ref'; ?></label>
                <input type="text" name="target_ref" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status_label" class="form-control">
                    <option value="Scheduled">Scheduled</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-calendar-event"></i> <?php echo $is_am ? 'መዝግብ' : 'Save Log'; ?>
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Item'; ?></th>
                        <th><?php echo $is_am ? 'ማጣቀሻ' : 'Reference'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ማስታወሻ' : 'Notes'; ?></th>
                        <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ev_logs)): ?>
                        <?php foreach ($ev_logs as $lg): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lg['item_title']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)($lg['target_ref'] ?? '-')); ?></td>
                            <td><?php echo status_badge($lg['status_label']); ?></td>
                            <td><?php echo htmlspecialchars((string)($lg['notes'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)$lg['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የዝግጅት መዝገብ አልተገኘም።' : 'No event records found.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>