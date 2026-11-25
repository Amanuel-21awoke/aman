<?php
require_once 'includes/functions.php';
require_once 'backend/db_connect.php';

// Load Language & Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($lang)) {
    $lang_code = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';
    $lang_path = __DIR__ . "/includes/lang_" . $lang_code . ".php";
    if (file_exists($lang_path)) {
        $lang = include($lang_path);
    } else {
        $lang = include(__DIR__ . "/includes/lang_en.php");
    }
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$errors = [];
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (empty($submitted_csrf) || !hash_equals($_SESSION['csrf_token'], $submitted_csrf)) {
        $errors[] = $is_am ? 'የደህንነት ማረጋገጫ (CSRF) አልተሳካም። እባክዎ ገጹን እንደገና ይጫኑ።' : 'Invalid security token (CSRF). Please refresh and try again.';
    }

    $email = strtolower(trim(clean_input($_POST['email'] ?? '')));
    $password = clean_input($_POST['password'] ?? '');

    file_put_contents(__DIR__ . '/login_debug.log', "POST attempt for $email\n", FILE_APPEND);

    if (empty($errors)) {
        if (empty($email) || empty($password)) {
            $errors[] = $is_am ? 'ኢሜይል እና የይለፍ ቃል ያስፈልጋሉ።' : 'Email and password are required.';
        } else {
            $stmt = $conn->prepare("SELECT id, name, password_hash, role, status FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $name, $hash, $role, $status);
                $stmt->fetch();

                if (password_verify($password, $hash)) {
                    if ($status === 'inactive') {
                        $errors[] = $is_am ? 'መለያዎ ተቋርጧል። እባክዎ አስተዳዳሪን ያነጋግሩ።' : 'Your account is deactivated. Contact Admin.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $id;
                        $_SESSION['name'] = $name;
                        $_SESSION['role'] = $role;

                        $target_dash = get_dashboard_url($role, '');
                        file_put_contents(__DIR__ . '/login_debug.log', "Login success for $email. Role: $role. Target: $target_dash\n", FILE_APPEND);
                        header("Location: " . $target_dash);
                        exit();
                    }
                } else {
                    $errors[] = $is_am ? 'የተሳሳተ የይለፍ ቃል።' : 'Invalid password.';
                    file_put_contents(__DIR__ . '/login_debug.log', "Login failure for $email. Invalid password. Input: $password, Hash in DB: $hash\n", FILE_APPEND);
                }
            } else {
                $errors[] = $is_am ? 'በዚህ ኢሜይል የተመዘገበ መለያ አልተገኘም።' : 'No account found with that email.';
                file_put_contents(__DIR__ . '/login_debug.log', "Login failure for $email. No account found.\n", FILE_APPEND);
            }
            $stmt->close();
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}

include 'includes/header.php';
?>

<style>
.inu-login-card,
.inu-login-card *,
.inu-login-card *::before,
.inu-login-card *::after {
    box-sizing: border-box;
}
.inu-login-card {
    background: #16b3e2;
    border-radius: 18px;
    padding: 2.25rem 2rem;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
    border: 1px solid #e2e8f0;
    align-items: center;
}
.inu-login-logo-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto 1rem auto;
}
.inu-login-logo {
    display: block;
    margin: 0 auto;
    width: 118px;
    height: 118px;
    max-width: 118px;
    max-height: 118px;
    border-radius: 50%;
    object-fit: contain;
    background: #8632a7;
    padding: 0;
    border: 2.5px solid #17663f;
    box-shadow: 0 8px 20px rgba(23, 102, 63, 0.16);
}
.inu-input-wrap {
    display: flex;
    text-align: center;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.inu-input-wrap:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.97);
}
.inu-input-icon {
    padding: 0 0.85rem;
    color: var(--primary-color);
    font-size: 1.05rem;
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    align-self: stretch;
    border-right: 1px solid #e2e8f0;
}
.inu-input-field {
    flex: 1;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 0.72rem 0.9rem !important;
    font-size: 0.95rem;
    background: transparent;
    min-width: 0;
}
.inu-login-title-badge {
    display: block;
    width: fit-content;
    margin: 0.35rem auto 0.55rem auto;
    padding: 0.35rem 1rem;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(0, 8, 4, 0.91) 0%, #0f766e 100%);
    color: #ffffff !important;
    font-weight: 700;
    font-size: 1.08rem;
    text-align: center;
    box-shadow: 0 6px 16px #f1f5f9;
}
.inu-eye-btn {
    background: #ffffff;
    border: none;
    border-left: 1px solid #0a0a0a;
    padding: 0 0.9rem;
    cursor: pointer;
    color: #000000;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: stretch;
    transition: background-color 0.18s ease, color 0.18s ease;
}
.inu-eye-btn:hover {
    background: #f1f5f9;
    color: var(--primary-color);
}
.inu-forgot-link,
.inu-register-link {
    display: inline-block;
    font-weight: 700;
    text-decoration: none;
    color: #06283d;
    border-bottom: 1.5px solid #06283d;
    padding: 0.1rem 0.35rem;
    border-radius: 6px;
    transition: color 0.18s ease, background-color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
}
.inu-forgot-link {
    font-size: 0.86rem;
}
.inu-register-link {
    font-size: 0.9rem;
}
.inu-forgot-link:hover,
.inu-register-link:hover {
    color: #ffffff;
    background-color: rgba(6, 40, 61, 0.28);
    border-bottom-color: #fde047;
    transform: translateY(-1px);
}
.inu-btn-login-secure {
    display: block;
    width: fit-content;
    margin: 0.35rem auto 0.55rem auto;
    padding: 0.35rem 1rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #000000 !important;
    font-weight: 700;
    font-size: 1.08rem;
    text-align: center;
    box-shadow: 0 6px 16px rgba(66, 23, 102, 0.22);
}
.inu-btn-login-secure:hover {
    background: #22f706;
    color: #ffffff !important;
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(159, 199, 13, 0.25) !important;
}
</style>

<div class="auth-wrapper login-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-11 col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto">
                <div class="login-box inu-login-card mx-auto" style="max-width: 480px;">
                    <div class="text-center mb-4">
                        <img src="assets/inu_logo.jpg"
                             alt="INU Logo" class="rounded-circle logo inu-login-logo mb-3">
                        <h3 class="fw-bold text-primary mb-1 inu-login-title-badge" style="text-align:center;">
                            <?php echo $is_am ? 'የእንጅባራ ዩኒቨርሲቲ የአጭር ጊዜ ስልጠና ማዕከል' : 'INU Short-Term Training Hub'; ?>
                        </h3>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error alert-danger small rounded-3" style="margin-bottom:1rem;">
                            <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success small rounded-3" style="margin-bottom:1rem;">
                            <?php echo htmlspecialchars($lang['reg_success'] ?? ($is_am ? 'ምዝገባ ተሳክቷል! እባክዎ ይግቡ።' : 'Registration successful! Please login.')); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['reset'])): ?>
                        <div class="alert alert-success small rounded-3" style="margin-bottom:1rem;">
                            <?php echo $is_am ? 'የይለፍ ቃል ዳግም ተጀምሯል! አሁን በአዲሱ የይለፍ ቃልዎ መግባት ይችላሉ።' : 'Password reset completed! You can now log in with your new password.'; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['logout'])): ?>
                        <div class="alert alert-info small rounded-3" style="margin-bottom:1rem;">
                            <?php echo $is_am ? 'በተሳካ ሁኔታ ወጥተዋል።' : 'Logged out successfully.'; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3 form-group">
                            <div class="input-group inu-input-wrap">
                                <span class="input-group-text bg-white inu-input-icon"><i class="bi bi-envelope text-primary"></i></span>
                                <input type="email" name="email" id="loginEmail" class="form-control form-control-lg inu-input-field"
                                       placeholder="<?php echo $is_am ? 'ኢሜይል አድራሻ' : 'Email Address'; ?>"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                       required autofocus>
                            </div>
                        </div>

                        <div class="mb-3 form-group">
                            <div class="input-group inu-input-wrap">
                                <span class="input-group-text bg-white inu-input-icon"><i class="bi bi-lock text-primary"></i></span>
                                <input type="password" name="password" id="password" class="form-control form-control-lg inu-input-field"
                                       placeholder="<?php echo $is_am ? 'የይለፍ ቃል' : 'Password'; ?>"
                                       required minlength="6">
                                <button class="btn btn-outline-secondary border-start-0 inu-eye-btn" type="button" id="togglePassword"
                                        aria-label="<?php echo $is_am ? 'የይለፍ ቃል አሳይ ወይም ደብቅ' : 'Toggle Password Visibility'; ?>">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-start; margin-bottom:1.1rem;">
                            <a href="reset_password.php" class="inu-forgot-link">
                                <?php echo $is_am ? 'የይለፍ ቃል ረሱ ወይም ዳግም ያስጀምሩ?' : 'Forgot / Reset Password?'; ?>
                            </a>
                        </div>

                        <button type="submit" class="btn btn-login inu-btn-login-secure text-white w-100 shadow-lg">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span><?php echo $is_am ? 'በደህንነት ይግቡ' : 'Login Securely'; ?></span>
                        </button>
                    </form>

                    <p style="margin-top: 1.5rem; margin-bottom: 0; text-align: center; font-size: 0.9rem; color: #06283d; font-weight: 500;">
                        <?php echo htmlspecialchars($lang['dont_have_account'] ?? ($is_am ? 'መለያ የለዎትም?' : "Don't have an account?")); ?>
                        <a href="register.php" class="inu-register-link">
                            <?php echo htmlspecialchars($lang['register_here'] ?? ($is_am ? 'እዚህ ይመዝገቡ' : 'Register here')); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.className = 'bi bi-eye-slash';
        } else {
            passwordInput.type = 'password';
            eyeIcon.className = 'bi bi-eye';
        }
    });

    // Enforce lowercase email client-side in real-time
    document.querySelectorAll('input[type="email"]').forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.toLowerCase().trim();
        });
        input.addEventListener('blur', function () {
            this.value = this.value.toLowerCase().trim();
        });
    });
</script>

<?php include 'includes/footer.php'; ?>