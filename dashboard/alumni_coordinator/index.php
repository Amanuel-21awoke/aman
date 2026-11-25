<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['alumni_coordinator'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['alu_msg'] ?? '';
$err = $_SESSION['alu_err'] ?? '';
unset($_SESSION['alu_msg'], $_SESSION['alu_err']);

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS alumni_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            program_title VARCHAR(180) NOT NULL,
            graduation_year VARCHAR(40) NULL,
            contact_info VARCHAR(150) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_alumni') {
        $full_name = htmlspecialchars_decode(clean_input(trim((string)($_POST['full_name'] ?? ''))), ENT_QUOTES);
        $prog_t    = htmlspecialchars_decode(clean_input(trim((string)($_POST['program_title'] ?? ''))), ENT_QUOTES);
        $grad_yr   = htmlspecialchars_decode(clean_input(trim((string)($_POST['graduation_year'] ?? ''))), ENT_QUOTES);
        $contact   = htmlspecialchars_decode(clean_input(trim((string)($_POST['contact_info'] ?? ''))), ENT_QUOTES);

        if ($full_name === '' || $prog_t === '') {
            $_SESSION['alu_err'] = $is_am ? 'እባክዎ የተመራቂ ስም እና የፕሮግራም ርዕስ ያስገቡ።' : 'Please enter alumni name and program title.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO alumni_records (full_name, program_title, graduation_year, contact_info, created_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssi", $full_name, $prog_t, $grad_yr, $contact, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['alu_msg'] = $is_am ? 'የአሉምናይ መዝገብ ተቀምጧል።' : 'Alumni record saved.';
                    } else {
                        $_SESSION['alu_err'] = $is_am ? 'መዝገቡን ማስቀመጥ አልተቻለም።' : 'Failed to save alumni record.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['alu_err'] = $e->getMessage();
            }
        }
        header("Location: index.php#add-alumni-panel");
        exit;
    } elseif ($act === 'delete_alumni') {
        $alu_id = (int)($_POST['alumni_id'] ?? 0);
        if ($alu_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM alumni_records WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $alu_id);
                    if ($stmt->execute()) {
                        $_SESSION['alu_msg'] = $is_am ? 'የአሉምናይ መዝገብ ተሰርዟል።' : 'Alumni record removed.';
                    } else {
                        $_SESSION['alu_err'] = $is_am ? 'መዝገቡን መሰረዝ አልተቻለም።' : 'Failed to delete alumni record.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['alu_err'] = $e->getMessage();
            }
        }
        header("Location: index.php#alumni-list-panel");
        exit;
    }
}

$alumni_list = [];
try {
    $ra = $conn->query("SELECT id, full_name, program_title, graduation_year, contact_info, created_at FROM alumni_records ORDER BY id DESC");
    if ($ra) {
        while ($row = $ra->fetch_assoc()) {
            $alumni_list[] = $row;
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
            <h1><?php echo $is_am ? 'የአሉምናይ አስተባባሪ ዳሽቦርድ' : 'Alumni Coordinator Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የተመራቂ ሰልጣኞች መረጃ መመዝገብ እና የአሉምናይ ግንኙነት ማስተዳደር' : 'Maintain graduate records and alumni engagement directory'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'የተመዘገቡ አሉምናይ' : 'Registered Alumni'; ?></p><h2><?php echo count($alumni_list); ?></h2></div>
            <i class="bi bi-mortarboard"></i>
        </div>
    </div>

    <div class="card-box" id="add-alumni-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ አሉምናይ መዝግብ' : 'Add Alumni Record'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_alumni">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሙሉ ስም *' : 'Full Name *'; ?></label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የተጠናቀቀ ፕሮግራም *' : 'Program Title *'; ?></label>
                <input type="text" name="program_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የምረቃ ዓመት' : 'Graduation Year'; ?></label>
                <input type="text" name="graduation_year" class="form-control" placeholder="2018 E.C. / 2026">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ስልክ / ኢሜይል' : 'Contact Info'; ?></label>
                <input type="text" name="contact_info" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-person-check"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Alumni'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="alumni-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የአሉምናይ ዝርዝር' : 'Alumni Directory'; ?> (<?php echo count($alumni_list); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ስም' : 'Name'; ?></th>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ዓመት / መገኛ' : 'Year & Contact'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($alumni_list)): ?>
                        <?php foreach ($alumni_list as $al):
                            $aid = (int)$al['id'];
                        ?>
                        <tr id="alu-row-<?php echo $aid; ?>">
                            <td><strong><?php echo htmlspecialchars((string)$al['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)$al['program_title']); ?></td>
                            <td>
                                <?php echo htmlspecialchars((string)($al['graduation_year'] ?? '-')); ?>
                                <?php if (!empty($al['contact_info'])): ?>
                                    <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$al['contact_info']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን የአሉምናይ መዝገብ መሰረዝ ይፈልጋሉ?' : 'Delete this alumni record?'; ?>');">
                                    <input type="hidden" name="action" value="delete_alumni">
                                    <input type="hidden" name="alumni_id" value="<?php echo $aid; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የአሉምናይ መዝገብ አልተገኘም።' : 'No alumni records found.')); ?></td></tr>
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