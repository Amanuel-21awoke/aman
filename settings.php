<?php
require_once 'includes/functions.php';
require_once 'backend/db_connect.php';
check_login();

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg   = '';
$err   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'change_password') {
        $current_pw = (string)($_POST['current_password'] ?? '');
        $new_pw     = (string)($_POST['new_password'] ?? '');
        $confirm_pw = (string)($_POST['confirm_password'] ?? '');

        if ($current_pw === '' || $new_pw === '' || $confirm_pw === '') {
            $err = $is_am ? 'እባክዎ ሁሉንም የይለፍ ቃል ሳጥኖች ይሙሉ።' : 'Please fill in all password fields.';
        } elseif ($new_pw !== $confirm_pw) {
            $err = $is_am ? 'አዲሱ የይለፍ ቃል እና ማረጋገጫው አልተመሳሰሉም።' : 'New password and confirmation do not match.';
        } elseif (strlen($new_pw) < 6) {
            $err = $is_am ? 'አዲሱ የይለፍ ቃል ቢያንስ 6 ፊደላት/ቁጥሮች ሊኖሩት ይገባል።' : 'New password must be at least 6 characters long.';
        } else {
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $row  = null;
            if ($stmt) {
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if (!$row || !password_verify($current_pw, (string)$row['password_hash'])) {
                $err = $is_am ? 'የአሁኑ የይለፍ ቃል ትክክል አይደለም።' : 'Current password is incorrect.';
            } else {
                $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $ok  = false;
                if ($upd) {
                    $upd->bind_param("si", $new_hash, $uid);
                    $ok = $upd->execute();
                    $upd->close();
                }

                if ($ok) {
                    $msg = $is_am ? 'የይለፍ ቃል በተሳካ ሁኔታ ተቀይሯል።' : 'Password updated successfully!';
                } else {
                    $err = $is_am ? 'የይለፍ ቃሉን ማዘመን አልተቻለም።' : 'Failed to update password.';
                }
            }
        }
    } elseif ($act === 'set_language') {
        $chosen_lang = ($_POST['lang_choice'] ?? 'en') === 'am' ? 'am' : 'en';
        $_SESSION['lang'] = $chosen_lang;
        $msg = ($chosen_lang === 'am')
            ? 'የቋንቋ ምርጫ ወደ አማርኛ ተቀይሯል።'
            : 'Interface language switched to English.';
    }
}

$user_info = ['name' => '', 'email' => '', 'role' => ''];
$u_stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
if ($u_stmt) {
    $u_stmt->bind_param("i", $uid);
    $u_stmt->execute();
    $fetched_u = $u_stmt->get_result()->fetch_assoc();
    if ($fetched_u) {
        $user_info = $fetched_u;
    }
    $u_stmt->close();
}

$active_lang = $_SESSION['lang'] ?? 'en';

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo $is_am ? 'የመለያ ቅንብሮች' : 'Account Settings'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo htmlspecialchars($user_info['email'] ?? ''); ?>
                • <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($user_info['role'] ?? '')))); ?></strong>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="cards-grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); align-items:start;">
        <div class="card-box">
            <h3 style="color:var(--primary-color); margin-bottom:0.85rem;">
                <i class="bi bi-shield-lock"></i> <?php echo $is_am ? 'የይለፍ ቃል ይቀይሩ' : 'Change Password'; ?>
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label><?php echo $is_am ? 'የአሁኑ የይለፍ ቃል' : 'Current Password'; ?></label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><?php echo $is_am ? 'አዲስ የይለፍ ቃል (ቢያንስ 6 ቁምፊዎች)' : 'New Password (min 6 characters)'; ?></label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                </div>
                <div class="form-group">
                    <label><?php echo $is_am ? 'አዲሱን የይለፍ ቃል ያረጋግጡ' : 'Confirm New Password'; ?></label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-key"></i> <?php echo $is_am ? 'የይለፍ ቃል ያዘምኑ' : 'Update Password'; ?>
                </button>
            </form>
        </div>

        <div class="card-box">
            <h3 style="color:var(--primary-color); margin-bottom:0.85rem;">
                <i class="bi bi-translate"></i> <?php echo $is_am ? 'የስርዓት ቋንቋ' : 'Display Language'; ?>
            </h3>
            <p style="font-size:0.88rem; color:#475569; margin-bottom:1rem;">
                <?php echo $is_am
                    ? 'በዳሽቦርድ እና በስርዓቱ ገጾች የሚታየውን ቋንቋ ይምረጡ።'
                    : 'Choose your preferred language across the dashboard and portal pages.'; ?>
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="set_language">
                <div class="form-group">
                    <label><?php echo $is_am ? 'ቋንቋ' : 'Language'; ?></label>
                    <select name="lang_choice" class="form-control">
                        <option value="en" <?php echo $active_lang === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="am" <?php echo $active_lang === 'am' ? 'selected' : ''; ?>>አማርኛ (Amharic)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2"></i> <?php echo $is_am ? 'ቋንቋ አስቀምጥ' : 'Save Language'; ?>
                </button>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>