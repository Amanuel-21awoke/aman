<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
require_once 'backend/db_connect.php';

$is_am_vc = (($_SESSION['lang'] ?? 'en') === 'am');
$role_vc  = strtolower(trim((string)($_SESSION['role'] ?? '')));

// Fallback destination if browser history is empty
$fallback_back_url = 'index.php';
if ($role_vc === 'registrar') {
    $fallback_back_url = 'dashboard/registrar/index.php?section=documents#registrar-documents-panel';
} elseif ($role_vc !== '') {
    $fallback_back_url = 'dashboard/' . rawurlencode($role_vc) . '.php';
}

// Optional explicit ?back=... parameter (safe relative path only)
$raw_back = trim((string)($_GET['back'] ?? ''));
if ($raw_back !== '' && !preg_match('#^(https?:)?//#i', $raw_back) && strpos($raw_back, "\0") === false) {
    $fallback_back_url = $raw_back;
}

$serial_query = trim((string)($_GET['serial'] ?? ($_POST['serial'] ?? '')));
$verify_result = null;
$verify_error  = '';

if ($serial_query !== '') {
    try {
        $chk_doc = $conn->query("SHOW TABLES LIKE 'registrar_documents'");
        if ($chk_doc && $chk_doc->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT d.id, d.enrollment_id, d.doc_type, d.doc_serial, d.remarks, d.created_at,
                       e.student_code, e.cohort_name, e.assessment_score, e.attendance_pct, e.grade_verified, e.status
                FROM `registrar_documents` d
                LEFT JOIN `enrollments` e ON d.enrollment_id = e.id
                WHERE UPPER(TRIM(d.doc_serial)) = UPPER(TRIM(?))
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param("s", $serial_query);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $verify_result = $row;
                } else {
                    $verify_error = $is_am_vc
                        ? 'በዚህ መለያ ቁጥር የተመዘገበ ሰርተፍኬት ወይም ሰነድ አልተገኘም።'
                        : 'No certificate or document found for this serial number.';
                }
                $stmt->close();
            }
        } else {
            $verify_error = $is_am_vc
                ? 'የሰነድ ማረጋገጫ ዳታቤዝ ሰንጠረዥ እስካሁን አልተዘጋጀም።'
                : 'Document verification table is not initialized yet.';
        }
    } catch (Throwable $e) {
        $verify_error = $e->getMessage();
    }
}

$base_path = '';
include 'includes/header.php';
?>
<div class="container" style="max-width:860px; margin:2rem auto; padding:0 1rem;">
    <div class="vc-top-bar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1.25rem;">
        <a href="<?php echo htmlspecialchars($fallback_back_url); ?>"
           id="vcBackBtn"
           onclick="return vcHandleBack(event, '<?php echo htmlspecialchars($fallback_back_url, ENT_QUOTES); ?>');"
           class="btn btn-outline btn-sm"
           style="display:inline-flex; align-items:center; gap:0.4rem; text-decoration:none;">
            <i class="bi bi-arrow-left"></i>
            <span><?php echo $is_am_vc ? 'ተመለስ (Back)' : 'Back'; ?></span>
        </a>
    </div>

    <div class="card-box">
        <h1 style="color:var(--primary-color); margin-top:0; margin-bottom:0.4rem;">
            <?php echo $is_am_vc ? 'የሰርተፍኬት እና ሰነድ ትክክለኛነት ማረጋገጫ' : 'Certificate Authenticity Verification'; ?>
        </h1>
        <p style="color:#64748b; margin-bottom:1.25rem;">
            <?php echo $is_am_vc
                ? 'በሰነዱ ላይ ያለውን የመለያ ቁጥር (Serial No - ለምሳሌ DOC-...) በማስገባት የሰነዱን ትክክለኛነት ያረጋግጡ።'
                : 'Enter the document serial number (e.g., DOC-...) to verify authenticity and issuance status.'; ?>
        </p>

        <form method="GET" action="verify_certificate.php" style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:flex-end; margin-bottom:1.25rem;">
            <?php if ($raw_back !== '' && !preg_match('#^(https?:)?//#i', $raw_back) && strpos($raw_back, "\0") === false): ?>
                <input type="hidden" name="back" value="<?php echo htmlspecialchars($raw_back); ?>">
            <?php endif; ?>
            <div class="form-group" style="margin:0; flex:1 1 260px;">
                <label><?php echo $is_am_vc ? 'የሰነድ መለያ ቁጥር (Serial Number)' : 'Document Serial Number'; ?></label>
                <input type="text"
                       name="serial"
                       class="form-control"
                       required
                       value="<?php echo htmlspecialchars($serial_query); ?>"
                       placeholder="<?php echo $is_am_vc ? 'ለምሳሌ DOC-20261123-A1B2C3' : 'e.g. DOC-20261123-A1B2C3'; ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-shield-check"></i>
                    <?php echo $is_am_vc ? 'አረጋግጥ (Verify)' : 'Verify'; ?>
                </button>
            </div>
        </form>

        <?php if ($verify_error !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($verify_error); ?></div>
        <?php endif; ?>

        <?php if ($verify_result !== null): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;">
                <strong><?php echo $is_am_vc ? 'ሰነዱ ትክክለኛ እና የተረጋገጠ ነው።' : 'Authentic & Verified Document Record.'; ?></strong>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <th><?php echo $is_am_vc ? 'የመለያ ቁጥር (Serial No)' : 'Serial Number'; ?></th>
                            <td><strong><?php echo htmlspecialchars((string)($verify_result['doc_serial'] ?? '')); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php echo $is_am_vc ? 'የሰነድ ዓይነት' : 'Document Type'; ?></th>
                            <td><?php echo htmlspecialchars((string)($verify_result['doc_type'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $is_am_vc ? 'የተማሪ መታወቂያ (Student ID)' : 'Student ID'; ?></th>
                            <td><?php echo htmlspecialchars((string)($verify_result['student_code'] ?? '-')); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $is_am_vc ? 'ኮሆርት / ምድብ' : 'Cohort'; ?></th>
                            <td><?php echo htmlspecialchars((string)($verify_result['cohort_name'] ?? '-')); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $is_am_vc ? 'የተሰጠበት ቀን' : 'Issued Date'; ?></th>
                            <td><?php echo htmlspecialchars((string)($verify_result['created_at'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $is_am_vc ? 'ማስታወሻ' : 'Remarks'; ?></th>
                            <td><?php echo htmlspecialchars((string)($verify_result['remarks'] ?? '-')); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function vcHandleBack(e, fallbackUrl) {
    if (document.referrer && document.referrer.indexOf(window.location.origin) === 0 && window.history.length > 1) {
        e.preventDefault();
        window.history.back();
        return false;
    }
    if (fallbackUrl) {
        window.location.href = fallbackUrl;
        return false;
    }
    return true;
}
</script>
<?php include 'includes/footer.php'; ?>