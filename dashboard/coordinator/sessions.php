<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['coordinator'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$msg   = $_SESSION['coord_ses_msg'] ?? '';
$err   = $_SESSION['coord_ses_err'] ?? '';
unset($_SESSION['coord_ses_msg'], $_SESSION['coord_ses_err']);

$self_file = basename((string)($_SERVER['PHP_SELF'] ?? 'sessions.php'));

/**
 * Ensure `training_sessions` exists and self-heal missing columns (`program_id`,
 * `session_title`, `session_date`, `venue`) so queries never fail with `Unknown column`.
 */
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `training_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `program_id` INT NOT NULL,
            `session_title` VARCHAR(180) NOT NULL,
            `session_date` DATE NOT NULL,
            `venue` VARCHAR(150) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ses_cols = [];
    $r_ses = $conn->query("SHOW COLUMNS FROM `training_sessions`");
    if ($r_ses) {
        while ($c = $r_ses->fetch_assoc()) {
            $ses_cols[] = (string)($c['Field'] ?? '');
        }
    }

    if (!in_array('program_id', $ses_cols, true)) {
        $conn->query("ALTER TABLE `training_sessions` ADD COLUMN `program_id` INT NOT NULL DEFAULT 0");
    }
    if (!in_array('session_title', $ses_cols, true)) {
        $conn->query("ALTER TABLE `training_sessions` ADD COLUMN `session_title` VARCHAR(180) NOT NULL DEFAULT ''");
    }
    if (!in_array('session_date', $ses_cols, true)) {
        $conn->query("ALTER TABLE `training_sessions` ADD COLUMN `session_date` DATE NULL");
    }
    if (!in_array('venue', $ses_cols, true)) {
        $conn->query("ALTER TABLE `training_sessions` ADD COLUMN `venue` VARCHAR(150) NULL");
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_session') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['session_title'] ?? ''))), ENT_QUOTES);
        $s_date  = trim((string)($_POST['session_date'] ?? ''));
        $venue   = htmlspecialchars_decode(clean_input(trim((string)($_POST['venue'] ?? ''))), ENT_QUOTES);

        $redirect_anchor = '#add-session-panel';

        if ($prog_id <= 0 || $title === '' || $s_date === '') {
            $_SESSION['coord_ses_err'] = $is_am ? 'እባክዎ ፕሮግራም፣ የክፍለ-ጊዜ ርዕስ እና ቀን ያስገቡ።' : 'Please select a program, session title, and date.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO `training_sessions` (`program_id`, `session_title`, `session_date`, `venue`) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isss", $prog_id, $title, $s_date, $venue);
                    if ($stmt->execute()) {
                        $new_id = (int)$conn->insert_id;
                        $redirect_anchor = ($new_id > 0) ? ('#ses-row-' . $new_id) : '#sessions-list-panel';
                        $_SESSION['coord_ses_msg'] = $is_am ? 'የስልጠና ክፍለ-ጊዜ ተቀምጧል።' : 'Training session scheduled.';
                    } else {
                        $_SESSION['coord_ses_err'] = $is_am ? 'ክፍለ-ጊዜውን ማስቀመጥ አልተቻለም።' : 'Failed to schedule session.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['coord_ses_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . $redirect_anchor);
        exit;
    } elseif ($act === 'delete_session') {
        $ses_id = (int)($_POST['session_id'] ?? 0);
        if ($ses_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM `training_sessions` WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $ses_id);
                    if ($stmt->execute()) {
                        $_SESSION['coord_ses_msg'] = $is_am ? 'ክፍለ-ጊዜው ተሰርዟል።' : 'Session removed.';
                    } else {
                        $_SESSION['coord_ses_err'] = $is_am ? 'ክፍለ-ጊዜውን መሰረዝ አልተቻለም።' : 'Failed to delete session.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['coord_ses_err'] = $e->getMessage();
            }
        }

        header("Location: " . $self_file . "#sessions-list-panel");
        exit;
    }
}

$programs = [];
$sessions = [];

try {
    $rp = $conn->query("SELECT id, code, title_en, title_am FROM `training_programs` ORDER BY id DESC");
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $programs[] = $p;
        }
    }

    $rs = $conn->query("
        SELECT s.id, s.session_title, s.session_date, s.venue, p.code, p.title_en, p.title_am
        FROM `training_sessions` s
        LEFT JOIN `training_programs` p ON s.program_id = p.id
        ORDER BY s.session_date DESC, s.id DESC
    ");
    if ($rs) {
        while ($s = $rs->fetch_assoc()) {
            $sessions[] = $s;
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
            <h1><?php echo htmlspecialchars($lang['manage_sessions'] ?? ($is_am ? 'ክፍለ-ጊዜዎችን አስተዳድር' : 'Manage Sessions')); ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስልጠና ክፍለ-ጊዜዎችን መርሃ-ግብር ማዘጋጀት እና ማስተዳደር' : 'Schedule and manage training sessions'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="add-session-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ ክፍለ-ጊዜ ጨምር' : 'Schedule New Session'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_session">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፕሮግራም *' : 'Program *'; ?></label>
                <select name="program_id" class="form-control" required>
                    <option value=""><?php echo $is_am ? '-- ይምረጡ --' : '-- Select --'; ?></option>
                    <?php foreach ($programs as $p):
                        $pt = ($is_am && !empty($p['title_am'])) ? $p['title_am'] : ($p['title_en'] ?? '');
                    ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars((string)(($p['code'] ?? '') . ' — ' . $pt)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ *' : 'Session Title *'; ?></label>
                <input type="text" name="session_title" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ቀን *' : 'Date *'; ?></label>
                <input type="date" name="session_date" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ቦታ / አዳራሽ' : 'Venue'; ?></label>
                <input type="text" name="venue" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-calendar-plus"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save Session'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="sessions-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የተመደቡ ክፍለ-ጊዜዎች' : 'Scheduled Sessions'; ?> (<?php echo count($sessions); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Session'; ?></th>
                        <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                        <th><?php echo $is_am ? 'ቦታ' : 'Venue'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sessions)): ?>
                        <?php foreach ($sessions as $s):
                            $sid = (int)$s['id'];
                            $pt  = ($is_am && !empty($s['title_am'])) ? $s['title_am'] : ($s['title_en'] ?? '');
                        ?>
                        <tr id="ses-row-<?php echo $sid; ?>">
                            <td><strong><?php echo htmlspecialchars((string)($s['code'] ?? '')); ?></strong><?php echo ($pt !== '' ? ' — ' . htmlspecialchars((string)$pt) : ''); ?></td>
                            <td><?php echo htmlspecialchars((string)($s['session_title'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($s['session_date'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($s['venue'] ?? '-')); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ክፍለ-ጊዜ መሰረዝ ይፈልጋሉ?' : 'Delete this session?'; ?>');">
                                    <input type="hidden" name="action" value="delete_session">
                                    <input type="hidden" name="session_id" value="<?php echo $sid; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ክፍለ-ጊዜ አልተገኘም።' : 'No sessions found.')); ?></td></tr>
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