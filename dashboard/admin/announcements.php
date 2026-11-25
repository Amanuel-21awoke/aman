<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);

$msg = $_SESSION['admin_ann_msg'] ?? '';
$err = $_SESSION['admin_ann_err'] ?? '';
unset($_SESSION['admin_ann_msg'], $_SESSION['admin_ann_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_announcement') {
        $title_en    = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_en'] ?? ''))), ENT_QUOTES);
        $title_am    = htmlspecialchars_decode(clean_input(trim((string)($_POST['title_am'] ?? ''))), ENT_QUOTES);
        $body_en     = htmlspecialchars_decode(clean_input(trim((string)($_POST['body_en'] ?? ''))), ENT_QUOTES);
        $body_am     = htmlspecialchars_decode(clean_input(trim((string)($_POST['body_am'] ?? ''))), ENT_QUOTES);
        $target_role = trim((string)($_POST['target_role'] ?? 'all'));

        if ($title_am === '' && $title_en !== '') { $title_am = $title_en; }
        if ($title_en === '' && $title_am !== '') { $title_en = $title_am; }
        if ($body_am === '' && $body_en !== '') { $body_am = $body_en; }
        if ($body_en === '' && $body_am !== '') { $body_en = $body_am; }

        if ($title_en === '' || $body_en === '') {
            $_SESSION['admin_ann_err'] = $is_am ? 'እባክዎ የማስታወቂያ ርዕስ እና ይዘት ያስገቡ።' : 'Please enter announcement title and body.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO announcements (title_en, title_am, body_en, body_am, target_role, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssssi", $title_en, $title_am, $body_en, $body_am, $target_role, $uid);
                    if ($stmt->execute()) {
                        $_SESSION['admin_ann_msg'] = $is_am ? 'ማስታወቂያው ተለጥፏል።' : 'Announcement published.';
                    } else {
                        $_SESSION['admin_ann_err'] = $is_am ? 'ማስታወቂያውን መለጠፍ አልተቻለም።' : 'Failed to publish announcement.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_ann_err'] = $e->getMessage();
            }
        }
        header("Location: announcements.php#post-announcement-panel");
        exit;
    } elseif ($act === 'delete_announcement') {
        $ann_id = (int)($_POST['announcement_id'] ?? 0);
        if ($ann_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $ann_id);
                    if ($stmt->execute()) {
                        $_SESSION['admin_ann_msg'] = $is_am ? 'ማስታወቂያው ተሰርዟል።' : 'Announcement deleted.';
                    } else {
                        $_SESSION['admin_ann_err'] = $is_am ? 'ማስታወቂያውን መሰረዝ አልተቻለም።' : 'Failed to delete announcement.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_ann_err'] = $e->getMessage();
            }
        }
        header("Location: announcements.php#announcements-list-panel");
        exit;
    }
}

$announcements = [];
try {
    $res = $conn->query("SELECT id, title_en, title_am, body_en, body_am, target_role, created_at FROM announcements ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $announcements[] = $row;
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
            <h1><?php echo htmlspecialchars($lang['post_announcement'] ?? ($is_am ? 'ማስታወቂያ ለጥፍ' : 'Post Announcement')); ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'ለሁሉም ወይም ለተወሰኑ የሚና ተጠቃሚዎች ማስታወቂያ ማስተላለፍ' : 'Publish notices to all users or specific roles'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="post-announcement-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ ማስታወቂያ ጻፍ' : 'New Announcement'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_announcement">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (English) *' : 'Title (English) *'; ?></label>
                <input type="text" name="title_en" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ርዕስ (አማርኛ)' : 'Title (Amharic)'; ?></label>
                <input type="text" name="title_am" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ዒላማ ሚና' : 'Target Role'; ?></label>
                <select name="target_role" class="form-control">
                    <option value="all"><?php echo $is_am ? 'ሁሉም' : 'All Users'; ?></option>
                    <option value="trainee">Trainee</option>
                    <option value="trainer">Trainer</option>
                    <option value="coordinator">Coordinator</option>
                </select>
            </div>
            <div class="form-group" style="margin:0; grid-column:1 / -1;">
                <label><?php echo $is_am ? 'ዝርዝር (English) *' : 'Body (English) *'; ?></label>
                <textarea name="body_en" class="form-control" rows="2" required></textarea>
            </div>
            <div class="form-group" style="margin:0; grid-column:1 / -1;">
                <label><?php echo $is_am ? 'ዝርዝር (አማርኛ)' : 'Body (Amharic)'; ?></label>
                <textarea name="body_am" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-megaphone"></i> <?php echo $is_am ? 'አስቀምጥ / ለጥፍ' : 'Save & Publish'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="announcements-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የተለጠፉ ማስታወቂያዎች' : 'Published Announcements'; ?> (<?php echo count($announcements); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ርዕስ' : 'Title'; ?></th>
                        <th><?php echo $is_am ? 'ዒላማ' : 'Target'; ?></th>
                        <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $a):
                            $atitle = ($is_am && !empty($a['title_am'])) ? $a['title_am'] : $a['title_en'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string)$atitle); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)($a['target_role'] ?? 'all')); ?></td>
                            <td><?php echo htmlspecialchars((string)($a['created_at'] ?? '')); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ማስታወቂያ መሰረዝ ይፈልጋሉ?' : 'Delete this announcement?'; ?>');">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?php echo (int)$a['id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ማስታወቂያ አልተገኘም።' : 'No announcements found.')); ?></td></tr>
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