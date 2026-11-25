<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['trainee']);

$uid = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'update_progress') {
        $eid  = (int)($_POST['enrollment_id'] ?? 0);
        $prog = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
        if ($eid > 0) {
            $s = $conn->prepare("UPDATE enrollments SET progress_percent = ? WHERE id = ? AND trainee_id = ?");
            $s->bind_param("iii", $prog, $eid, $uid);
            if ($s->execute()) $msg = "Course progress updated to {$prog}%!";
            $s->close();
        }
    } elseif ($act === 'submit_assignment') {
        $aid  = (int)($_POST['assignment_id'] ?? 0);
        $text = clean_input($_POST['submission_text'] ?? '');
        $url  = clean_input($_POST['attachment_url'] ?? '');
        if ($aid > 0 && $text !== '') {
            $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, trainee_id, submission_text, attachment_url) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $aid, $uid, $text, $url);
            if ($stmt->execute()) $msg = "Assignment submitted successfully!";
            $stmt->close();
        }
    } elseif ($act === 'post_discussion') {
        $pid  = (int)($_POST['program_id'] ?? 1);
        $tit  = clean_input($_POST['title'] ?? '');
        $dmsg = clean_input($_POST['message'] ?? '');
        if ($tit !== '' && $dmsg !== '') {
            $stmt = $conn->prepare("INSERT INTO course_discussions (program_id, author_id, title, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $pid, $uid, $tit, $dmsg);
            if ($stmt->execute()) $msg = "Discussion posted!";
            $stmt->close();
        }
    } elseif ($act === 'submit_support') {
        $pid     = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $ttype   = clean_input($_POST['ticket_type'] ?? 'Support Request');
        $subj    = clean_input($_POST['subject'] ?? '');
        $details = clean_input($_POST['details'] ?? '');
        if ($subj !== '' && $details !== '') {
            $stmt = $conn->prepare("INSERT INTO complaints_support (user_id, program_id, ticket_type, subject, details) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $uid, $pid, $ttype, $subj, $details);
            if ($stmt->execute()) $msg = "Support / complaint ticket submitted!";
            $stmt->close();
        }
    }
}

$my_progress = $conn->query("SELECT e.id, e.progress_percent, e.status, p.code, p.title_en FROM enrollments e JOIN training_programs p ON e.program_id = p.id WHERE e.trainee_id = {$uid} ORDER BY e.enrolled_at DESC");
$materials   = $conn->query("SELECT m.*, p.code, p.title_en FROM training_materials m JOIN training_programs p ON m.program_id = p.id ORDER BY m.uploaded_at DESC");
$lessons     = $conn->query("SELECT l.*, p.code, p.title_en FROM lessons l JOIN training_programs p ON l.program_id = p.id ORDER BY l.program_id ASC, l.sort_order ASC");
$assignments = $conn->query("SELECT a.*, p.code, p.title_en FROM assignments a JOIN training_programs p ON a.program_id = p.id ORDER BY a.due_date ASC");
$my_subs     = $conn->query("SELECT s.*, a.title AS assignment_title FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.trainee_id = {$uid} ORDER BY s.submitted_at DESC");
$discussions = $conn->query("SELECT d.*, p.code, u.name AS author_name FROM course_discussions d JOIN training_programs p ON d.program_id = p.id JOIN users u ON d.author_id = u.id ORDER BY d.created_at DESC LIMIT 20");
$progs       = $conn->query("SELECT id, code, title_en FROM training_programs ORDER BY id ASC");
$prog_arr    = [];
if ($progs) while ($pr = $progs->fetch_assoc()) $prog_arr[] = $pr;
$my_tickets  = $conn->query("SELECT * FROM complaints_support WHERE user_id = {$uid} ORDER BY created_at DESC");

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1>Learning Management System (LMS) &amp; Support</h1>
            <p style="color:#64748b;">Course progress tracking, PDF &amp; video lessons, downloadable resources, assignments, trainer feedback, discussions &amp; support.</p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <!-- 1. Course Progress Tracking -->
    <div class="card-box" style="margin-bottom:1.5rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-graph-up-arrow"></i> Course Progress Tracking</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የስልጠና ፕሮግራም' : 'Training Program'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የምዝገባ ሁኔታ' : 'Enrollment Status'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የማጠናቀቂያ ሂደት' : 'Completion Progress'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሂደት አዘምን' : 'Update Progress'; ?></th></tr></thead>
                <tbody>
                    <?php if ($my_progress && $my_progress->num_rows > 0): while ($mp = $my_progress->fetch_assoc()): $pct = (int)$mp['progress_percent']; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($mp['code'] . ' - ' . $mp['title_en']); ?></strong></td>
                        <td><?php echo status_badge($mp['status']); ?></td>
                        <td>
                            <div style="background:#e2e8f0; border-radius:999px; height:12px; width:160px; overflow:hidden; display:inline-block; vertical-align:middle;">
                                <div style="background:#17663f; height:100%; width:<?php echo $pct; ?>%;"></div>
                            </div>
                            <strong style="margin-left:0.45rem;"><?php echo $pct; ?>%</strong>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; gap:0.4rem; align-items:center;">
                                <input type="hidden" name="action" value="update_progress">
                                <input type="hidden" name="enrollment_id" value="<?php echo (int)$mp['id']; ?>">
                                <select name="progress_percent" class="form-control" style="padding:0.25rem; width:auto;">
                                    <?php foreach ([0, 25, 50, 75, 100] as $step): ?>
                                        <option value="<?php echo $step; ?>" <?php echo ($pct === $step) ? 'selected' : ''; ?>><?php echo $step; ?>%</option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አስቀምጥ' : 'Save'; ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4">Enroll in a training program to track your learning progress.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. Downloadable PDF Materials & Video Resources -->
    <div class="card-box" style="margin-bottom:1.5rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-file-earmark-pdf-fill"></i> Downloadable PDF Materials &amp; Resources</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ርዕስ' : 'Title'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አይነት' : 'Type'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ግብዓት' : 'Resource'; ?></th></tr></thead>
                <tbody>
                    <?php if ($materials && $materials->num_rows > 0): while ($m = $materials->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['code'] . ' - ' . $m['title_en']); ?></td>
                        <td><strong><?php echo htmlspecialchars($m['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($m['resource_type']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($m['resource_url']); ?>" target="_blank" class="btn btn-primary btn-sm"><i class="bi bi-download"></i> Download / Open</a></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records']); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Learning Units & Video Lessons -->
    <div class="card-box" style="margin-bottom:1.5rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-play-btn-fill"></i> Learning Units &amp; Video Lessons</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ክፍል' : 'Unit'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ትምህርት' : 'Lesson'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ማጠቃለያ' : 'Summary'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ቪዲዮ' : 'Video'; ?></th></tr></thead>
                <tbody>
                    <?php if ($lessons && $lessons->num_rows > 0): while ($l = $lessons->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($l['code']); ?></td>
                        <td><?php echo htmlspecialchars($l['unit_title']); ?></td>
                        <td><strong><?php echo htmlspecialchars($l['lesson_title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($l['content_summary']); ?></td>
                        <td><?php if (!empty($l['video_url'])): ?><a href="<?php echo htmlspecialchars($l['video_url']); ?>" target="_blank" class="btn btn-outline btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ትምህርቱን ይመልከቱ' : 'Watch Lesson'; ?></a><?php endif; ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records']); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. Assignments, Student Submissions & Trainer Feedback -->
    <div class="card-box" style="margin-bottom:1.5rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-file-earmark-check-fill"></i> Assignments, Submissions &amp; Trainer Feedback</h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0.8rem; margin-bottom:1rem;">
            <input type="hidden" name="action" value="submit_assignment">
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የቤት ስራ / ምዘና' : 'Assignment'; ?></label>
                <select name="assignment_id" class="form-control" required>
                    <?php if ($assignments): while ($a = $assignments->fetch_assoc()): ?>
                        <option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['code'] . ' - ' . $a['title'] . ' (Due: ' . $a['due_date'] . ')'); ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተሰጠ መልስ / ማስታወሻ' : 'Submission Answer / Notes'; ?></label>
                <input type="text" name="submission_text" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፋይል / አገናኝ (አማራጭ)' : 'File / Link (Optional)'; ?></label>
                <input type="text" name="attachment_url" class="form-control" placeholder="https://...">
            </div>
            <div style="display:flex; align-items:end;"><button type="submit" class="btn btn-primary"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ስራ አስገባ' : 'Submit Work'; ?></button></div>
        </form>

        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የቤት ስራ / ምዘና' : 'Assignment'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የቀረበ ስራ' : 'Submission'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ውጤት' : 'Score'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአሰልጣኝ አስተያየት' : 'Trainer Feedback'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ቀን' : 'Date'; ?></th></tr></thead>
                <tbody>
                    <?php if ($my_subs && $my_subs->num_rows > 0): while ($s = $my_subs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['assignment_title']); ?></td>
                        <td><?php echo htmlspecialchars($s['submission_text']); ?></td>
                        <td><strong><?php echo htmlspecialchars($s['score'] !== null ? $s['score'] : 'Pending'); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['trainer_feedback'] ?? 'Awaiting trainer feedback'); ?></td>
                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($s['submitted_at']))); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5"><?php echo htmlspecialchars($lang['no_records']); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. Course Discussion & Announcements -->
    <div class="card-box" style="margin-bottom:1.5rem;">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-chat-dots-fill"></i> Discussion &amp; Course Announcements</h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0.8rem; margin-bottom:1rem;">
            <input type="hidden" name="action" value="post_discussion">
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></label>
                <select name="program_id" class="form-control">
                    <?php foreach ($prog_arr as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ርዕሰ ጉዳይ' : 'Topic'; ?></label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'መልዕክት' : 'Message'; ?></label><input type="text" name="message" class="form-control" required></div>
            <div style="display:flex; align-items:end;"><button type="submit" class="btn btn-outline"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ውይይት ይለጥፉ' : 'Post Discussion'; ?></button></div>
        </form>
        <?php if ($discussions && $discussions->num_rows > 0): while ($d = $discussions->fetch_assoc()): ?>
            <div style="padding:0.75rem; border-bottom:1px solid #e2e8f0;">
                <strong>[<?php echo htmlspecialchars($d['code']); ?>] <?php echo htmlspecialchars($d['title']); ?></strong>
                <span style="font-size:0.78rem; color:#64748b;"> — by <?php echo htmlspecialchars($d['author_name']); ?> (<?php echo date('M d, Y', strtotime($d['created_at'])); ?>)</span>
                <p style="margin:0.3rem 0 0 0; font-size:0.9rem;"><?php echo htmlspecialchars($d['message']); ?></p>
            </div>
        <?php endwhile; endif; ?>
    </div>

    <!-- 6. Complaints, Support & Refund Requests -->
    <div class="card-box">
        <h3 style="color:var(--primary-color); margin-bottom:0.85rem;"><i class="bi bi-life-preserver"></i> Complaints, Support &amp; Refund Requests</h3>
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.8rem; margin-bottom:1rem;">
            <input type="hidden" name="action" value="submit_support">
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የጥያቄ አይነት' : 'Request Type'; ?></label>
                <select name="ticket_type" class="form-control">
                    <option value="Support Request"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የድጋፍ ጥያቄ' : 'Support Request'; ?></option>
                    <option value="Complaint"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ቅሬታ' : 'Complaint'; ?></option>
                    <option value="Refund Request"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተመላሽ ክፍያ ጥያቄ' : 'Refund Request'; ?></option>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፕሮግራም' : 'Program'; ?></label>
                <select name="program_id" class="form-control">
                    <option value=""><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አጠቃላይ' : 'General'; ?></option>
                    <?php foreach ($prog_arr as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ርዕሰ ጉዳይ' : 'Subject'; ?></label><input type="text" name="subject" class="form-control" required></div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ዝርዝሮች' : 'Details'; ?></label><input type="text" name="details" class="form-control" required></div>
            <div style="display:flex; align-items:end;"><button type="submit" class="btn btn-secondary"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ጥያቄ አስገባ' : 'Submit Ticket'; ?></button></div>
        </form>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አይነት' : 'Type'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ርዕሰ ጉዳይ' : 'Subject'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ዝርዝሮች' : 'Details'; ?></th><th><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሁኔታ' : 'Status'; ?></th></tr></thead>
                <tbody>
                    <?php if ($my_tickets && $my_tickets->num_rows > 0): while ($t = $my_tickets->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($t['ticket_type']); ?></td>
                        <td><strong><?php echo htmlspecialchars($t['subject']); ?></strong></td>
                        <td><?php echo htmlspecialchars($t['details']); ?></td>
                        <td><?php echo htmlspecialchars($t['status']); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records']); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
