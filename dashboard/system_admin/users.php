<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['system_admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg = $_SESSION['adm_usr_msg'] ?? '';
$err = $_SESSION['adm_usr_err'] ?? '';
unset($_SESSION['adm_usr_msg'], $_SESSION['adm_usr_err']);

$allowed_roles = [
    'system_admin', 'coordinator', 'trainer', 'trainee',
    'college_dean', 'department_head', 'finance_officer',
    'registrar', 'certificate_officer', 'qa_evaluator',
    'lab_technician', 'curriculum_developer', 'sponsor_liaison',
    'events_coordinator', 'monitoring_officer', 'support_staff',
    'alumni_coordinator'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_user') {
        $name  = htmlspecialchars_decode(clean_input(trim((string)($_POST['name'] ?? ''))), ENT_QUOTES);
        $email = htmlspecialchars_decode(clean_input(trim((string)($_POST['email'] ?? ''))), ENT_QUOTES);
        $role  = trim((string)($_POST['role'] ?? 'trainee'));
        $pass  = (string)($_POST['password'] ?? '');

        if (!in_array($role, $allowed_roles, true)) {
            $role = 'trainee';
        }

        if ($name === '' || $email === '' || strlen($pass) < 6) {
            $_SESSION['adm_usr_err'] = $is_am
                ? 'እባክዎ ስም፣ ኢሜይል እና ቢያንስ 6 ቁምፊ ያለው የይለፍ ቃል ያስገቡ።'
                : 'Please enter name, email, and a password of at least 6 characters.';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssss", $name, $email, $hash, $role);
                    if ($stmt->execute()) {
                        $_SESSION['adm_usr_msg'] = $is_am ? 'አዲስ ተጠቃሚ ተመዝግቧል።' : 'User account created.';
                    } else {
                        $_SESSION['adm_usr_err'] = $is_am ? 'ተጠቃሚውን መመዝገብ አልተቻለም።' : 'Failed to create user.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['adm_usr_err'] = $e->getMessage();
            }
        }
        header("Location: users.php#add-user-panel");
        exit;
    } elseif ($act === 'update_role') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        $new_role  = trim((string)($_POST['role'] ?? 'trainee'));
        if (!in_array($new_role, $allowed_roles, true)) {
            $new_role = 'trainee';
        }

        if ($target_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_role, $target_id);
                    if ($stmt->execute()) {
                        $_SESSION['adm_usr_msg'] = $is_am ? 'የተጠቃሚ ሚና ተዘምኗል።' : 'User role updated.';
                    } else {
                        $_SESSION['adm_usr_err'] = $is_am ? 'ሚናውን ማዘመን አልተቻለም።' : 'Failed to update user role.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['adm_usr_err'] = $e->getMessage();
            }
        }
        $anchor = ($target_id > 0) ? '#usr-row-' . $target_id : '#users-list-panel';
        header("Location: users.php" . $anchor);
        exit;
    } elseif ($act === 'delete_user') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        if ($target_id === $uid) {
            $_SESSION['adm_usr_err'] = $is_am ? 'የራስዎን መለያ መሰረዝ አይችሉም።' : 'You cannot delete your own account.';
        } elseif ($target_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $target_id);
                    if ($stmt->execute()) {
                        $_SESSION['adm_usr_msg'] = $is_am ? 'ተጠቃሚው ተሰርዟል።' : 'User account deleted.';
                    } else {
                        $_SESSION['adm_usr_err'] = $is_am ? 'ተጠቃሚውን መሰረዝ አልተቻለም።' : 'Failed to delete user.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['adm_usr_err'] = $e->getMessage();
            }
        }
        header("Location: users.php#users-list-panel");
        exit;
    }
}

$users_list = [];
try {
    $res = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users_list[] = $row;
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
            <h1><?php echo $is_am ? 'የተጠቃሚዎች አስተዳደር' : 'User Management'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የስርዓት ተጠቃሚዎችን መፍጠር፣ ሚና መቀየር እና መለያዎችን ማስተዳደር' : 'Create user accounts, assign roles, and manage access'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" id="add-user-panel" style="margin-bottom:1.25rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'አዲስ ተጠቃሚ ጨምር' : 'Add New User'; ?>
        </h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="add_user">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሙሉ ስም *' : 'Full Name *'; ?></label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ኢሜይል *' : 'Email *'; ?></label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሚና / Role' : 'Role'; ?></label>
                <select name="role" class="form-control">
                    <?php foreach ($allowed_roles as $rl): ?>
                        <option value="<?php echo $rl; ?>"><?php echo $rl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'የይለፍ ቃል *' : 'Password *'; ?></label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-person-plus"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Create User'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="users-list-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የተጠቃሚዎች ዝርዝር' : 'Users List'; ?> (<?php echo count($users_list); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ተጠቃሚ' : 'User'; ?></th>
                        <th><?php echo $is_am ? 'ወቅታዊ ሚና' : 'Current Role'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users_list)): ?>
                        <?php foreach ($users_list as $u):
                            $ur_id = (int)$u['id'];
                        ?>
                        <tr id="usr-row-<?php echo $ur_id; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)$u['name']); ?></strong>
                                <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars((string)$u['email']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars((string)$u['role']); ?></td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?php echo $ur_id; ?>">
                                    <select name="role" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_roles as $rl): ?>
                                            <option value="<?php echo $rl; ?>" <?php echo (($u['role'] ?? '') === $rl) ? 'selected' : ''; ?>><?php echo $rl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check2"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save'; ?>
                                    </button>
                                </form>
                                <?php if ($ur_id !== $uid): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ተጠቃሚ መሰረዝ ይፈልጋሉ?' : 'Delete this user?'; ?>');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $ur_id; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ተጠቃሚ አልተገኘም።' : 'No users found.')); ?></td></tr>
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