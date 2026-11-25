<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainee', 'coordinator', 'certificate_officer', 'admin'], '../../');

$cert_code = clean_input($_GET['code'] ?? '');
$tid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'trainee';

if ($role === 'trainee') {
    $stmt = $conn->prepare("
        SELECT e.*, p.title_en, p.title_am, p.code, p.duration_days, u.name AS trainee_name
        FROM enrollments e
        JOIN training_programs p ON e.program_id = p.id
        JOIN users u ON e.trainee_id = u.id
        WHERE e.certificate_code = ? AND e.trainee_id = ? AND e.certificate_issued = 1
    ");
    $stmt->bind_param("si", $cert_code, $tid);
} else {
    $stmt = $conn->prepare("
        SELECT e.*, p.title_en, p.title_am, p.code, p.duration_days, u.name AS trainee_name
        FROM enrollments e
        JOIN training_programs p ON e.program_id = p.id
        JOIN users u ON e.trainee_id = u.id
        WHERE e.certificate_code = ? AND e.certificate_issued = 1
    ");
    $stmt->bind_param("s", $cert_code);
}
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cert) {
    die("Certificate not found or not yet issued.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate - <?php echo htmlspecialchars($cert['certificate_code']); ?></title>
    <style>
        body { font-family: Georgia, serif; background: #f1f5f9; padding: 2rem; color: #0f172a; }
        .cert-box { max-width: 860px; margin: 0 auto; background: #fff; border: 10px solid #1e3a8a; outline: 3px solid #f59e0b; padding: 3rem; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.12); }
        .univ { font-size: 1.5rem; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; }
        .hub { font-size: 1.05rem; color: #0d9488; margin-top: 0.25rem; }
        .title { font-size: 2.2rem; font-weight: bold; color: #0f172a; margin: 1.5rem 0 0.5rem; }
        .recipient { font-size: 1.8rem; font-weight: bold; color: #1e3a8a; border-bottom: 2px solid #cbd5e1; display: inline-block; padding: 0.25rem 2rem; margin: 0.75rem 0; }
        .meta { margin-top: 2rem; display: flex; justify-content: space-between; font-size: 0.9rem; color: #475569; }
        .no-print { text-align: center; margin-bottom: 1.25rem; }
        .no-print button { padding: 0.6rem 1.4rem; font-size: 1rem; background: #1e3a8a; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        @media print { .no-print { display: none; } body { background: #fff; padding: 0; } .cert-box { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አትም / እንደ PDF አስቀምጥ' : 'Print / Save as PDF'; ?></button>
    </div>
    <div class="cert-box">
        <div class="univ"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'እንጅባራ ዩኒቨርሲቲ' : 'Injibara University'; ?></div>
        <div class="hub"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአጭር ጊዜ ስልጠና ማዕከል (INU-STTH)' : 'Short-Term Training Hub (INU-STTH)'; ?></div>
        <div class="title"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የስልጠና ማጠናቀቂያ ሰርተፍኬት' : 'Certificate of Completion'; ?></div>
        <p><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ይህ ሰርተፍኬት የሚያረጋግጠው፦' : 'This is to certify that'; ?></p>
        <div class="recipient"><?php echo htmlspecialchars($cert['trainee_name']); ?></div>
        <p style="font-size: 1.05rem; line-height: 1.7; max-width: 660px; margin: 0.75rem auto;">
            has successfully completed the short-term training course<br>
            <strong><?php echo htmlspecialchars($cert['code'] . ' — ' . $cert['title_en']); ?></strong><br>
            (<?php echo (int)$cert['duration_days']; ?> Days • Attendance: <?php echo htmlspecialchars($cert['attendance_rate']); ?>% • Score: <?php echo htmlspecialchars($cert['assessment_score']); ?>%)
        </p>
        <div class="meta">
            <div><strong><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሰርተፍኬት ቁጥር፦' : 'Certificate No:'; ?></strong> <?php echo htmlspecialchars($cert['certificate_code']); ?></div>
            <div><strong><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ቀን፦' : 'Date:'; ?></strong> <?php echo date('F d, Y'); ?></div>
            <div><strong><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የእንጅባራ ስልጠና ማዕከል' : 'INU Training Hub'; ?></strong></div>
        </div>
    </div>
</body>
</html>
