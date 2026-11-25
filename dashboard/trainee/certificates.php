<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainee']);

$tid = (int)$_SESSION['user_id'];
$certs = $conn->query("
    SELECT e.*, p.title_en, p.code, u.name AS trainee_name
    FROM enrollments e
    JOIN training_programs p ON e.program_id = p.id
    JOIN users u ON e.trainee_id = u.id
    WHERE e.trainee_id = {$tid} AND e.certificate_issued = 1
");

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header"><h1><?php echo htmlspecialchars($lang['my_certificates']); ?></h1></div>
    <div class="cards-grid">
        <?php if ($certs && $certs->num_rows > 0): while ($c = $certs->fetch_assoc()): ?>
            <div class="card-box" style="border-left:5px solid var(--accent-color);">
                <div style="font-size:0.8rem; color:#64748b;">Certificate ID: <strong><?php echo htmlspecialchars($c['certificate_code']); ?></strong></div>
                <h3 style="color:var(--primary-color); margin:0.4rem 0;"><?php echo htmlspecialchars($c['title_en']); ?></h3>
                <p style="font-size:0.9rem;">Awarded to: <strong><?php echo htmlspecialchars($c['trainee_name']); ?></strong></p>
                <p style="font-size:0.85rem; color:#059669; margin:0.35rem 0 0.85rem;">Attendance: <?php echo htmlspecialchars($c['attendance_rate']); ?>% • Score: <?php echo htmlspecialchars($c['assessment_score']); ?>%</p>
                <a href="certificate_print.php?code=<?php echo urlencode($c['certificate_code']); ?>" target="_blank" class="btn btn-primary btn-sm">
                    <i class="bi bi-printer"></i> Print / Save Certificate
                </a>
            </div>
        <?php endwhile; else: ?>
            <div class="card-box"><p><?php echo htmlspecialchars($lang['no_records']); ?></p></div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
