<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['certificate_officer'], '../../');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$msg   = $_SESSION['cert_msg'] ?? '';
$err   = $_SESSION['cert_err'] ?? '';
unset($_SESSION['cert_msg'], $_SESSION['cert_err']);

$allowed_cert_status = ['Pending', 'Issued', 'Revoked'];

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS issued_certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            certificate_code VARCHAR(80) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Issued',
            issued_by INT NULL,
            issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cert_enrollment (enrollment_id),
            UNIQUE KEY uq_cert_code (certificate_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'issue_or_update_certificate') {
    $enr_id   = (int)($_POST['enrollment_id'] ?? 0);
    $anchor   = ($enr_id > 0) ? '#cert-row-' . $enr_id : '#cert-queue-panel';
    $csrf_val = (string)($_POST['csrf_token'] ?? '');

    if (empty($csrf_val) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_val)) {
        $_SESSION['cert_err'] = $is_am
            ? 'የደህንነት ማረጋገጫ (CSRF) አልተሳካም። እባክዎ ገጹን እንደገና ይጫኑ።'
            : 'Invalid security token (CSRF). Please refresh and try again.';
        header("Location: index.php" . $anchor);
        exit;
    }

    $cert_st = trim((string)($_POST['cert_status'] ?? 'Issued'));
    if (!in_array($cert_st, $allowed_cert_status, true)) {
        $cert_st = 'Issued';
    }
    $custom_c = htmlspecialchars_decode(clean_input(trim((string)($_POST['certificate_code'] ?? ''))), ENT_QUOTES);

    if ($enr_id > 0) {
        try {
            $chk = $conn->prepare("SELECT id, certificate_code FROM issued_certificates WHERE enrollment_id = ? LIMIT 1");
            $existing = null;
            if ($chk) {
                $chk->bind_param("i", $enr_id);
                $chk->execute();
                $res_c = $chk->get_result();
                if ($res_c) {
                    $existing = $res_c->fetch_assoc();
                }
                $chk->close();
            }

            if ($existing) {
                $code_to_save = ($custom_c !== '') ? $custom_c : (string)$existing['certificate_code'];

                $dup_chk = $conn->prepare("SELECT id FROM issued_certificates WHERE certificate_code = ? AND enrollment_id <> ? LIMIT 1");
                $is_dup = false;
                if ($dup_chk) {
                    $dup_chk->bind_param("si", $code_to_save, $enr_id);
                    $dup_chk->execute();
                    $res_dup = $dup_chk->get_result();
                    if ($res_dup && $res_dup->num_rows > 0) {
                        $is_dup = true;
                    }
                    $dup_chk->close();
                }

                if ($is_dup) {
                    $_SESSION['cert_err'] = $is_am
                        ? 'ይህ የሰርተፊኬት ኮድ ቀድሞ ለሌላ መዝገብ ተመዝግቧል።'
                        : 'This certificate code is already assigned to another record.';
                } else {
                    $upd = $conn->prepare("UPDATE issued_certificates SET certificate_code = ?, status = ?, issued_by = ? WHERE enrollment_id = ?");
                    if ($upd) {
                        $upd->bind_param("ssii", $code_to_save, $cert_st, $uid, $enr_id);
                        if ($upd->execute()) {
                            $_SESSION['cert_msg'] = $is_am ? 'የሰርተፊኬት ሁኔታ ተዘምኗል።' : 'Certificate updated.';
                        } else {
                            $_SESSION['cert_err'] = $is_am ? 'ሰርተፊኬቱን ማዘመን አልተቻለም።' : 'Failed to update certificate.';
                        }
                        $upd->close();
                    }
                }
            } else {
                $code_to_save = ($custom_c !== '')
                    ? $custom_c
                    : ('CERT-' . date('Y') . '-' . str_pad((string)$enr_id, 4, '0', STR_PAD_LEFT));

                $dup_chk = $conn->prepare("SELECT id FROM issued_certificates WHERE certificate_code = ? LIMIT 1");
                $is_dup = false;
                if ($dup_chk) {
                    $dup_chk->bind_param("s", $code_to_save);
                    $dup_chk->execute();
                    $res_dup = $dup_chk->get_result();
                    if ($res_dup && $res_dup->num_rows > 0) {
                        $is_dup = true;
                    }
                    $dup_chk->close();
                }

                if ($is_dup && $custom_c === '') {
                    $code_to_save = 'CERT-' . date('Y') . '-' . str_pad((string)$enr_id, 4, '0', STR_PAD_LEFT) . '-' . random_int(10, 99);
                    $is_dup = false;
                }

                if ($is_dup) {
                    $_SESSION['cert_err'] = $is_am
                        ? 'ይህ የሰርተፊኬት ኮድ ቀድሞ ተመዝግቧል።'
                        : 'This certificate code already exists.';
                } else {
                    $ins = $conn->prepare("INSERT INTO issued_certificates (enrollment_id, certificate_code, status, issued_by) VALUES (?, ?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param("issi", $enr_id, $code_to_save, $cert_st, $uid);
                        if ($ins->execute()) {
                            $_SESSION['cert_msg'] = $is_am ? 'ሰርተፊኬት ተሰጥቷል።' : 'Certificate issued.';
                        } else {
                            $_SESSION['cert_err'] = $is_am ? 'ሰርተፊኬት መስጠት አልተቻለም።' : 'Failed to issue certificate.';
                        }
                        $ins->close();
                    }
                }
            }
        } catch (Throwable $e) {
            $_SESSION['cert_err'] = $e->getMessage();
        }
    }

    header("Location: index.php" . $anchor);
    exit;
}

$candidates = [];
$issued_cnt = 0;

try {
    $enr_cols = [];
    $col_res = $conn->query("SHOW COLUMNS FROM enrollments");
    if ($col_res) {
        while ($c_row = $col_res->fetch_assoc()) {
            $enr_cols[] = (string)$c_row['Field'];
        }
    }

    $user_fk_col = null;
    foreach (['user_id', 'trainee_id', 'student_id', 'member_id'] as $cand_col) {
        if (in_array($cand_col, $enr_cols, true)) {
            $user_fk_col = $cand_col;
            break;
        }
    }

    $prog_fk_col = null;
    foreach (['program_id', 'training_program_id', 'course_id'] as $cand_pcol) {
        if (in_array($cand_pcol, $enr_cols, true)) {
            $prog_fk_col = $cand_pcol;
            break;
        }
    }

    $sel_status = in_array('status', $enr_cols, true) ? "e.status AS enrollment_status" : "'enrolled' AS enrollment_status";
    $sel_score  = in_array('assessment_score', $enr_cols, true) ? "e.assessment_score" : "0 AS assessment_score";
    $sel_att    = in_array('attendance_pct', $enr_cols, true) ? "e.attendance_pct" : "0 AS attendance_pct";

    $join_users_sql = $user_fk_col
        ? "LEFT JOIN users u ON e.`" . $user_fk_col . "` = u.id"
        : "LEFT JOIN users u ON 1=0";

    $join_prog_sql = $prog_fk_col
        ? "LEFT JOIN training_programs p ON e.`" . $prog_fk_col . "` = p.id"
        : "LEFT JOIN training_programs p ON 1=0";

    $sql = "
        SELECT e.id, {$sel_status}, {$sel_score}, {$sel_att},
               u.name AS trainee_name, u.email AS trainee_email,
               p.code AS program_code, p.title_en, p.title_am,
               ic.certificate_code, COALESCE(ic.status, 'Pending') AS cert_status
        FROM enrollments e
        {$join_users_sql}
        {$join_prog_sql}
        LEFT JOIN issued_certificates ic ON ic.enrollment_id = e.id
        ORDER BY e.id DESC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $candidates[] = $row;
            if (($row['cert_status'] ?? '') === 'Issued') {
                $issued_cnt++;
            }
        }
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<style>
.cert-dash-page,
.cert-dash-page *,
.cert-dash-page *::before,
.cert-dash-page *::after {
    box-sizing: border-box;
}
.cert-dash-page .form-control {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.cert-dash-page .form-control:focus {
    outline: none;
    border-color: var(--primary-color, #17663f);
    box-shadow: 0 0 0 3px rgba(23, 102, 63, 0.14);
}
@media (max-width: 640px) {
    .cert-dash-page .card-box {
        padding: 1rem 0.85rem;
    }
}
</style>

<div class="content-with-sidebar cert-dash-page">
    <div class="dashboard-header">
        <div>
            <h1><?php echo $is_am ? 'የሰርተፊኬት ባለሙያ (Certificate Officer) ዳሽቦርድ' : 'Certificate Officer Dashboard'; ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am ? 'የተመራቂ ሰልጣኞች ሰርተፊኬት መስጠት፣ ኮድ መመዝገብ እና ሁኔታ መከታተል' : 'Issue trainee certificates, assign certificate codes, and manage issuance status'; ?>
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" style="background:linear-gradient(135deg,#17663f,#0f172a);">
            <div><p><?php echo $is_am ? 'የተሰጡ ሰርተፊኬቶች' : 'Issued Certificates'; ?></p><h2><?php echo (int)$issued_cnt; ?></h2></div>
            <i class="bi bi-award-fill"></i>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
            <div><p><?php echo $is_am ? 'ጠቅላላ መዝገቦች' : 'Total Records'; ?></p><h2><?php echo count($candidates); ?></h2></div>
            <i class="bi bi-card-text"></i>
        </div>
    </div>

    <div class="card-box" id="cert-queue-panel">
        <h3 style="color:var(--primary-color); margin-bottom:0.75rem;">
            <?php echo $is_am ? 'የሰርተፊኬት አሰጣጥ ዝርዝር' : 'Certificate Issuance Queue'; ?> (<?php echo count($candidates); ?>)
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php echo $is_am ? 'ሰልጣኝ / ፕሮግራም' : 'Trainee & Program'; ?></th>
                        <th><?php echo $is_am ? 'ውጤት / መገኘት' : 'Score / Att.'; ?></th>
                        <th><?php echo $is_am ? 'የሰርተፊኬት ሁኔታ' : 'Certificate Status'; ?></th>
                        <th><?php echo $is_am ? 'ድርጊት' : 'Issue / Update'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($candidates)): ?>
                        <?php foreach ($candidates as $c):
                            $eid = (int)$c['id'];
                            $ptitle = ($is_am && !empty($c['title_am'])) ? $c['title_am'] : ($c['title_en'] ?? '');
                            $cur_st = (string)($c['cert_status'] ?? 'Pending');
                            $cur_code = (string)($c['certificate_code'] ?? '');
                        ?>
                        <tr id="cert-row-<?php echo $eid; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars((string)($c['trainee_name'] ?? ('Trainee #' . $eid)), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <div style="font-size:0.78rem; color:#64748b;">
                                    <?php echo htmlspecialchars(trim((string)(($c['program_code'] ?? '') . ($ptitle !== '' ? ' — ' . $ptitle : ''))), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <td>
                                <?php echo number_format((float)($c['assessment_score'] ?? 0), 1); ?>% /
                                <?php echo number_format((float)($c['attendance_pct'] ?? 0), 1); ?>%
                            </td>
                            <td>
                                <?php echo status_badge($cur_st); ?>
                                <?php if ($cur_code !== ''): ?>
                                    <div style="font-size:0.78rem; color:#334155; margin-top:0.2rem;"><?php echo htmlspecialchars($cur_code, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="issue_or_update_certificate">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                    <input type="text" name="certificate_code" class="form-control" style="width:130px; padding:0.25rem 0.45rem; font-size:0.82rem;" value="<?php echo htmlspecialchars($cur_code, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $is_am ? 'ኮድ (ራስ-ሰር)' : 'Code (auto)'; ?>">
                                    <select name="cert_status" class="form-control" style="width:auto; padding:0.25rem 0.45rem; font-size:0.82rem;">
                                        <?php foreach ($allowed_cert_status as $st): ?>
                                            <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($cur_st === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check2"></i> <?php echo $is_am ? 'አስቀምጥ' : 'Save'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የሰርተፊኬት መዝገብ አልተገኘም።' : 'No certificate records found.'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
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