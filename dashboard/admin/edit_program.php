<?php
require_once '../../includes/functions.php';
require_once '../../backend/db_connect.php';
require_role(['admin']);

$pid = (int)($_GET['id'] ?? $_POST['program_id'] ?? 0);
$msg = '';
$err_msg = '';

// Detect actual columns on training_programs so it works across schema versions
$tp_cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM training_programs");
if ($col_res) {
    while ($r = $col_res->fetch_assoc()) {
        $tp_cols[$r['Field']] = true;
    }
}

$fee_col = isset($tp_cols['fee_etb']) ? 'fee_etb' : (isset($tp_cols['fee_birr']) ? 'fee_birr' : null);
$desc_col = isset($tp_cols['description']) ? 'description' : (isset($tp_cols['description_en']) ? 'description_en' : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid   = (int)($_POST['program_id'] ?? 0);
    $code  = clean_input($_POST['code'] ?? '');
    $cat   = clean_input($_POST['category'] ?? 'ICT & Data Analytics');
    $ten   = clean_input($_POST['title_en'] ?? '');
    $tam   = clean_input($_POST['title_am'] ?? '');
    $desc  = clean_input($_POST['description'] ?? '');
    $days  = max(1, (int)($_POST['duration_days'] ?? 5));
    $fee   = max(0.0, (float)($_POST['fee_etb'] ?? 0));
    $st    = ($_POST['status'] ?? 'Open') === 'Closed' ? 'Closed' : 'Open';

    if ($pid > 0 && $code !== '' && $ten !== '') {
        $sets = ["code = ?", "title_en = ?", "title_am = ?", "category = ?", "duration_days = ?"];
        $types = "ssssi";
        $params = [$code, $ten, $tam, $cat, $days];

        if ($fee_col !== null) {
            $sets[] = "{$fee_col} = ?";
            $types .= "d";
            $params[] = $fee;
        }
        if ($desc_col !== null) {
            $sets[] = "{$desc_col} = ?";
            $types .= "s";
            $params[] = $desc;
        }
        $sets[] = "status = ?";
        $types .= "s";
        $params[] = $st;

        $types .= "i";
        $params[] = $pid;

        $sql = "UPDATE training_programs SET " . implode(", ", $sets) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $msg = "Training program updated successfully.";
            } else {
                $err_msg = "Failed to update training program.";
            }
            $stmt->close();
        } else {
            $err_msg = "Database query error: " . $conn->error;
        }
    } else {
        $err_msg = "Program code and English title are required.";
    }
}

try {
    $cats = $conn->query("SELECT name FROM categories ORDER BY name ASC");
} catch (mysqli_sql_exception $e) {
    $cats = false;
}
$cat_list = [];
if ($cats && $cats->num_rows > 0) {
    while ($c = $cats->fetch_assoc()) $cat_list[] = $c['name'];
} else {
    $cat_list = ['ICT & Data Analytics', 'Pedagogy & Digital Teaching', 'Entrepreneurship & Agribusiness', 'Green Economy & Environment'];
}

$stmt_p = $conn->prepare("SELECT * FROM training_programs WHERE id = ?");
$stmt_p->bind_param("i", $pid);
$stmt_p->execute();
$prog = $stmt_p->get_result()->fetch_assoc();
$stmt_p->close();

$cur_desc = '';
if ($prog) {
    $cur_desc = $prog['description'] ?? ($prog['description_en'] ?? '');
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የስልጠና ፕሮግራም ማስተካከያ ገጽ' : 'Edit Training Program Workspace'; ?></h1>
            <p style="color:#64748b;">Dedicated page for editing training code, category, titles, duration, fee, status, and description.</p>
        </div>
        <a href="programs.php" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Back to Manage Programs</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err_msg): ?><div class="alert alert-error"><?php echo htmlspecialchars($err_msg); ?></div><?php endif; ?>

    <?php if ($prog): ?>
    <div class="card-box">
        <form method="POST" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; align-items:end;">
            <input type="hidden" name="program_id" value="<?php echo (int)$prog['id']; ?>">
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የፕሮግራም ኮድ *' : 'Program Code *'; ?></label><input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($prog['code']); ?>" required></div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ምድብ *' : 'Category *'; ?></label>
                <select name="category" class="form-control">
                    <?php $cur_cat = $prog['category'] ?? 'ICT & Data Analytics'; foreach ($cat_list as $cname): ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>" <?php echo ($cur_cat === $cname) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ርዕስ (እንግሊዝኛ) *' : 'Title (English) *'; ?></label><input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($prog['title_en']); ?>" required></div>
            <div class="form-group" style="margin:0;"><label>ርዕስ (አማርኛ)</label><input type="text" name="title_am" class="form-control" value="<?php echo htmlspecialchars($prog['title_am'] ?? ''); ?>"></div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ቆይታ (ቀናት)' : 'Duration (Days)'; ?></label><input type="number" name="duration_days" class="form-control" value="<?php echo (int)$prog['duration_days']; ?>" min="1"></div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ክፍያ (ብር)' : 'Fee (ETB)'; ?></label><input type="number" name="fee_etb" class="form-control" value="<?php echo htmlspecialchars((string)($prog['fee_etb'] ?? ($prog['fee_birr'] ?? 0))); ?>" min="0" step="0.01"></div>
            <div class="form-group" style="margin:0;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሁኔታ' : 'Status'; ?></label>
                <select name="status" class="form-control">
                    <option value="Open" <?php echo ($prog['status'] === 'Open') ? 'selected' : ''; ?>><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ክፍት (የታተመ)' : 'Open (Published)'; ?></option>
                    <option value="Closed" <?php echo ($prog['status'] === 'Closed') ? 'selected' : ''; ?>><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ዝግ / ረቂቅ' : 'Closed (Draft)'; ?></option>
                </select>
            </div>
            <div class="form-group" style="margin:0; grid-column:1/-1;"><label><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'መግለጫ' : 'Description'; ?></label><textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($cur_desc); ?></textarea></div>
            <div style="grid-column:1/-1; display:flex; gap:0.6rem;">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Program Changes</button>
                <a href="programs.php" class="btn btn-outline"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሰርዝ' : 'Cancel'; ?></a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="card-box">
        <p>Program record not found.</p>
        <a href="programs.php" class="btn btn-primary btn-sm"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ወደ ፕሮግራም አስተዳደር ተመለስ' : 'Return to Manage Programs'; ?></a>
    </div>
    <?php endif; ?>
</div>
<?php include '../../includes/footer.php'; ?>