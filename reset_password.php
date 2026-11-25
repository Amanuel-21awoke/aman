<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
require_once 'backend/db_connect.php';

if (!function_exists('inu_normalize_phone_et')) {
    function inu_normalize_phone_et(string $raw): string {
        $clean = preg_replace('/[^\d+]/', '', trim($raw));
        if (!is_string($clean) || $clean === '') {
            return '';
        }

        $digits = ltrim($clean, '+');

        if (preg_match('/^251([79]\d{0,8})$/', $digits, $m)) {
            $digits = '0' . $m[1];
        }

        return substr($digits, 0, 10);
    }
}

if (!function_exists('inu_is_valid_et_phone')) {
    function inu_is_valid_et_phone(string $phone_10): bool {
        return (bool)preg_match('/^(09|07)\d{8}$/', $phone_10);
    }
}

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$error = '';

$email_val = '';
$phone_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_val = strtolower(trim((string)($_POST['email'] ?? '')));
    $raw_phone = trim((string)($_POST['phone'] ?? ''));
    $phone_val = inu_normalize_phone_et($raw_phone);
    $new_pw    = (string)($_POST['new_password'] ?? '');
    $conf_pw   = (string)($_POST['confirm_password'] ?? '');

    if ($email_val === '' || $raw_phone === '' || $new_pw === '') {
        $error = $lang['fill_all_fields'] ?? ($is_am ? 'እባክዎ ሁሉንም የሚያስፈልጉ መረጃዎች ይሙሉ።' : 'Please fill in all required fields.');
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $error = $is_am ? 'እባክዎ ትክክለኛ የኢሜይል አድራሻ ያስገቡ።' : 'Please enter a valid email address.';
    } elseif (!inu_is_valid_et_phone($phone_val)) {
        $error = $is_am
            ? 'ስልክ ቁጥር በ 09፣ 07 ወይም +251 መጀመር አለበት እና 10 አሃዝ መሆን አለበት (ለምሳሌ 0911223344 ወይም 0711223344)።'
            : 'Phone number must start with 09, 07, or +251 and be 10 digits (e.g., 0911223344 or 0711223344).';
    } elseif (strlen($new_pw) < 6) {
        $fallback_min = $is_am
            ? 'የይለፍ ቃል ቢያንስ 6 ፊደላት/ቁምፊዎች መሆን አለበት (አሁን: {current})።'
            : 'Password must be at least 6 characters (current: {current}).';
        $tpl   = $lang['min_6_chars_detailed'] ?? $fallback_min;
        $error = str_replace('{current}', (string)strlen($new_pw), $tpl);
    } elseif ($new_pw !== $conf_pw) {
        $error = $is_am
            ? 'አዲሱ የይለፍ ቃል እና ማረጋገጫው አይመሳሰሉም።'
            : 'New password and confirmation do not match.';
    } else {
        try {
            $stmt = $conn->prepare("
                SELECT u.id, p.phone
                FROM `users` u
                LEFT JOIN `profiles` p ON u.id = p.user_id
                WHERE LOWER(TRIM(u.email)) = ?
                LIMIT 1
            ");
            $row = null;
            if ($stmt) {
                $stmt->bind_param("s", $email_val);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            $stored_phone_norm = inu_normalize_phone_et((string)($row['phone'] ?? ''));

            if ($row && $stored_phone_norm !== '' && $stored_phone_norm === $phone_val) {
                $uid  = (int)$row['id'];
                $hash = password_hash($new_pw, PASSWORD_DEFAULT);

                $u_cols = [];
                $ru = $conn->query("SHOW COLUMNS FROM `users`");
                if ($ru) {
                    while ($c = $ru->fetch_assoc()) {
                        $u_cols[] = (string)($c['Field'] ?? '');
                    }
                }
                $pw_col = in_array('password_hash', $u_cols, true)
                    ? 'password_hash'
                    : (in_array('password', $u_cols, true) ? 'password' : 'password_hash');

                $up = $conn->prepare("UPDATE `users` SET `" . $pw_col . "` = ? WHERE `id` = ?");
                if ($up) {
                    $up->bind_param("si", $hash, $uid);
                    if ($up->execute()) {
                        $up->close();
                        header("Location: login.php?reset=1");
                        exit();
                    }
                    $up->close();
                }
                $error = $lang['error_occurred'] ?? ($is_am ? 'ስህተት ተፈጥሯል፤ እባክዎ እንደገና ይሞክሩ።' : 'An error occurred. Please try again.');
            } else {
                $error = $is_am
                    ? 'ኢሜይል እና የተመዘገበው ስልክ ቁጥር ከመዝገባችን ጋር አይጣጣሙም።'
                    : 'Email and registered phone number do not match our records.';
            }
        } catch (Throwable $e) {
            $error = $is_am ? 'የስርዓት ስህተት ተፈጥሯል፤ እባክዎ እንደገና ይሞክሩ።' : 'A system error occurred. Please try again.';
        }
    }
}

include 'includes/header.php';
?>
<style>
.rp-page,
.rp-page *,
.rp-page *::before,
.rp-page *::after {
    box-sizing: border-box;
}
.rp-page .auth-wrapper {
    min-height: calc(100vh - 180px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}
.rp-page .auth-container {
    width: 100%;
    max-width: 460px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 2rem 1.75rem;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}
.rp-page .rp-title {
    color: var(--primary-color, #17663f);
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 0.4rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.rp-page .rp-subtitle {
    color: #64748b;
    font-size: 0.88rem;
    line-height: 1.55;
    margin: 0 0 1.25rem 0;
}
.rp-page .form-group {
    margin-bottom: 1rem;
}
.rp-page .form-group label {
    display: block;
    font-size: 0.86rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.38rem;
}
.rp-page .form-control {
    width: 100%;
    padding: 0.65rem 0.8rem;
    font-size: 0.92rem;
    line-height: 1.4;
    color: #0f172a;
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.rp-page .form-control:focus {
    outline: none;
    border-color: var(--primary-color, #17663f);
    box-shadow: 0 0 0 3px rgba(23, 102, 63, 0.14);
}
.rp-page .rp-hint {
    display: block;
    margin-top: 0.3rem;
    color: #64748b;
    font-size: 0.78rem;
}
.rp-page .rp-submit {
    width: 100%;
    padding: 0.72rem 1rem;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: 8px;
    margin-top: 0.25rem;
    cursor: pointer;
}
.rp-page .rp-footer-link {
    margin-top: 1.15rem;
    margin-bottom: 0;
    text-align: center;
    font-size: 0.88rem;
    color: #475569;
}
.rp-page .rp-footer-link a {
    font-weight: 600;
    text-decoration: none;
}
.rp-page .rp-footer-link a:hover {
    text-decoration: underline;
}
@media (max-width: 480px) {
    .rp-page .auth-container {
        padding: 1.5rem 1.15rem;
        border-radius: 12px;
    }
    .rp-page .rp-title {
        font-size: 1.2rem;
    }
}
</style>

<div class="rp-page">
    <div class="auth-wrapper">
        <div class="auth-container">
            <h2 class="rp-title">
                <i class="bi bi-key-fill"></i>
                <span><?php echo $is_am ? 'የአካውንት የይለፍ ቃል ዳግም ያስጀምሩ' : 'Reset Account Password'; ?></span>
            </h2>
            <p class="rp-subtitle">
                <?php echo $is_am
                    ? 'አዲስ የይለፍ ቃል ለማስቀመጥ የተመዘገበውን ኢሜይል እና ስልክ ቁጥር ያረጋግጡ።'
                    : 'Verify your registered email and phone number to set a new password.'; ?>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php" novalidate>
                <div class="form-group">
                    <label for="rp_email"><?php echo htmlspecialchars($lang['email_address'] ?? ($is_am ? 'ኢሜይል አድራሻ' : 'Email Address')); ?></label>
                    <input id="rp_email" type="email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($email_val); ?>"
                           placeholder="name@inu.edu.et">
                </div>

                <div class="form-group">
                    <label for="rp_phone">
                        <?php echo $is_am
                            ? 'የተመዘገበ ' . htmlspecialchars($lang['phone'] ?? 'ስልክ ቁጥር')
                            : 'Registered ' . htmlspecialchars($lang['phone'] ?? 'Phone'); ?>
                    </label>
                    <input id="rp_phone" type="tel" name="phone" class="form-control et-phone-input" required
                           value="<?php echo htmlspecialchars($phone_val); ?>"
                           placeholder="+2519... / 09... / 07..." maxlength="13" inputmode="tel">
                    <small class="reg-hint rp-hint">
                        <?php echo $is_am
                            ? 'ቅርጸት፦ +251..., 09..., ወይም 07... (ቢበዛ 10 አሃዝ)'
                            : 'Formats: +251..., 09..., or 07... (max 10 digits)'; ?>
                    </small>
                </div>

                <div class="form-group">
                    <label for="rp_new_pw"><?php echo $is_am ? 'አዲስ የይለፍ ቃል' : 'New Password'; ?></label>
                    <input id="rp_new_pw" type="password" name="new_password" class="form-control" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="rp_conf_pw"><?php echo htmlspecialchars($lang['confirm_password'] ?? ($is_am ? 'የይለፍ ቃል ያረጋግጡ' : 'Confirm Password')); ?></label>
                    <input id="rp_conf_pw" type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>

                <button type="submit" class="btn btn-primary rp-submit">
                    <?php echo $is_am ? 'የይለፍ ቃል ዳግም ያስጀምሩ' : 'Reset Password'; ?>
                </button>
            </form>

            <p class="rp-footer-link">
                <a href="login.php"><?php echo $is_am ? 'ወደ መግቢያ ገጽ ተመለስ' : 'Back to Login'; ?></a>
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.et-phone-input').forEach(function (input) {
        function formatEtPhone() {
            let raw = input.value.replace(/[^\d+]/g, '');
            if (raw.indexOf('+') > 0) {
                raw = raw.replace(/\+/g, '');
            }
            let digits = raw.replace(/\+/g, '');
            if (digits.startsWith('251')) {
                digits = '0' + digits.slice(3);
            }
            digits = digits.slice(0, 10);
            input.value = digits;
        }
        input.addEventListener('input', formatEtPhone);
        input.addEventListener('blur', formatEtPhone);
    });
});
</script>
<?php include 'includes/footer.php'; ?>