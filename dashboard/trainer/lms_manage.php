<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainer', 'coordinator', 'admin']);

$uid = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'add_lesson') {
        $pid   = (int)($_POST['program_id'] ?? 1);
        $unit  = clean_input($_POST['unit_title'] ?? '');
        $title = clean_input($_POST['lesson_title'] ?? '');
        $vurl  = clean_input($_POST['video_url'] ?? '');
        $sum   = clean_input($_POST['content_summary'] ?? '');
        if ($unit !== '' && $title !== '') {
            $s = $conn->prepare("INSERT INTO lessons (program_id, unit_title, lesson_title, video_url, content_summary) VALUES (?, ?, ?, ?, ?)");
            $s->bind_param("issss", $pid, $unit, $title, $vurl, $sum);
            if ($s->execute()) $msg = "Learning unit & lesson added!";
            $s->close();
        }
    } elseif ($act === 'add_assignment') {
        $pid  = (int)($_POST['program_id'] ?? 1);
        $tit  = clean_input($_POST['title'] ?? '');
        $inst = clean_input($_POST['instructions'] ?? '');
        $due  = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $maxs = (float)($_POST['max_score'] ?? 100);
        if ($tit !== '') {
            $s = $conn->prepare("INSERT INTO assignments (program_id, title, instructions, due_date, max_score) VALUES (?, ?, ?, ?, ?)");
            $s->bind_param("isssd", $pid, $tit, $inst, $due, $maxs);
            if ($s->execute()) $msg = "Assignment published to trainees!";
            $s->close();
        }
    } elseif ($act === 'grade_submission') {
        $sid   = (int)($_POST['submission_id'] ?? 0);
        $score = (float)($_POST['score'] ?? 0);
        $fb    = clean_input($_POST['trainer_feedback'] ?? '');
        if ($sid > 0) {
            $s = $conn->prepare("UPDATE submissions SET score = ?, trainer_feedback = ? WHERE id = ?");
            $s->bind_param("dsi", $score, $fb, $sid);
            if ($s->execute()) $msg = "Submission graded & feedback saved!";
            $s->close();
        }
    } elseif ($act === 'add_exam') {
        $pid   = (int)($_POST['program_id'] ?? 1);
        $etit  = clean_input($_POST['exam_title'] ?? '');
        $edate = $_POST['exam_date'] ?? date('Y-m-d');
        $tot   = (float)($_POST['total_marks'] ?? 100);
        $pass  = (float)($_POST['pass_marks'] ?? 60);
        if ($etit !== '') {
            $s = $conn->prepare("INSERT INTO exams (program_id, exam_title, exam_date, total_marks, pass_marks) VALUES (?, ?, ?, ?, ?)");
            $s->bind_param("issdd", $pid, $etit, $edate, $tot, $pass);
            if ($s->execute()) $msg = "Exam scheduled!";
            $s->close();
        }
    } elseif ($act === 'post_discussion') {
        $pid  = (int)($_POST['program_id'] ?? 1);
        $dtit = clean_input($_POST['title'] ?? '');
        $dmsg = clean_input($_POST['message'] ?? '');
        if ($dtit !== '' && $dmsg !== '') {
            $s = $conn->prepare("INSERT INTO course_discussions (program_id, author_id, title, message) VALUES (?, ?, ?, ?)");
            $s->bind_param("iiss", $pid, $uid, $dtit, $dmsg);
            if ($s->execute()) $msg = "Course discussion/announcement posted!";
            $s->close();
        }
    }
}

$progs = $conn->query("SELECT id, code, title_en FROM training_programs ORDER BY id ASC");
$prog_list = [];
while ($p = $progs->fetch_assoc()) $prog_list[] = $p;

$subs = $conn->query("SELECT s.*, a.title AS assignment_title, u.name AS trainee_name FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN users u ON s.trainee_id = u.id ORDER BY s.submitted_at DESC");

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1>LMS, Lessons, Assignments &amp; Exams</h1>
            <p style="color:#64748b;">Manage learning units, video lessons, assignments, student feedback, exams, and course discussions.</p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="cards-grid" style="margin-bottom:1.5rem;">
        <!-- Add Learning Unit / Video Lesson -->
        <div class="card-box">
            <h3 style="color:var(--primary-color); margin-bottom:0.8rem;"><i class="bi bi-play-circle-fill"></i> Add Learning Unit &amp; Video Lesson</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_lesson">
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></label>
                    <select name="program_id" class="form-control">
                        <?php foreach ($prog_list as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code'] . ' - ' . $p['title_en']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የመማሪያ ክፍል ርዕስ' : 'Learning Unit Title'; ?></label><input type="text" name="unit_title" class="form-control" placeholder="Unit 1: Core Concepts" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የትምህርት ርዕስ' : 'Lesson Title'; ?></label><input type="text" name="lesson_title" class="form-control" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የቪዲዮ አድራሻ (MP4 / YouTube)' : 'Video URL (MP4 / YouTube / Portal)'; ?></label><input type="text" name="video_url" class="form-control" placeholder="https://..."></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የትምህርት ማጠቃለያ' : 'Lesson Summary'; ?></label><textarea name="content_summary" class="form-control" rows="2"></textarea></div>
                <button type="submit" class="btn btn-primary"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ትምህርት አስቀምጥ' : 'Save Lesson'; ?></button>
            </form>
        </div>

        <!-- Create Assignment -->
        <div class="card-box">
            <h3 style="color:var(--primary-color); margin-bottom:0.8rem;"><i class="bi bi-journal-plus"></i> Create Assignment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_assignment">
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></label>
                    <select name="program_id" class="form-control">
                        <?php foreach ($prog_list as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code'] . ' - ' . $p['title_en']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የምዘና ርዕስ' : 'Assignment Title'; ?></label><input type="text" name="title" class="form-control" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'መመሪያዎች' : 'Instructions'; ?></label><textarea name="instructions" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የማለቂያ ቀን' : 'Due Date'; ?></label><input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ከፍተኛ ነጥብ' : 'Max Score'; ?></label><input type="number" name="max_score" class="form-control" value="100"></div>
                <button type="submit" class="btn btn-primary"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ምዘና አሳትም' : 'Publish Assignment'; ?></button>
            </form>
        </div>

        <!-- Schedule Exam & Post Discussion -->
        <div class="card-box">
            <h3 style="color:var(--primary-color); margin-bottom:0.8rem;"><i class="bi bi-file-earmark-text-fill"></i> Create Exam &amp; Post Discussion</h3>
            <form method="POST" style="margin-bottom:1.1rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;">
                <input type="hidden" name="action" value="add_exam">
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></label>
                    <select name="program_id" class="form-control">
                        <?php foreach ($prog_list as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code'] . ' - ' . $p['title_en']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የፈተና ርዕስ' : 'Exam Title'; ?></label><input type="text" name="exam_title" class="form-control" placeholder="Final Assessment" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የፈተና ቀን' : 'Exam Date'; ?></label><input type="date" name="exam_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+10 days')); ?>"></div>
                <button type="submit" class="btn btn-secondary btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፈተና መርሐ-ግብር አውጣ' : 'Schedule Exam'; ?></button>
            </form>

            <form method="POST">
                <input type="hidden" name="action" value="post_discussion">
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የውይይት / የማስታወቂያ ርዕስ' : 'Discussion / Announcement Title'; ?></label><input type="text" name="title" class="form-control" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'መልዕክት' : 'Message'; ?></label><textarea name="message" class="form-control" rows="2" required></textarea></div>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ወደ ኮርስ ሰሌዳ ይለጥፉ' : 'Post to Course Board'; ?></button>
            </form>
        </div>
    </div>

    <!-- Grade Student Submissions & Give Trainer Feedback -->
    <div class="card-box">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-chat-square-text-fill"></i> Student Submissions &amp; Trainer Feedback</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሰልጣኝ' : 'Trainee'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የቤት ስራ / ምዘና' : 'Assignment'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የቀረበ ስራ' : 'Submission'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ውጤት' : 'Score'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአሰልጣኝ አስተያየት' : 'Trainer Feedback'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አስቀምጥ' : 'Save'; ?></th></tr></thead>
                <tbody>
                    <?php if ($subs && $subs->num_rows > 0): while ($s = $subs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['trainee_name']); ?></td>
                        <td><?php echo htmlspecialchars($s['assignment_title']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($s['submission_text']); ?>
                            <?php if (!empty($s['attachment_url'])): ?>
                                <div><a href="<?php echo htmlspecialchars($s['attachment_url']); ?>" target="_blank"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አባሪ ፋይል' : 'Attachment'; ?></a></div>
                            <?php endif; ?>
                        </td>
                        <form method="POST">
                            <input type="hidden" name="action" value="grade_submission">
                            <input type="hidden" name="submission_id" value="<?php echo (int)$s['id']; ?>">
                            <td><input type="number" step="0.5" name="score" value="<?php echo htmlspecialchars($s['score'] ?? ''); ?>" class="form-control" style="width:85px;"></td>
                            <td><input type="text" name="trainer_feedback" value="<?php echo htmlspecialchars($s['trainer_feedback'] ?? ''); ?>" class="form-control" placeholder="Feedback..."></td>
                            <td><button type="submit" class="btn btn-primary btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አስቀምጥ' : 'Save'; ?></button></td>
                        </form>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6"><?php echo htmlspecialchars($lang['no_records']); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
