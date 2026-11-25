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

$full_name_val = '';
$email_val     = '';
$phone_val     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name_val = htmlspecialchars_decode(clean_input(trim((string)($_POST['full_name'] ?? ''))), ENT_QUOTES);
    $email_val     = strtolower(trim((string)($_POST['email'] ?? '')));
    $raw_phone     = trim((string)($_POST['phone'] ?? ''));
    $phone_val     = inu_normalize_phone_et($raw_phone);
    $password      = (string)($_POST['password'] ?? '');
    $confirm_pw    = (string)($_POST['confirm_password'] ?? '');

    if ($full_name_val === '' || $email_val === '' || $password === '') {
        $error = $lang['fill_all_fields'] ?? ($is_am ? 'እባክዎ ሁሉንም የሚያስፈልጉ መረጃዎች ይሙሉ።' : 'Please fill in all required fields.');
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $error = $is_am ? 'እባክዎ ትክክለኛ የኢሜይል አድራሻ ያስገቡ።' : 'Please enter a valid email address.';
    } elseif ($raw_phone !== '' && !inu_is_valid_et_phone($phone_val)) {
        $error = $is_am
            ? 'ስልክ ቁጥር በ 09፣ 07 ወይም +251 መጀመር አለበት እና 10 አሃዝ መሆን አለበት (ለምሳሌ 0911223344 ወይም 0711223344)።'
            : 'Phone number must start with 09, 07, or +251 and be 10 digits (e.g., 0911223344 or 0711223344).';
    } elseif (strlen($password) < 6) {
        $fallback_min = $is_am
            ? 'የይለፍ ቃል ቢያንስ 6 ፊደላት/ቁምፊዎች መሆን አለበት (አሁን: {current})።'
            : 'Password must be at least 6 characters (current: {current}).';
        $tpl   = $lang['min_6_chars_detailed'] ?? $fallback_min;
        $error = str_replace('{current}', (string)strlen($password), $tpl);
    } elseif ($password !== $confirm_pw) {
        $error = $is_am ? 'የይለፍ ቃል እና ማረጋገጫው አይመሳሰሉም።' : 'Password and confirmation do not match.';
    } else {
        try {
            $chk = $conn->prepare("SELECT id FROM `users` WHERE LOWER(TRIM(`email`)) = ? LIMIT 1");
            $exists = false;
            if ($chk) {
                $chk->bind_param("s", $email_val);
                $chk->execute();
                $res = $chk->get_result();
                if ($res && $res->num_rows > 0) {
                    $exists = true;
                }
                $chk->close();
            }

            if ($exists) {
                $error = $is_am ? 'ይህ ኢሜይል አድራሻ ቀድሞ ተመዝግቧል።' : 'This email address is already registered.';
            } else {
                $u_cols = [];
                $ru = $conn->query("SHOW COLUMNS FROM `users`");
                if ($ru) {
                    while ($c = $ru->fetch_assoc()) {
                        $u_cols[] = (string)($c['Field'] ?? '');
                    }
                }

                $name_col = in_array('full_name', $u_cols, true)
                    ? 'full_name'
                    : (in_array('name', $u_cols, true) ? 'name' : null);
                $pw_col   = in_array('password_hash', $u_cols, true)
                    ? 'password_hash'
                    : (in_array('password', $u_cols, true) ? 'password' : null);
                $role_col = in_array('role', $u_cols, true) ? 'role' : null;

                if ($pw_col === null) {
                    $error = $is_am ? 'የተጠቃሚ የይለፍ ቃል አምድ በዳታቤዝ ላይ አልተገኘም።' : 'Password column not found in users table.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $cols_sql = ['`email`', '`' . $pw_col . '`'];
                    $ph_sql   = ['?', '?'];
                    $types    = 'ss';
                    $params   = [$email_val, $hash];

                    if ($name_col !== null) {
                        $cols_sql[] = '`' . $name_col . '`';
                        $ph_sql[]   = '?';
                        $types     .= 's';
                        $params[]   = $full_name_val;
                    }
                    if ($role_col !== null) {
                        $cols_sql[] = '`' . $role_col . '`';
                        $ph_sql[]   = '?';
                        $types     .= 's';
                        $params[]   = 'trainee';
                    }

                    $ins = $conn->prepare("INSERT INTO `users` (" . implode(", ", $cols_sql) . ") VALUES (" . implode(", ", $ph_sql) . ")");
                    $created_ok = false;
                    $new_uid    = 0;

                    if ($ins) {
                        $ins->bind_param($types, ...$params);
                        if ($ins->execute()) {
                            $created_ok = true;
                            $new_uid    = (int)$conn->insert_id;
                        }
                        $ins->close();
                    }

                    if ($created_ok) {
                        if ($new_uid > 0 && $phone_val !== '') {
                            $chk_prof = $conn->query("SHOW TABLES LIKE 'profiles'");
                            if ($chk_prof && $chk_prof->num_rows > 0) {
                                $p_cols = [];
                                $rp = $conn->query("SHOW COLUMNS FROM `profiles`");
                                if ($rp) {
                                    while ($pc = $rp->fetch_assoc()) {
                                        $p_cols[] = (string)($pc['Field'] ?? '');
                                    }
                                }
                                if (in_array('user_id', $p_cols, true) && in_array('phone', $p_cols, true)) {
                                    $pins = $conn->prepare("INSERT INTO `profiles` (`user_id`, `phone`) VALUES (?, ?)");
                                    if ($pins) {
                                        $pins->bind_param("is", $new_uid, $phone_val);
                                        $pins->execute();
                                        $pins->close();
                                    }
                                }
                            }
                        }

                        header("Location: login.php?registered=1");
                        exit();
                    } else {
                        $error = $lang['error_occurred'] ?? ($is_am ? 'ምዝገባውን ማጠናቀቅ አልተቻለም።' : 'Could not complete registration.');
                    }
                }
            }
        } catch (Throwable $e) {
            $error = $is_am ? 'የስርዓት ስህተት ተፈጥሯል፤ እባክዎ እንደገና ይሞክሩ።' : 'A system error occurred. Please try again.';
        }
    }
}

include 'includes/header.php';
?>
<style>
.reg-page,
.reg-page *,
.reg-page *::before,
.reg-page *::after {
    box-sizing: border-box;
}
.reg-page .auth-wrapper {
    min-height: calc(100vh - 180px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}
.reg-page .auth-container {
    width: 100%;
    max-width: 460px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 2rem 1.75rem;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}
.reg-page .reg-title {
    color: var(--primary-color, #17663f);
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 0.4rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.reg-page .reg-subtitle {
    color: #64748b;
    font-size: 0.88rem;
    line-height: 1.55;
    margin: 0 0 1.25rem 0;
}
.reg-page .form-group {
    margin-bottom: 1rem;
}
.reg-page .form-group label {
    display: block;
    font-size: 0.86rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.38rem;
}
.reg-page .form-control {
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
.reg-page .form-control:focus {
    outline: none;
    border-color: var(--primary-color, #17663f);
    box-shadow: 0 0 0 3px rgba(23, 102, 63, 0.14);
}
.reg-page .reg-hint {
    display: block;
    margin-top: 0.3rem;
    color: #64748b;
    font-size: 0.78rem;
}
.reg-page .reg-submit {
    width: 100%;
    padding: 0.72rem 1rem;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: 8px;
    margin-top: 0.25rem;
    cursor: pointer;
}
.reg-page .reg-footer-link {
    margin-top: 1.15rem;
    margin-bottom: 0;
    text-align: center;
    font-size: 0.88rem;
    color: #475569;
}
.reg-page .reg-footer-link a {
    font-weight: 600;
    text-decoration: none;
}
.reg-page .reg-footer-link a:hover {
    text-decoration: underline;
}
@media (max-width: 480px) {
    .reg-page .auth-container {
        padding: 1.5rem 1.15rem;
        border-radius: 12px;
    }
    .reg-page .reg-title {
        font-size: 1.2rem;
    }
}
</style>

<div class="reg-page">
    <div class="auth-wrapper">
        <div class="auth-container">
            <h2 class="reg-title">
                <i class="bi bi-person-plus-fill"></i>
                <span><?php echo htmlspecialchars($lang['join_us'] ?? ($is_am ? 'አዲስ አካውንት ይፍጠሩ' : 'Create Account')); ?></span>
            </h2>
            <p class="reg-subtitle">
                <?php echo $is_am
                    ? 'በእንጅባራ ዩኒቨርሲቲ የአጭር ጊዜ ስልጠና ማዕከል ፕሮግራሞች ለመመዝገብ አካውንት ይፍጠሩ።'
                    : 'Register for Injibara University Short-Term Training Hub programs.'; ?>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php" novalidate>
                <div class="form-group">
                    <label for="reg_full_name"><?php echo htmlspecialchars($lang['full_name'] ?? ($is_am ? 'ሙሉ ስም' : 'Full Name')); ?></label>
                    <input id="reg_full_name" type="text" name="full_name" class="form-control" required
                           value="<?php echo htmlspecialchars($full_name_val); ?>"
                           placeholder="<?php echo $is_am ? 'ሙሉ ስምዎን ያስገቡ' : 'Enter your full name'; ?>">
                </div>

                <div class="form-group">
                    <label for="reg_email"><?php echo htmlspecialchars($lang['email_address'] ?? ($is_am ? 'ኢሜይል አድራሻ' : 'Email Address')); ?></label>
                    <input id="reg_email" type="email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($email_val); ?>"
                           placeholder="name@inu.edu.et">
                </div>

                <div class="form-group">
                    <label for="reg_phone"><?php echo htmlspecialchars($lang['phone'] ?? ($is_am ? 'ስልክ ቁጥር' : 'Phone Number')); ?></label>
                    <input id="reg_phone" type="tel" name="phone" class="form-control et-phone-input"
                           value="<?php echo htmlspecialchars($phone_val); ?>"
                           placeholder="+2519... / 09... / 07..." maxlength="13" inputmode="tel">
                    <small class="reg-hint">
                        <?php echo $is_am
                            ? 'ቅርጸት፦ +251..., 09..., ወይም 07... (ቢበዛ 10 አሃዝ)'
                            : 'Formats: +251..., 09..., or 07... (max 10 digits)'; ?>
                    </small>
                </div>

                <div class="form-group">
                    <label for="reg_password"><?php echo htmlspecialchars($lang['password'] ?? ($is_am ? 'የይለፍ ቃል' : 'Password')); ?></label>
                    <input id="reg_password" type="password" name="password" class="form-control" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="reg_confirm_pw"><?php echo htmlspecialchars($lang['confirm_password'] ?? ($is_am ? 'የይለፍ ቃል ያረጋግጡ' : 'Confirm Password')); ?></label>
                    <input id="reg_confirm_pw" type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>

                <button type="submit" class="btn btn-primary reg-submit">
                    <?php echo htmlspecialchars($lang['join_us'] ?? ($is_am ? 'ተመዝገብ' : 'Register')); ?>
                </button>
            </form>

            <p class="reg-footer-link">
                <?php echo $is_am ? 'ቀድሞ አካውንት አለዎት?' : 'Already have an account?'; ?>
                <a href="login.php"><?php echo htmlspecialchars($lang['login'] ?? ($is_am ? 'ግባ' : 'Login')); ?></a>
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