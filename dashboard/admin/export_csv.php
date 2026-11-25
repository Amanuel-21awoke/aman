<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin', 'coordinator', 'qa_evaluator', 'finance_officer']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inu_training_hub_enrollments_' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['Enrollment ID', 'Trainee Name', 'Email', 'Organization', 'Course Code', 'Course Title', 'Enrollment Status', 'Payment Status', 'Attendance %', 'Score %', 'Certificate Code']);

$q = $conn->query("
    SELECT e.id, u.name, u.email, e.organization, p.code, p.title_en, e.status, e.payment_status, e.attendance_rate, e.assessment_score, e.certificate_code
    FROM enrollments e
    JOIN users u ON e.trainee_id = u.id
    JOIN training_programs p ON e.program_id = p.id
    ORDER BY e.id ASC
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        fputcsv($out, $row);
    }
}
fclose($out);
exit();
