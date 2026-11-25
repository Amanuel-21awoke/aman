<?php
require_once 'includes/functions.php';
require_once 'backend/db_connect.php';
check_login();

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg   = '';
$err   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_name    = trim((string)($_POST['name'] ?? ''));
    $raw_address = trim((string)($_POST['address'] ?? ''));
    $raw_bio     = trim((string)($_POST['bio'] ?? ''));

    $name    = htmlspecialchars_decode(clean_input($raw_name), ENT_QUOTES);
    $phone   = sanitize_phone_10($_POST['phone'] ?? '');
    $address = htmlspecialchars_decode(clean_input($raw_address), ENT_QUOTES);
    $bio     = htmlspecialchars_decode(clean_input($raw_bio), ENT_QUOTES);

    $current_photo = get_profile_image($conn, $uid);
    $photo_to_save = $current_photo;

    if ($name === '') {
        $err = $lang['fill_all_fields'] ?? ($is_am ? 'እባክዎ ሙሉ ስም ያስገቡ።' : 'Please enter your full name.');
    }

    if ($err === '' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $err = $is_am ? 'የመገለጫ ፎቶ ጭነት አልተሳካም።' : 'Failed to upload profile image.';
        } else {
            $tmp_path   = $_FILES['profile_photo']['tmp_name'];
            $orig_name  = $_FILES['profile_photo']['name'];
            $size_bytes = (int)$_FILES['profile_photo']['size'];
            $ext        = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            $finfo_ok = true;
            if (function_exists('finfo_open')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = (string)@finfo_file($finfo, $tmp_path);
                    @finfo_close($finfo);
                    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if ($mime !== '' && !in_array($mime, $allowed_mimes, true)) {
                        $finfo_ok = false;
                    }
                }
            }

            if (!$finfo_ok || !in_array($ext, $allowed_ext, true)) {
                $err = $is_am
                    ? 'የሚፈቀዱ የፎቶ አይነቶች፦ JPG፣ JPEG፣ PNG፣ GIF፣ WEBP ናቸው።'
                    : 'Allowed image formats: JPG, JPEG, PNG, GIF, WEBP.';
            } elseif ($size_bytes <= 0 || $size_bytes > 3 * 1024 * 1024) {
                $err = $is_am
                    ? 'የመገለጫ ፎቶ መጠን ከ 3MB መብለጥ የለበትም።'
                    : 'Profile image must be 3MB or smaller.';
            } else {
                $upload_dir = __DIR__ . '/uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0775, true);
                }
                $new_filename = 'user_' . $uid . '_' . time() . '.' . $ext;
                $dest_full    = $upload_dir . $new_filename;
                if (move_uploaded_file($tmp_path, $dest_full)) {
                    $photo_to_save = 'uploads/profiles/' . $new_filename;
                } else {
                    $err = $is_am ? 'የመገለጫ ፎቶውን ማስቀመጥ አልተቻለም።' : 'Failed to upload profile image.';
                }
            }
        }
    }

    if ($err === '') {
        $u = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
        $u_ok = false;
        if ($u) {
            $u->bind_param("si", $name, $uid);
            $u_ok = $u->execute();
            $u->close();
        }

        $p = $conn->prepare("INSERT INTO profiles (user_id, phone, address, bio, photo_url) VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE phone = VALUES(phone), address = VALUES(address), bio = VALUES(bio), photo_url = VALUES(photo_url)");
        $p_ok = false;
        if ($p) {
            $p->bind_param("issss", $uid, $phone, $address, $bio, $photo_to_save);
            $p_ok = $p->execute();
            $p->close();
        }

        if ($u_ok && $p_ok) {
            $_SESSION['name'] = $name;
            $msg = $lang['success_update'] ?? ($is_am ? 'መገለጫ በተሳካ ሁኔታ ተዘምኗል።' : 'Profile updated successfully!');
        } else {
            $err = $lang['error_occurred'] ?? ($is_am ? 'መረጃውን ማዘመን አልተቻለም።' : 'Failed to update profile details.');
        }
    }
}

$user = [
    'name'       => '',
    'email'      => '',
    'role'       => '',
    'department' => '',
    'phone'      => '',
    'address'    => '',
    'bio'        => '',
    'photo_url'  => ''
];

$stmt = $conn->prepare("SELECT u.name, u.email, u.role, u.department, p.phone, p.address, p.bio, p.photo_url
                       FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $fetched = $stmt->get_result()->fetch_assoc();
    if ($fetched) {
        $user = $fetched;
    }
    $stmt->close();
}

$raw_photo = (string)($user['photo_url'] ?? '');
if ($raw_photo === 'assets/image.png') {
    $raw_photo = 'assets/image.png';
}
$display_photo = ($raw_photo !== '' && file_exists(__DIR__ . '/' . $raw_photo))
    ? $raw_photo
    : 'assets/image.png';

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo htmlspecialchars($lang['profile'] ?? ($is_am ? 'የተጠቃሚ መገለጫ' : 'User Profile')); ?></h1>
            <p style="color:#64748b;">
                <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                • <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($user['role'] ?? '')))); ?></strong>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card-box" style="max-width:740px;">
        <div style="display:flex; align-items:center; gap:1.25rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;">
            <img src="<?php echo htmlspecialchars($display_photo); ?>" alt="<?php echo $is_am ? 'የመገለጫ ፎቶ' : 'Profile Photo'; ?>"
                 style="width:88px; height:88px; border-radius:50%; object-fit:cover; border:3px solid var(--secondary-color);"
                 onerror="this.src='assets/default_avatar.svg'">
            <div>
                <h3 style="color:var(--primary-color); margin-bottom:0.25rem;"><?php echo htmlspecialchars($user['name'] ?? ''); ?></h3>
                <p style="font-size:0.85rem; color:#64748b; margin:0;"><?php echo htmlspecialchars($user['department'] ?? ''); ?></p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label><?php echo $is_am ? 'የመገለጫ ፎቶ (JPG, PNG, GIF, WEBP — እስከ 3MB)' : 'Profile Image (JPG, PNG, GIF, WEBP — max 3MB)'; ?></label>
                <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp" class="form-control">
            </div>
            <div class="form-group">
                <label><?php echo htmlspecialchars($lang['full_name'] ?? ($is_am ? 'ሙሉ ስም' : 'Full Name')); ?></label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label><?php echo htmlspecialchars($lang['phone'] ?? ($is_am ? 'ስልክ ቁጥር' : 'Phone Number')); ?></label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" maxlength="10" pattern="\d{1,10}">
            </div>
            <div class="form-group">
                <label><?php echo $is_am ? 'አድራሻ' : 'Address'; ?></label>
                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="<?php echo $is_am ? 'ለምሳሌ፡ እንጅባራ ወይም ፍኖተሰላም' : 'e.g., Injibara or Finote Selam'; ?>">
            </div>
            <div class="form-group">
                <label><?php echo $is_am ? 'አጭር መግለጫ / የኃላፊነት ማስታወሻ' : 'Bio / Role Notes'; ?></label>
                <textarea name="bio" class="form-control" rows="3" placeholder="<?php echo $is_am ? 'ስለ እርስዎ ወይም ስለ ስራ ኃላፊነትዎ አጭር ማስታወሻ...' : 'Short bio or role notes...'; ?>"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle"></i> <?php echo htmlspecialchars($lang['save'] ?? ($is_am ? 'አስቀምጥ' : 'Save Changes')); ?>
            </button>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>