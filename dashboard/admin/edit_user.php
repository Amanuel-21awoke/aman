<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin']);

$all_roles = [
    'trainee'              => 'Trainee',
    'trainer'              => 'Trainer',
    'coordinator'          => 'Coordinator',
    'finance_officer'      => 'Finance Office',
    'admin'                => 'Admin',
    'qa_evaluator'         => 'QA Evaluator',
    'registrar'            => 'Registrar',
    'lab_technician'       => 'Lab Technician',
    'curriculum_developer' => 'Curriculum Developer',
    'sponsor_liaison'      => 'Sponsor Liaison',
    'certificate_officer'  => 'Certificate Officer',
    'events_coordinator'   => 'Events Coordinator',
    'department_head'      => 'Department Head',
    'monitoring_officer'   => 'Monitoring Officer',
    'support_staff'        => 'Support Staff',
    'alumni_coordinator'   => 'Alumni Coordinator',
];

$uid = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
$msg = '';
$err_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $name = clean_input($_POST['name'] ?? '');
    $email = strtolower(trim(clean_input($_POST['email'] ?? '')));
    $role = $_POST['role'] ?? 'trainee';
    $dept = clean_input($_POST['department'] ?? 'Injibara University');
    $st = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
    $new_pw = trim($_POST['new_password'] ?? '');
    if (!array_key_exists($role, $all_roles)) $role = 'trainee';

    if ($uid > 0 && $name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($new_pw !== '') {
            $pw_hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?, department = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $name, $email, $pw_hash, $role, $dept, $st, $uid);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, department = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $name, $email, $role, $dept, $st, $uid);
        }
        if ($stmt->execute()) {
            $msg = "User updated successfully.";
        } else {
            $err_msg = "Failed to update user record.";
        }
        $stmt->close();
    } else {
        $err_msg = "Please provide valid name and email.";
    }
}

$stmt_u = $conn->prepare("SELECT id, name, email, role, department, status, created_at FROM users WHERE id = ?");
$stmt_u->bind_param("i", $uid);
$stmt_u->execute();
$user = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተጠቃሚ መረጃ ማስተካከያ ገጽ' : 'Edit User Workspace'; ?></h1>
            <p style="color:#64748b;">Dedicated page for updating user account details, role, department, status, or password.</p>
        </div>
        <a href="users.php" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Back to Manage Users</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err_msg): ?><div class="alert alert-error"><?php echo htmlspecialchars($err_msg); ?></div><?php endif; ?>

    <?php if ($user): ?>
    <div class="card-box">
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; align-items:end;">
            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሙሉ ስም' : 'Full Name'; ?></label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ኢሜይል' : 'Email'; ?></label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሚና (16 ሚናዎች)' : 'Role (16 Roles)'; ?></label>
                <select name="role" class="form-control">
                    <?php foreach ($all_roles as $rk => $rtitle): ?>
                        <option value="<?php echo htmlspecialchars($rk); ?>" <?php echo ($user['role'] === $rk) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rtitle); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሥራ ክፍል / ዲፓርትመንት' : 'Department'; ?></label>
                <input type="text" name="department" class="form-control" value="<?php echo htmlspecialchars($user['department']); ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <option value="active" <?php echo ($user['status'] === 'active') ? 'selected' : ''; ?>><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ንቁ' : 'active'; ?></option>
                    <option value="inactive" <?php echo ($user['status'] === 'inactive') ? 'selected' : ''; ?>><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ንቁ ያልሆነ' : 'inactive'; ?></option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አዲስ የይለፍ ቃል (አማራጭ)' : 'New Password (Optional)'; ?></label>
                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
            </div>
            <div style="grid-column:1/-1; display:flex; gap:0.6rem;">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
                <a href="users.php" class="btn btn-outline"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሰርዝ' : 'Cancel'; ?></a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="card-box">
        <p>User record not found.</p>
        <a href="users.php" class="btn btn-primary btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ወደ ተጠቃሚ አስተዳደር ተመለስ' : 'Return to Manage Users'; ?></a>
    </div>
    <?php endif; ?>
</div>
<?php include '../../includes/footer.php'; ?>
