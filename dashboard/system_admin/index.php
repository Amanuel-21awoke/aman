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

$role_filter = trim((string)($_GET['role_filter'] ?? 'ALL'));
if ($role_filter !== 'ALL' && !in_array($role_filter, $allowed_roles, true)) {
    $role_filter = 'ALL';
}
$search_q = trim((string)($_GET['q'] ?? ''));

$build_return_url = static function (string $anchor = '') use ($role_filter, $search_q): string {
    $params = [];
    if ($role_filter !== '' && $role_filter !== 'ALL') {
        $params['role_filter'] = $role_filter;
    }
    if ($search_q !== '') {
        $params['q'] = $search_q;
    }
    $qs = !empty($params) ? ('?' . http_build_query($params)) : '';
    return 'users.php' . $qs . $anchor;
};

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
        header("Location: " . $build_return_url('#add-user-panel'));
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
        header("Location: " . $build_return_url($anchor));
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
        header("Location: " . $build_return_url('#users-list-panel'));
        exit;
    }
}

$all_users_count = 0;
$users_list = [];

try {
    $rc = $conn->query("SELECT COUNT(*) AS c FROM users");
    if ($rc) {
        $all_users_count = (int)($rc->fetch_assoc()['c'] ?? 0);
    }

    $where_sql = [];
    $types = '';
    $params = [];

    if ($role_filter !== 'ALL') {
        $where_sql[] = "role = ?";
        $types .= 's';
        $params[] = $role_filter;
    }
    if ($search_q !== '') {
        $where_sql[] = "(name LIKE ? OR email LIKE ? OR role LIKE ?)";
        $types .= 'sss';
        $like = '%' . $search_q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT id, name, email, role, created_at FROM users";
    if (!empty($where_sql)) {
        $sql .= " WHERE " . implode(" AND ", $where_sql);
    }
    $sql .= " ORDER BY id ASC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $users_list[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $users_list[] = $row;
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
            <h1><?php echo $is_am ? 'ሁሉንም ተጠቃሚዎች አስተዳድር' : 'Manage All Users'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'በስርዓቱ ውስጥ ያሉ ሁሉንም ተጠቃሚዎች ማየት፣ አዲስ መጨመር፣ ሚና ማዘመን እና መሰረዝ' : 'View all system users, create accounts, update roles, and remove users'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'ጠቅላላ ተጠቃሚዎች' : 'Total System Users'; ?></p><h2><?php echo $all_users_count; ?></h2></div>
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'የሚታዩ ተጠቃሚዎች' : 'Showing Users'; ?></p><h2><?php echo count($users_list); ?></h2></div>
            <i class="bi bi-person-lines-fill"></i>
        </div>
    </div>

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
            <?php echo $is_am ? 'ሁሉም ተጠቃሚዎች' : 'All Users'; ?> (<?php echo count($users_list); ?> / <?php echo $all_users_count; ?>)
        </h3>

        <form method="GET" action="users.php#users-list-panel" style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:end; margin-bottom:1rem;">
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ሚና አጣራ' : 'Filter by Role'; ?></label>
                <select name="role_filter" class="form-control" style="min-width:170px;">
                    <option value="ALL" <?php echo ($role_filter === 'ALL') ? 'selected' : ''; ?>><?php echo $is_am ? 'ሁሉም ሚናዎች (All)' : 'All Roles'; ?></option>
                    <?php foreach ($allowed_roles as $rl): ?>
                        <option value="<?php echo $rl; ?>" <?php echo ($role_filter === $rl) ? 'selected' : ''; ?>><?php echo $rl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo $is_am ? 'ፈልግ (ስም / ኢሜይል)' : 'Search (Name / Email)'; ?></label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo $is_am ? 'ስም ወይም ኢሜይል...' : 'Name or email...'; ?>">
            </div>
            <div class="form-group" style="margin:0; display:flex; gap:0.4rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> <?php echo $is_am ? 'አሳይ' : 'Show'; ?>
                </button>
                <a href="users.php#users-list-panel" class="btn btn-outline">
                    <?php echo $is_am ? 'ሁሉንም አሳይ' : 'Reset All'; ?>
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
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
                            <td><?php echo $ur_id; ?></td>
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
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም ተጠቃሚ አልተገኘም።' : 'No users found.')); ?></td></tr>
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