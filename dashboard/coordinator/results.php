<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['coordinator', 'trainer', 'admin']);

$uid = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'record_exam_mark') {
        $ex_id = (int)($_POST['exam_id'] ?? 0);
        $tr_id = (int)($_POST['trainee_id'] ?? 0);
        $marks = (float)($_POST['marks_obtained'] ?? 0);
        if ($ex_id > 0 && $tr_id > 0) {
            $s = $conn->prepare("INSERT INTO exam_results (exam_id, trainee_id, marks_obtained, approval_status) VALUES (?, ?, ?, 'Pending')");
            $s->bind_param("iid", $ex_id, $tr_id, $marks);
            if ($s->execute()) $msg = "Exam score recorded (Pending Result Approval).";
            $s->close();
        }
    } elseif ($act === 'approve_result') {
        $rid = (int)($_POST['result_id'] ?? 0);
        $eid = (int)($_POST['enrollment_id'] ?? 0);
        $ast = ($_POST['approval_status'] ?? 'Approved') === 'Rejected' ? 'Rejected' : 'Approved';
        if ($rid > 0) {
            $s = $conn->prepare("UPDATE exam_results SET approval_status = ?, approved_by = ? WHERE id = ?");
            $s->bind_param("sii", $ast, $uid, $rid);
            $s->execute();
            $s->close();
        }
        if ($eid > 0 && $ast === 'Approved') {
            $u2 = $conn->prepare("UPDATE enrollments SET result_approved = 1, status = 'Completed' WHERE id = ?");
            $u2->bind_param("i", $eid);
            $u2->execute();
            $u2->close();
        }
        $msg = "Examination result status updated to {$ast}!";
    }
}

$exams    = $conn->query("SELECT x.*, p.code, p.title_en FROM exams x JOIN training_programs p ON x.program_id = p.id ORDER BY x.id DESC");
$trainees = $conn->query("SELECT id, name, email FROM users WHERE role = 'trainee' ORDER BY name ASC");
$results  = $conn->query("
    SELECT r.*, x.exam_title, x.total_marks, x.pass_marks, u.name AS trainee_name, p.code,
           (SELECT e.id FROM enrollments e WHERE e.trainee_id = r.trainee_id AND e.program_id = x.program_id LIMIT 1) AS enrollment_id
    FROM exam_results r
    JOIN exams x ON r.exam_id = x.id
    JOIN training_programs p ON x.program_id = p.id
    JOIN users u ON r.trainee_id = u.id
    ORDER BY r.recorded_at DESC
");

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1>Examination &amp; Result Approval</h1>
            <p style="color:#64748b;">Step 8 &amp; 9 Workflow: Record assessment marks and approve qualified results for certificate generation.</p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="card-box" style="margin-bottom:1.5rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-pencil-fill"></i> Record Trainee Exam Score</h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:0.85rem;">
            <input type="hidden" name="action" value="record_exam_mark">
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፈተና' : 'Exam'; ?></label>
                <select name="exam_id" class="form-control" required>
                    <?php if ($exams): while ($x = $exams->fetch_assoc()): ?>
                        <option value="<?php echo (int)$x['id']; ?>"><?php echo htmlspecialchars($x['code'] . ' - ' . $x['exam_title'] . ' (Pass: ' . $x['pass_marks'] . '/' . $x['total_marks'] . ')'); ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሰልጣኝ' : 'Trainee'; ?></label>
                <select name="trainee_id" class="form-control" required>
                    <?php if ($trainees): while ($t = $trainees->fetch_assoc()): ?>
                        <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name'] . ' (' . $t['email'] . ')'); ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተገኘ ውጤት' : 'Marks Obtained'; ?></label>
                <input type="number" step="0.5" min="0" max="100" name="marks_obtained" class="form-control" required>
            </div>
            <div style="display:flex; align-items:end;"><button type="submit" class="btn btn-primary"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የፈተና ውጤት አስቀምጥ' : 'Save Exam Result'; ?></button></div>
        </form>
    </div>

    <div class="card-box">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-patch-check-fill"></i> Result Verification &amp; Approval</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፈተና' : 'Exam'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሰልጣኝ' : 'Trainee'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ውጤት' : 'Marks'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የማለፊያ ነጥብ' : 'Pass Mark'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የማጽደቅ ሁኔታ' : 'Approval Status'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አጽድቅ / ውድቅ አድርግ' : 'Approve / Reject'; ?></th></tr></thead>
                <tbody>
                    <?php if ($results && $results->num_rows > 0): while ($r = $results->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['code']); ?></td>
                        <td><?php echo htmlspecialchars($r['exam_title']); ?></td>
                        <td><strong><?php echo htmlspecialchars($r['trainee_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['marks_obtained'] . ' / ' . $r['total_marks']); ?></td>
                        <td><?php echo htmlspecialchars($r['pass_marks']); ?></td>
                        <td><?php echo status_badge($r['approval_status']); ?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:0.35rem;">
                                <input type="hidden" name="action" value="approve_result">
                                <input type="hidden" name="result_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="enrollment_id" value="<?php echo (int)($r['enrollment_id'] ?? 0); ?>">
                                <button type="submit" name="approval_status" value="Approved" class="btn btn-primary btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አጽድቅ' : 'Approve'; ?></button>
                                <button type="submit" name="approval_status" value="Rejected" class="btn btn-outline btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ውድቅ አድርግ' : 'Reject'; ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7"><?php echo htmlspecialchars($lang['no_records']); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
