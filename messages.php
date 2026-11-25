<?php
require_once 'includes/functions.php';
require_once 'backend/db_connect.php';
check_login();

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? 0);
$notice = '';
$err_msg = '';
$reply_to_id = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : 0;
$reply_subject = '';

if ($reply_to_id > 0) {
    $q_rep = $conn->prepare("SELECT m.sender_id, m.subject, u.name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ? AND (m.receiver_id = ? OR m.sender_id = ?)");
    if ($q_rep) {
        $q_rep->bind_param("iii", $reply_to_id, $uid, $uid);
        $q_rep->execute();
        if ($r_rep = $q_rep->get_result()->fetch_assoc()) {
            $reply_to_id = (int)$r_rep['sender_id'];
            $subj_raw = (string)$r_rep['subject'];
            $reply_subject = (stripos($subj_raw, 'Re:') === 0) ? $subj_raw : ('Re: ' . $subj_raw);
        }
        $q_rep->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? 'send';
    if ($act === 'delete_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        if ($msg_id > 0) {
            $del = $conn->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
            if ($del) {
                $del->bind_param("iii", $msg_id, $uid, $uid);
                if ($del->execute() && $del->affected_rows > 0) {
                    $notice = $lang['success_delete'] ?? ($is_am ? 'መልዕክቱ በተሳካ ሁኔታ ተሰርዟል።' : 'Message deleted successfully.');
                } else {
                    $err_msg = $is_am ? 'መልዕክቱን መሰረዝ አልተቻለም።' : 'Unable to delete message.';
                }
                $del->close();
            }
        }
    } else {
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);
        $raw_subj    = trim((string)($_POST['subject'] ?? ''));
        $raw_body    = trim((string)($_POST['body'] ?? ''));

        $subject = htmlspecialchars_decode(clean_input($raw_subj), ENT_QUOTES);
        $body    = htmlspecialchars_decode(clean_input($raw_body), ENT_QUOTES);

        if ($receiver_id > 0 && $subject !== '' && $body !== '') {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iiss", $uid, $receiver_id, $subject, $body);
                if ($stmt->execute()) {
                    $notice = $is_am ? 'መልዕክቱ በተሳካ ሁኔታ ተልኳል / ተመልሷል።' : 'Message sent / replied successfully!';
                    $reply_to_id = 0;
                    $reply_subject = '';
                } else {
                    $err_msg = $is_am ? 'መልዕክቱን መላክ አልተቻለም።' : 'Failed to send message.';
                }
                $stmt->close();
            }
        } else {
            $err_msg = $lang['fill_all_fields'] ?? ($is_am ? 'እባክዎ ተቀባይ፣ ርዕሰ ጉዳይ እና መልዕክት ያስገቡ።' : 'Please select a recipient and enter subject and message.');
        }
    }
}

$users_arr = [];
$stmt_u = $conn->prepare("SELECT id, name, role FROM users WHERE id != ? AND status = 'active' ORDER BY name ASC");
if ($stmt_u) {
    $stmt_u->bind_param("i", $uid);
    $stmt_u->execute();
    $res_u = $stmt_u->get_result();
    while ($u_row = $res_u->fetch_assoc()) {
        $users_arr[] = $u_row;
    }
    $stmt_u->close();
}

$messages_list = [];
$stmt_m = $conn->prepare("
    SELECT m.*, s.name AS sender_name, s.role AS sender_role, r.name AS receiver_name, r.role AS receiver_role
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    JOIN users r ON m.receiver_id = r.id
    WHERE m.receiver_id = ? OR m.sender_id = ?
    ORDER BY m.created_at DESC
");
if ($stmt_m) {
    $stmt_m->bind_param("ii", $uid, $uid);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    while ($m_row = $res_m->fetch_assoc()) {
        $messages_list[] = $m_row;
    }
    $stmt_m->close();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo htmlspecialchars($lang['messages'] ?? ($is_am ? 'መልዕክቶች' : 'Messages')); ?></h1>
            <p style="color:#64748b; margin-bottom:0;">
                <?php echo $is_am
                    ? 'የእንጅባራ ዩኒቨርሲቲ የአጭር ጊዜ ስልጠና ማዕከል • የውስጥ መልዕክት መላላኪያ እና ቀጥታ ምላሽ'
                    : 'Injibara University Short-Term Training Hub • Direct Internal Messaging & Inline Replies'; ?>
            </p>
        </div>
    </div>

    <?php if ($notice): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($err_msg): ?><div class="alert alert-error"><?php echo htmlspecialchars($err_msg); ?></div><?php endif; ?>

    <div class="cards-grid" style="grid-template-columns: 1fr 1.6fr; align-items:start;">
        <div class="card-box" id="composeBox">
            <h3 style="color:var(--primary-color); margin-bottom:1rem;">
                <i class="bi bi-send-fill"></i> <?php echo $is_am ? 'መልዕክት ይላኩ / ይመልሱ' : 'Send / Reply Message'; ?>
            </h3>
            <form method="POST" id="composeForm">
                <input type="hidden" name="action" value="send">
                <div class="form-group">
                    <label><?php echo $is_am ? 'ተቀባይ' : 'Recipient'; ?></label>
                    <select name="receiver_id" id="composeReceiver" class="form-control" required>
                        <?php foreach ($users_arr as $u):
                            $role_disp = ucwords(str_replace('_', ' ', (string)$u['role']));
                        ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo ($reply_to_id === (int)$u['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name'] . " (" . $role_disp . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo $is_am ? 'ርዕሰ ጉዳይ' : 'Subject'; ?></label>
                    <input type="text" name="subject" id="composeSubject" class="form-control" value="<?php echo htmlspecialchars($reply_subject); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo $is_am ? 'መልዕክት' : 'Message'; ?></label>
                    <textarea name="body" id="composeBody" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-send"></i> <?php echo $is_am ? 'መልዕክት ላክ' : 'Send Message'; ?>
                </button>
            </form>
        </div>

        <div class="card-box">
            <h3 style="color:var(--primary-color); margin-bottom:1rem;">
                <i class="bi bi-chat-dots-fill"></i> <?php echo $is_am ? 'የገቡ እና የተላኩ መልዕክቶች (ቀጥታ ምላሽ ይሰራል)' : 'Inbox & Sent (Direct Reply Enabled)'; ?>
            </h3>
            <?php if (!empty($messages_list)): ?>
                <?php foreach ($messages_list as $m):
                    $other_id   = ((int)$m['sender_id'] === $uid) ? (int)$m['receiver_id'] : (int)$m['sender_id'];
                    $clean_subj = (stripos((string)$m['subject'], 'Re:') === 0) ? (string)$m['subject'] : ('Re: ' . (string)$m['subject']);
                    $ts_val     = strtotime((string)$m['created_at']);
                    $time_str   = $ts_val ? date('M d, H:i', $ts_val) : (string)$m['created_at'];
                ?>
                    <div style="padding:0.9rem; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:0.85rem; background:#f8fafc;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; font-size:0.82rem; color:#64748b;">
                            <span>
                                <strong><?php echo $is_am ? 'ከ፦' : 'From:'; ?></strong> <?php echo htmlspecialchars($m['sender_name']); ?>
                                → <strong><?php echo $is_am ? 'ለ፦' : 'To:'; ?></strong> <?php echo htmlspecialchars($m['receiver_name']); ?>
                            </span>
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <span><?php echo htmlspecialchars($time_str); ?></span>
                                <button type="button" class="btn btn-outline btn-sm" onclick="openInlineReply(<?php echo (int)$m['id']; ?>)">
                                    <i class="bi bi-reply-fill"></i> <?php echo $is_am ? 'መልስ' : 'Reply'; ?>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $is_am ? 'ይህን መልዕክት ለሁሉም መሰረዝ ይፈልጋሉ?' : 'Delete this message for everyone?'; ?>');">
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="message_id" value="<?php echo (int)$m['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i> <?php echo $is_am ? 'ሰርዝ' : 'Delete'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div style="font-weight:700; color:var(--primary-color); margin:0.35rem 0;"><?php echo htmlspecialchars($m['subject']); ?></div>
                        <p style="font-size:0.9rem; color:#334155; margin-bottom:0.5rem;"><?php echo htmlspecialchars($m['body']); ?></p>

                        <form method="POST" id="inlineReplyForm_<?php echo (int)$m['id']; ?>" style="display:none; margin-top:0.6rem; padding-top:0.6rem; border-top:1px dashed #cbd5e1;">
                            <input type="hidden" name="action" value="send">
                            <input type="hidden" name="receiver_id" value="<?php echo $other_id; ?>">
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($clean_subj); ?>">
                            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                <input type="text" name="body" class="form-control" style="flex:1; min-width:200px;" placeholder="<?php echo $is_am ? 'ቀጥተኛ ምላሽዎን እዚህ ይጻፉ...' : 'Write your reply directly here...'; ?>" required>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-send"></i> <?php echo $is_am ? 'ምላሽ ላክ' : 'Send Reply'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#64748b;"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም መልዕክት አልተገኘም።' : 'No messages found.')); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function openInlineReply(msgId) {
    const el = document.getElementById('inlineReplyForm_' + msgId);
    if (!el) return;
    el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    const inp = el.querySelector('input[name="body"]');
    if (el.style.display === 'block' && inp) inp.focus();
}
</script>
<?php include 'includes/footer.php'; ?>