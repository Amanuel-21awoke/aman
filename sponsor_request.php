<?php
require_once 'includes/functions.php';
require_once 'backend/db_connect.php';

$msg = '';
$err = '';
$progs = $conn->query("SELECT id, code, title_en FROM training_programs ORDER BY id ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org     = clean_input($_POST['organization_name'] ?? '');
    $person  = clean_input($_POST['contact_person'] ?? '');
    $email   = strtolower(trim(clean_input($_POST['contact_email'] ?? '')));
    $phone   = sanitize_phone_10($_POST['contact_phone'] ?? '');
    $prog_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $count   = max(1, (int)($_POST['estimated_trainees'] ?? 15));
    $month   = clean_input($_POST['preferred_month'] ?? '');
    $details = clean_input($_POST['details'] ?? '');

    if ($org === '' || $person === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = $lang['fill_all_fields'];
    } else {
        $stmt = $conn->prepare("INSERT INTO sponsor_requests (organization_name, contact_person, contact_email, contact_phone, program_id, estimated_trainees, preferred_month, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiiss", $org, $person, $email, $phone, $prog_id, $count, $month, $details);
        if ($stmt->execute()) {
            $msg = "Your institutional training request has been submitted to Injibara University Training Hub.";
        } else {
            $err = $lang['error_occurred'];
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>
<div class="section" style="max-width: 780px;">
    <div class="card-box">
        <h2 style="color:var(--primary-color); margin-bottom:0.4rem;">
            <i class="bi bi-building"></i> Institutional & Sponsor Group Training Request
        </h2>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:1.25rem;">
            Request a tailored or scheduled short-term training cohort for government offices, NGOs, schools, or enterprises.
        </p>

        <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

        <form method="POST">
            <div class="cards-grid">
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተቋም / የቢሮ ስም' : 'Organization / Bureau Name'; ?></label><input type="text" name="organization_name" class="form-control" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የመገናኛ ሰው' : 'Focal / Contact Person'; ?></label><input type="text" name="contact_person" class="form-control" required></div>
            </div>
            <div class="cards-grid">
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ኢሜይል አድራሻ' : 'Email Address'; ?></label><input type="email" name="contact_email" class="form-control" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ስልክ ቁጥር' : 'Phone Number'; ?></label><input type="text" name="contact_phone" class="form-control" placeholder="0911223344" maxlength="10" pattern="\d{1,10}" required></div>
            </div>
            <div class="cards-grid">
                <div class="form-group">
                    <label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የታለመ የስልጠና ፕሮግራም' : 'Target Training Program'; ?></label>
                    <select name="program_id" class="form-control">
                        <option value=""><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አጠቃላይ የማዕከል ስርዓተ-ትምህርት' : 'Custom / General Hub Curriculum'; ?></option>
                        <?php if ($progs): while ($p = $progs->fetch_assoc()): ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['code'] . ' - ' . $p['title_en']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተገመቱ ሰልጣኞች' : 'Estimated Trainees'; ?></label><input type="number" name="estimated_trainees" class="form-control" min="1" value="20" required></div>
                <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሚመረጥ ወቅት / ወር' : 'Preferred Period / Month'; ?></label><input type="text" name="preferred_month" class="form-control" placeholder="e.g., January 2027"></div>
            </div>
            <div class="form-group"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የስልጠና ዓላማዎች እና ማስታወሻዎች' : 'Training Objectives & Notes'; ?></label><textarea name="details" class="form-control" rows="3"></textarea></div>
            <button type="submit" class="btn btn-primary"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተቋም ጥያቄ አስገባ' : 'Submit Institutional Request'; ?></button>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
