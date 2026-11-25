<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin'], '../../');

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$filter_role = trim((string)($_GET['role'] ?? 'all'));
$panel_mode  = (($_GET['panel'] ?? '') === '1');
$edit_uid    = (int)($_GET['edit_id'] ?? 0);

$msg = $_SESSION['admin_users_msg'] ?? '';
$err = $_SESSION['admin_users_err'] ?? '';
unset($_SESSION['admin_users_msg'], $_SESSION['admin_users_err']);

$role_labels = [
    'trainee'              => ['en' => 'Trainee',               'am' => 'ሰልጣኝ'],
    'trainer'              => ['en' => 'Trainer',               'am' => 'አሰልጣኝ'],
    'coordinator'          => ['en' => 'Coordinator',           'am' => 'አስተባባሪ'],
    'finance_officer'      => ['en' => 'Finance Officer',       'am' => 'የፋይናንስ ባለሙያ'],
    'department_head'      => ['en' => 'Department Head',       'am' => 'የዲፓርትመንት ኃላፊ'],
    'monitoring_officer'   => ['en' => 'Monitoring Officer',    'am' => 'የክትትል ባለሙያ'],
    'support_staff'        => ['en' => 'Support Staff',         'am' => 'የድጋፍ ሰራተኛ'],
    'alumni_coordinator'   => ['en' => 'Alumni Coordinator',    'am' => 'የቀድሞ ተማሪዎች አስተባባሪ'],
    'college_dean'         => ['en' => 'College Dean',          'am' => 'የኮሌጅ ዲን'],
    'registrar'            => ['en' => 'Registrar',             'am' => 'ሬጅስትራር'],
    'lab_technician'       => ['en' => 'Lab Technician',        'am' => 'የላቦራቶሪ ቴክኒሻን'],
    'curriculum_developer' => ['en' => 'Curriculum Developer',  'am' => 'ሥርዓተ-ትምህርት አዘጋጅ'],
    'sponsor_liaison'      => ['en' => 'Sponsor Liaison',       'am' => 'የስፖንሰር/አጋር አስተባባሪ'],
    'certificate_officer'  => ['en' => 'Certificate Officer',   'am' => 'የሰርተፍኬት ባለሙያ'],
    'events_coordinator'   => ['en' => 'Events Coordinator',    'am' => 'የክስተት አስተባባሪ'],
    'qa_evaluator'         => ['en' => 'QA Evaluator',          'am' => 'የጥራት ገምጋሚ'],
    'admin'                => ['en' => 'Admin',                 'am' => 'አስተዳዳሪ'],
];

$allowed_roles    = array_keys($role_labels);
$allowed_statuses = ['active', 'inactive', 'suspended'];

$get_role_text = static function (string $role_key) use ($role_labels, $is_am): string {
    if (!isset($role_labels[$role_key])) {
        return $role_key;
    }
    return $is_am ? $role_labels[$role_key]['am'] : $role_labels[$role_key]['en'];
};

$build_users_redirect = static function (string $anchor = '#users-table-panel') use ($filter_role, $panel_mode, $edit_uid): string {
    $q = [];
    if ($filter_role !== '' && $filter_role !== 'all') {
        $q['role'] = $filter_role;
    }
    if ($panel_mode) {
        $q['panel'] = '1';
    }
    if ($edit_uid > 0) {
        $q['edit_id'] = $edit_uid;
    }
    $qs = !empty($q) ? ('?' . http_build_query($q)) : '';
    return 'users.php' . $qs . $anchor;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $anchor = '#users-table-panel';

    if ($act === 'add_user') {
        $anchor     = '#add-user-panel';
        $name       = htmlspecialchars_decode(clean_input(trim((string)($_POST['name'] ?? ''))), ENT_QUOTES);
        $email      = strtolower(trim((string)($_POST['email'] ?? '')));
        $password   = (string)($_POST['password'] ?? '');
        $role_val   = trim((string)($_POST['role'] ?? 'trainee'));
        $department = htmlspecialchars_decode(clean_input(trim((string)($_POST['department'] ?? ''))), ENT_QUOTES);
        $status_val = trim((string)($_POST['status'] ?? 'active'));

        if (!in_array($role_val, $allowed_roles, true)) {
            $role_val = 'trainee';
        }
        if (!in_array($status_val, $allowed_statuses, true)) {
            $status_val = 'active';
        }

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            $_SESSION['admin_users_err'] = $is_am
                ? 'እባክዎ ትክክለኛ ስም፣ ኢሜይል እና ቢያንስ 6 ፊደላት/ቁጥሮች ያሉት የይለፍ ቃል ያስገቡ።'
                : 'Please enter a valid name, email address, and password (min 6 characters).';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, department, status) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssss", $name, $email, $hash, $role_val, $department, $status_val);
                    if ($stmt->execute()) {
                        $_SESSION['admin_users_msg'] = $is_am ? 'አዲስ ተጠቃሚ ተጨምሯል።' : 'New user account created.';
                    } else {
                        $_SESSION['admin_users_err'] = $is_am ? 'ተጠቃሚውን ማከል አልተቻለም።' : 'Failed to create user account.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_users_err'] = $e->getMessage();
            }
        }
        header("Location: " . $build_users_redirect($anchor));
        exit;
    } elseif ($act === 'update_user') {
        $target_id  = (int)($_POST['user_id'] ?? 0);
        $anchor     = ($target_id > 0) ? '#edit-user-panel' : '#users-table-panel';
        $name       = htmlspecialchars_decode(clean_input(trim((string)($_POST['name'] ?? ''))), ENT_QUOTES);
        $email      = strtolower(trim((string)($_POST['email'] ?? '')));
        $role_val   = trim((string)($_POST['role'] ?? 'trainee'));
        $department = htmlspecialchars_decode(clean_input(trim((string)($_POST['department'] ?? ''))), ENT_QUOTES);
        $status_val = trim((string)($_POST['status'] ?? 'active'));

        if (!in_array($role_val, $allowed_roles, true)) {
            $role_val = 'trainee';
        }
        if (!in_array($status_val, $allowed_statuses, true)) {
            $status_val = 'active';
        }

        if ($target_id <= 0 || $name === '' || $email === '') {
            $_SESSION['admin_users_err'] = $is_am ? 'እባክዎ ትክክለኛ የተጠቃሚ መረጃ ያስገቡ።' : 'Please provide valid user details.';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, department = ?, status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssssi", $name, $email, $role_val, $department, $status_val, $target_id);
                    if ($stmt->execute()) {
                        $_SESSION['admin_users_msg'] = $is_am ? 'የተጠቃሚ መረጃ ተዘምኗል።' : 'User details updated.';
                    } else {
                        $_SESSION['admin_users_err'] = $is_am ? 'የተጠቃሚ መረጃ ማዘመን አልተቻለም።' : 'Failed to update user details.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_users_err'] = $e->getMessage();
            }
        }
        header("Location: " . $build_users_redirect($anchor));
        exit;
    } elseif ($act === 'delete_user') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        $curr_uid  = (int)($_SESSION['user_id'] ?? 0);
        if ($target_id <= 0 || $target_id === $curr_uid) {
            $_SESSION['admin_users_err'] = $is_am ? 'የራስዎን ንቁ መለያ መሰረዝ አይችሉም።' : 'You cannot delete your own active account.';
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $target_id);
                    if ($stmt->execute()) {
                        $_SESSION['admin_users_msg'] = $is_am ? 'ተጠቃሚው ተሰርዟል።' : 'User removed.';
                    } else {
                        $_SESSION['admin_users_err'] = $is_am ? 'ተጠቃሚውን መሰረዝ አልተቻለም።' : 'Failed to delete user.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['admin_users_err'] = $e->getMessage();
            }
        }
        header("Location: " . $build_users_redirect('#users-table-panel'));
        exit;
    }
}

$users_list = [];
$editing_user = null;

try {
    if ($filter_role !== '' && $filter_role !== 'all' && in_array($filter_role, $allowed_roles, true)) {
        $stmt = $conn->prepare("SELECT id, name, email, role, department, status, created_at FROM users WHERE role = ? ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param("s", $filter_role);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $users_list[] = $row;
                if ($edit_uid > 0 && (int)$row['id'] === $edit_uid) {
                    $editing_user = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $res = $conn->query("SELECT id, name, email, role, department, status, created_at FROM users ORDER BY id DESC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $users_list[] = $row;
                if ($edit_uid > 0 && (int)$row['id'] === $edit_uid) {
                    $editing_user = $row;
                }
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
            <h1><?php echo htmlspecialchars($lang['manage_users'] ?? ($is_am ? 'ተጠቃሚዎችን አስተዳድር' : 'Manage Users')); ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የተጠቃሚ መለያዎችን መፍጠር፣ ማርም እና የሚና ክትትል' : 'Create, edit, and manage user accounts and roles'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <?php if ($editing_user !== null): ?>
    <div class="card-box" id="edit-user-panel" style="margin-bottom:1.25rem; border:2px solid var(--primary-color);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
            <h3 style="color:var(--primary-color); margin:0;">
                <?php echo $is_am ? 'ተጠቃሚ አርም፦ ' : 'Edit User — '; ?>
                <?php echo htmlspecialchars((string)$editing_user['name']); ?>
            </h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="window.location.href='users.php#users-table-panel'">
                <?php echo $is_am ? 'ዝጋ' : 'Close'; ?>
            </button>
        </div>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; align-items:end;">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?php echo (int)$editing_user['id']; ?>">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሙሉ ስም *' : 'Full Name *'; ?></label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars((string)$editing_user['name']); ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ኢሜይል *' : 'Email *'; ?></label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars((string)$editing_user['email']); ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሚና' : 'Role'; ?></label>
                <select name="role" class="form-control">
                    <?php foreach ($allowed_roles as $rl): ?>
                        <option value="<?php echo htmlspecialchars($rl); ?>" <?php echo (($editing_user['role'] ?? '') === $rl) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($get_role_text($rl)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ክፍል / ዲፓርትመንት' : 'Department'; ?></label>
                <input type="text" name="department" class="form-control" value="<?php echo htmlspecialchars((string)($editing_user['department'] ?? '')); ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_statuses as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($editing_user['status'] ?? 'active') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-check2-circle"></i> <?php echo $is_am ? 'ለውጦችን አስቀምጥ' : 'Save Changes'; ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

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
                <label><?php echo $is_am ? 'የይለፍ ቃል (6+) *' : 'Password (6+) *'; ?></label>
                <input type="password" name="password" class="form-control" minlength="6" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሚና' : 'Role'; ?></label>
                <select name="role" class="form-control">
                    <?php foreach ($allowed_roles as $rl): ?>
                        <option value="<?php echo htmlspecialchars($rl); ?>" <?php echo ($filter_role === $rl) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($get_role_text($rl)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ክፍል / ዲፓርትመንት' : 'Department'; ?></label>
                <input type="text" name="department" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <?php foreach ($allowed_statuses as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-person-plus"></i> <?php echo $is_am ? 'ተጠቃሚ ጨምር' : 'Create User'; ?>
                </button>
            </div>
        </form>
    </div>

    <div class="card-box" id="users-table-panel">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
            <h3 style="color:var(--primary-color); margin:0;">
                <?php echo $is_am ? 'የተጠቃሚዎች ዝርዝር' : 'Users Directory'; ?> (<?php echo count($users_list); ?>)
            </h3>
            <form method="GET" style="display:flex; gap:0.4rem; align-items:center;">
                <?php if ($panel_mode): ?><input type="hidden" name="panel" value="1"><?php endif; ?>
                <select name="role" class="form-control" style="width:auto;" onchange="this.form.action='users.php#users-table-panel'; this.form.submit();">
                    <option value="all"><?php echo $is_am ? 'ሁሉም ሚናዎች' : 'All Roles'; ?></option>
                    <?php foreach ($allowed_roles as $rl): ?>
                        <option value="<?php echo htmlspecialchars($rl); ?>" <?php echo ($filter_role === $rl) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($get_role_text($rl)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ስም' : 'Name'; ?></th>
                        <th><?php echo $is_am ? 'ኢሜይል' : 'Email'; ?></th>
                        <th><?php echo $is_am ? 'ሚና' : 'Role'; ?></th>
                        <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users_list)): ?>
                        <?php foreach ($users_list as $u):
                            $uid_row = (int)$u['id'];
                            $r_key = (string)($u['role'] ?? '');
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string)$u['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)$u['email']); ?></td>
                            <td><?php echo htmlspecialchars($get_role_text($r_key)); ?></td>
                            <td><?php echo status_badge((string)($u['status'] ?? 'active')); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn btn-primary btn-sm"
                                        onclick="window.location.href='users.php?edit_id=<?php echo $uid_row; ?><?php echo ($filter_role !== '' && $filter_role !== 'all') ? '&role=' . urlencode($filter_role) : ''; ?><?php echo $panel_mode ? '&panel=1' : ''; ?>#edit-user-panel'">
                                    <i class="bi bi-pencil-square"></i> <?php echo $is_am ? 'አርም' : 'Edit'; ?>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን ተጠቃሚ መሰረዝ ይፈልጋሉ?' : 'Delete this user?'; ?>');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $uid_row; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ተጠቃሚ አልተገኘም።' : 'No users found.')); ?></td></tr>
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