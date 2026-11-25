<?php
// includes/sidebar.php
$role = $_SESSION['role'] ?? 'trainee';
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = (strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false) ? '../../' : '';

if (!isset($lang)) {
    $lang_code = $_SESSION['lang'] ?? 'en';
    $lang_path = __DIR__ . "/lang_" . $lang_code . ".php";
    $lang = file_exists($lang_path) ? include($lang_path) : include(__DIR__ . "/lang_en.php");
}

$is_am_sb = (($_SESSION['lang'] ?? 'en') === 'am');
$unread_msg = isset($conn, $_SESSION['user_id']) ? get_unread_messages_count($conn, $_SESSION['user_id']) : 0;
$pending_enr = isset($conn) ? get_pending_enrollments_count($conn) : 0;
$pending_pay = isset($conn) ? get_pending_payments_count($conn) : 0;
$programs_label = $lang['programs'] ?? ($is_am_sb ? 'ፕሮግራሞች' : 'Programs');

$admin_program_dropdown = [
    [
        'label' => $is_am_sb ? 'ሁሉንም ፕሮግራሞች አስተዳድር' : 'All Programs',
        'url'   => $base_path . 'dashboard/admin/programs.php'
    ]
];

if ($role === 'admin' && isset($conn) && $conn instanceof mysqli) {
    try {
        $res_sb_progs = $conn->query("SELECT id, code, title_en, title_am FROM training_programs ORDER BY id DESC");
        if ($res_sb_progs) {
            while ($sb_p = $res_sb_progs->fetch_assoc()) {
                $sb_title = ($is_am_sb && !empty($sb_p['title_am'])) ? $sb_p['title_am'] : $sb_p['title_en'];
                $sb_code  = trim((string)($sb_p['code'] ?? ''));
                $admin_program_dropdown[] = [
                    'label' => ($sb_code !== '' ? $sb_code . ' — ' : '') . $sb_title,
                    'url'   => $base_path . 'dashboard/admin/programs.php?program_id=' . (int)$sb_p['id'] . '#program-edit-panel'
                ];
            }
        }
    } catch (Throwable $e) {
        // Keep base link if table is not yet initialized
    }
}

$admin_user_roles_dropdown = [
    ['label' => $is_am_sb ? 'ሰልጣኝ (Trainee)' : 'Trainee',                           'url' => $base_path . 'dashboard/admin/users.php?role=trainee&panel=1'],
    ['label' => $is_am_sb ? 'አሰልጣኝ (Trainer)' : 'Trainer',                           'url' => $base_path . 'dashboard/admin/users.php?role=trainer&panel=1'],
    ['label' => $is_am_sb ? 'አስተባባሪ (Coordinator)' : 'Coordinator',               'url' => $base_path . 'dashboard/admin/users.php?role=coordinator&panel=1'],
    ['label' => $is_am_sb ? 'የፋይናንስ ባለሙያ (Finance Office)' : 'Finance Office',   'url' => $base_path . 'dashboard/admin/users.php?role=finance_officer&panel=1'],
    ['label' => $is_am_sb ? 'የዲፓርትመንት ኃላፊ (Department Head)' : 'Department Head','url' => $base_path . 'dashboard/admin/users.php?role=department_head&panel=1'],
    ['label' => $is_am_sb ? 'የክትትል ባለሙያ (Monitoring Officer)' : 'Monitoring Officer','url' => $base_path . 'dashboard/admin/users.php?role=monitoring_officer&panel=1'],
    ['label' => $is_am_sb ? 'የድጋፍ ሰራተኛ (Support Staff)' : 'Support Staff',       'url' => $base_path . 'dashboard/admin/users.php?role=support_staff&panel=1'],
    ['label' => $is_am_sb ? 'የቀድሞ ተማሪዎች አስተባባሪ (Alumni)' : 'Alumni Coordinator','url' => $base_path . 'dashboard/admin/users.php?role=alumni_coordinator&panel=1'],
    ['label' => $is_am_sb ? 'የኮሌጅ ዲን (College Dean)' : 'College Dean',            'url' => $base_path . 'dashboard/admin/users.php?role=college_dean&panel=1'],
    ['label' => $is_am_sb ? 'ሬጅስትራር (Registrar)' : 'Registrar',                   'url' => $base_path . 'dashboard/admin/users.php?role=registrar&panel=1'],
    ['label' => $is_am_sb ? 'የላቦራቶሪ ቴክኒሻን (Lab Technician)' : 'Lab Technician','url' => $base_path . 'dashboard/admin/users.php?role=lab_technician&panel=1'],
    ['label' => $is_am_sb ? 'ሥርዓተ-ትምህርት አዘጋጅ (Curriculum)' : 'Curriculum Developer','url' => $base_path . 'dashboard/admin/users.php?role=curriculum_developer&panel=1'],
    ['label' => $is_am_sb ? 'የስፖንሰር አስተባባሪ (Sponsor Liaison)' : 'Sponsor Liaison','url' => $base_path . 'dashboard/admin/users.php?role=sponsor_liaison&panel=1'],
    ['label' => $is_am_sb ? 'የሰርተፍኬት ባለሙያ (Certificate Officer)' : 'Certificate Officer','url' => $base_path . 'dashboard/admin/users.php?role=certificate_officer&panel=1'],
    ['label' => $is_am_sb ? 'የክስተት አስተባባሪ (Events Coordinator)' : 'Events Coordinator','url' => $base_path . 'dashboard/admin/users.php?role=events_coordinator&panel=1'],
    ['label' => $is_am_sb ? 'የጥራት ገምጋሚ (QA Evaluator)' : 'QA Evaluator',         'url' => $base_path . 'dashboard/admin/users.php?role=qa_evaluator&panel=1'],
    ['label' => $is_am_sb ? 'አስተዳዳሪ (Admin)' : 'Admin',                             'url' => $base_path . 'dashboard/admin/users.php?role=admin&panel=1'],
];

$menu_items = [];
switch ($role) {
    case 'admin':
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/admin/index.php', 'badge' => 0],
            [
                'icon' => 'bi-people-fill',
                'label' => $lang['manage_users'],
                'url' => $base_path . 'dashboard/admin/users.php',
                'badge' => 0,
                'dropdown' => $admin_user_roles_dropdown
            ],
            [
                'icon' => 'bi-journal-bookmark-fill',
                'label' => $lang['manage_programs'],
                'url' => $base_path . 'dashboard/admin/programs.php',
                'badge' => 0,
                'dropdown' => $admin_program_dropdown
            ],
            ['icon' => 'bi-megaphone-fill', 'label' => $lang['post_announcement'], 'url' => $base_path . 'dashboard/admin/announcements.php', 'badge' => 0],
            ['icon' => 'bi-bar-chart-line-fill', 'label' => $lang['reports_export'], 'url' => $base_path . 'dashboard/admin/reports.php', 'badge' => 0],
            ['icon' => 'bi-shield-lock-fill', 'label' => $is_am_sb ? 'የሚና ክትትል እና ማስተካከያ' : 'Role Oversight & Logs', 'url' => $base_path . 'dashboard/admin/index.php?section=oversight#role-oversight', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'certificate_officer':
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/certificate_officer/index.php', 'badge' => 0],
            ['icon' => 'bi-shield-check', 'label' => $is_am_sb ? 'ሰርተፊኬት ማረጋገጫ' : 'Verify Certificate', 'url' => $base_path . 'verify_certificate.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'coordinator':
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/coordinator/index.php', 'badge' => 0],
            ['icon' => 'bi-clipboard-check-fill', 'label' => $lang['manage_enrollments'], 'url' => $base_path . 'dashboard/coordinator/enrollments.php', 'badge' => $pending_enr],
            ['icon' => 'bi-calendar3', 'label' => $lang['manage_sessions'], 'url' => $base_path . 'dashboard/coordinator/sessions.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'trainer':
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/trainer/index.php', 'badge' => 0],
            ['icon' => 'bi-check2-square', 'label' => $lang['attendance_grading'], 'url' => $base_path . 'dashboard/trainer/attendance.php', 'badge' => 0],
            ['icon' => 'bi-folder2-open', 'label' => $is_am_sb ? 'የኮርስ ግብዓቶች' : 'Course Materials', 'url' => $base_path . 'dashboard/trainer/materials.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'trainee':
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/trainee/index.php', 'badge' => 0],
            ['icon' => 'bi-book-half', 'label' => $lang['available_programs'], 'url' => $base_path . 'dashboard/trainee/programs.php', 'badge' => 0],
            ['icon' => 'bi-cash-coin', 'label' => $is_am_sb ? 'ክፍያ እና ደረሰኝ' : 'Payment & Receipts', 'url' => $base_path . 'dashboard/trainee/payment.php', 'badge' => 0],
            ['icon' => 'bi-folder2-open', 'label' => $is_am_sb ? 'የኮርስ ግብዓቶች' : 'Course Materials', 'url' => $base_path . 'dashboard/trainee/materials.php', 'badge' => 0],
            ['icon' => 'bi-star-half', 'label' => $is_am_sb ? 'የኮርስ ግምገማ' : 'Course Evaluation', 'url' => $base_path . 'dashboard/trainee/evaluations.php', 'badge' => 0],
            ['icon' => 'bi-award-fill', 'label' => $lang['my_certificates'], 'url' => $base_path . 'dashboard/trainee/certificates.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'sponsor_liaison':
        $panel_heading = $is_am_sb ? 'የስፖንሰር አስተባባሪ (Sponsor Liaison)' : 'Sponsor Liaison Panel';
        $menu_items = [
            ['icon' => 'bi-building-fill', 'label' => $is_am_sb ? 'ስፖንሰር ተግባር (Sponsor Liaison)' : 'Sponsor Liaison', 'url' => $base_path . 'dashboard/finance/sponsor_task.php', 'badge' => 0],
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/finance/index.php', 'badge' => $pending_pay],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'finance_officer':
    case 'finance':
        $panel_role_key = isset($lang['finance_officer_panel']) ? 'finance_officer_panel' : (isset($lang['finance_panel']) ? 'finance_panel' : null);
        $panel_heading  = $panel_role_key ? $lang[$panel_role_key] : ($is_am_sb ? 'የፋይናንስ ቢሮ ፓነል' : 'Finance Office Panel');
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/finance/index.php', 'badge' => $pending_pay],
            ['icon' => 'bi-building-fill', 'label' => $is_am_sb ? 'ስፖንሰር ተግባር (Sponsor Liaison)' : 'Sponsor Liaison', 'url' => $base_path . 'dashboard/finance/sponsor_task.php', 'badge' => 0],
            ['icon' => 'bi-wallet2', 'label' => $is_am_sb ? 'በጀት እና ወጪ' : 'Budgets & Expenses', 'url' => $base_path . 'dashboard/finance/budgets.php', 'badge' => 0],
            ['icon' => 'bi-receipt-cutoff', 'label' => $is_am_sb ? 'ኢንቮይሶች' : 'Invoices & Billing', 'url' => $base_path . 'dashboard/finance/invoices.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'qa_evaluator':
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/qa/index.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'registrar':
        $menu_items = [
            ['icon' => 'bi-card-checklist', 'label' => $is_am_sb ? 'ምዝገባ እና ምድብ' : 'Enrollment & Placement', 'url' => $base_path . 'dashboard/registrar/index.php?section=enrollment#registrar-enrollment-panel', 'badge' => $pending_enr],
            ['icon' => 'bi-journal-check', 'label' => $is_am_sb ? 'ውጤት እና መርሐ-ግብር' : 'Grades & Schedule', 'url' => $base_path . 'dashboard/registrar/index.php?section=academics#registrar-academics-panel', 'badge' => 0],
            ['icon' => 'bi-file-earmark-award', 'label' => $is_am_sb ? 'ሰነድ እና ምረቃ' : 'Documents & Graduation', 'url' => $base_path . 'dashboard/registrar/index.php?section=documents#registrar-documents-panel', 'badge' => 0],
            ['icon' => 'bi-database-gear', 'label' => $is_am_sb ? 'መረጃ ማረጋገጫ እና ጥገና' : 'Data Audit & Maintenance', 'url' => $base_path . 'dashboard/registrar/index.php?section=maintenance#registrar-maintenance-panel', 'badge' => 0],
            ['icon' => 'bi-shield-check', 'label' => $is_am_sb ? 'ሰርተፍኬት ማረጋገጫ' : 'Verify Certificate', 'url' => $base_path . 'verify_certificate.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'college_dean':
        $menu_items = [
            ['icon' => 'bi-mortarboard-fill', 'label' => $is_am_sb ? 'የኮሌጅ ዲን ዳሽቦርድ' : 'College Dean Dashboard', 'url' => $base_path . 'dashboard/college_dean/index.php', 'badge' => 0],
            ['icon' => 'bi-journal-bookmark-fill', 'label' => $programs_label, 'url' => $base_path . 'dashboard/college_dean/index.php?view=programs', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'department_head':
        $menu_items = [
            ['icon' => 'bi-diagram-3-fill', 'label' => $is_am_sb ? 'የክፍል ኃላፊ ዳሽቦርድ' : 'Department Head Dashboard', 'url' => $base_path . 'dashboard/department_head/index.php', 'badge' => 0],
            ['icon' => 'bi-journal-bookmark-fill', 'label' => $programs_label, 'url' => $base_path . 'dashboard/department_head/index.php?view=programs', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'monitoring_officer':
        $menu_items = [
            ['icon' => 'bi-clipboard-data-fill', 'label' => $is_am_sb ? 'የክትትል ባለሙያ ዳሽቦርድ' : 'Monitoring Officer Dashboard', 'url' => $base_path . 'dashboard/monitoring_officer/index.php', 'badge' => 0],
            ['icon' => 'bi-shield-check', 'label' => $is_am_sb ? 'ሰርተፍኬት ማረጋገጫ' : 'Verify Certificate', 'url' => $base_path . 'verify_certificate.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'support_staff':
        $menu_items = [
            ['icon' => 'bi-headset', 'label' => $is_am_sb ? 'የድጋፍ ሰራተኛ ዳሽቦርድ' : 'Support Staff Dashboard', 'url' => $base_path . 'dashboard/support_staff/index.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    case 'alumni_coordinator':
        $menu_items = [
            ['icon' => 'bi-mortarboard', 'label' => $is_am_sb ? 'የአሉምናይ አስተባባሪ ዳሽቦርድ' : 'Alumni Coordinator Dashboard', 'url' => $base_path . 'dashboard/alumni_coordinator/index.php', 'badge' => 0],
            ['icon' => 'bi-shield-check', 'label' => $is_am_sb ? 'ሰርተፍኬት ማረጋገጫ' : 'Verify Certificate', 'url' => $base_path . 'verify_certificate.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
    default:
        $menu_items = [
            ['icon' => 'bi-speedometer2', 'label' => $lang['my_dashboard'], 'url' => $base_path . 'dashboard/role_workspace.php', 'badge' => 0],
            ['icon' => 'bi-envelope-fill', 'label' => $lang['messages'], 'url' => $base_path . 'messages.php', 'badge' => $unread_msg],
            ['icon' => 'bi-person-circle', 'label' => $lang['profile'], 'url' => $base_path . 'profile.php', 'badge' => 0],
            ['icon' => 'bi-gear-fill', 'label' => $is_am_sb ? 'ቅንብሮች' : 'Settings', 'url' => $base_path . 'settings.php', 'badge' => 0],
        ];
        break;
}

if (!isset($panel_heading)) {
    $panel_heading = $lang[$role . '_panel'] ?? ucwords(str_replace('_', ' ', $role)) . ' Panel';
}
?>
<style>
    .hub-sidebar {
        width: 265px;
        flex-shrink: 0;
        background: linear-gradient(180deg, #17663f 0%, #0f172a 100%);
        color: #fff;
        position: relative;
        margin-top: 0;
        min-height: calc(100vh - 76px);
        transition: transform 0.28s ease;
        z-index: 1400;
    }
    .hub-sidebar-header {
        padding: 1.2rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        text-align: center;
    }
    .hub-sidebar-header h3 {
        font-size: 0.98rem;
        font-weight: 700;
        color: #fbbf24;
    }
    .hub-sidebar-menu {
        padding: 0.7rem 0;
    }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(1),
    .hub-sidebar-menu > .hub-dd-container:nth-child(1) > .hub-sidebar-item { --item-accent: #fbbf24; --item-bg: rgba(251, 191, 36, 0.16); --item-fg: #fef3c7; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(2),
    .hub-sidebar-menu > .hub-dd-container:nth-child(2) > .hub-sidebar-item { --item-accent: #38bdf8; --item-bg: rgba(56, 189, 248, 0.16); --item-fg: #e0f2fe; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(3),
    .hub-sidebar-menu > .hub-dd-container:nth-child(3) > .hub-sidebar-item { --item-accent: #4ade80; --item-bg: rgba(74, 222, 128, 0.16); --item-fg: #dcfce7; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(4),
    .hub-sidebar-menu > .hub-dd-container:nth-child(4) > .hub-sidebar-item { --item-accent: #f472b6; --item-bg: rgba(244, 114, 182, 0.16); --item-fg: #fce7f3; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(5),
    .hub-sidebar-menu > .hub-dd-container:nth-child(5) > .hub-sidebar-item { --item-accent: #c084fc; --item-bg: rgba(192, 132, 252, 0.16); --item-fg: #f3e8ff; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(6),
    .hub-sidebar-menu > .hub-dd-container:nth-child(6) > .hub-sidebar-item { --item-accent: #fb923c; --item-bg: rgba(251, 146, 60, 0.16); --item-fg: #ffedd5; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(7),
    .hub-sidebar-menu > .hub-dd-container:nth-child(7) > .hub-sidebar-item { --item-accent: #2dd4bf; --item-bg: rgba(45, 212, 191, 0.16); --item-fg: #ccfbf1; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(8),
    .hub-sidebar-menu > .hub-dd-container:nth-child(8) > .hub-sidebar-item { --item-accent: #facc15; --item-bg: rgba(250, 204, 21, 0.16); --item-fg: #fef9c3; }
    .hub-sidebar-menu > .hub-sidebar-item:nth-child(9),
    .hub-sidebar-menu > .hub-dd-container:nth-child(9) > .hub-sidebar-item { --item-accent: #60a5fa; --item-bg: rgba(96, 165, 250, 0.16); --item-fg: #dbeafe; }

    .hub-sidebar-item {
        --item-accent: #fbbf24;
        --item-bg: rgba(251, 191, 36, 0.16);
        --item-fg: #fef3c7;
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.78rem 1.3rem;
        color: rgba(255, 255, 255, 0.86);
        font-size: 0.89rem;
        font-weight: 500;
        border-left: 3px solid transparent;
        transition: transform 0.2s ease, background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    .hub-sidebar-item:hover,
    .hub-sidebar-item:focus-visible,
    .hub-sidebar-item:active,
    .hub-sidebar-item.touched,
    .hub-sidebar-item.active {
        background: var(--item-bg);
        color: var(--item-fg);
        border-left-color: var(--item-accent);
        transform: translateX(4px);
    }
    .hub-sidebar-item i {
        transition: transform 0.2s ease, color 0.2s ease;
    }
    .hub-sidebar-item:hover i,
    .hub-sidebar-item:active i,
    .hub-sidebar-item.touched i,
    .hub-sidebar-item.active i {
        color: var(--item-accent);
        transform: scale(1.08);
    }
    .hub-sub-menu {
        display: none;
        background: rgba(15, 23, 42, 0.45);
        padding: 0.25rem 0;
        max-height: 360px;
        overflow-y: auto;
    }
    .hub-sub-menu.open {
        display: block;
    }
    .hub-sub-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.54rem 1.2rem 0.54rem 2.45rem;
        color: #e2e8f0;
        font-size: 0.83rem;
        transition: background 0.15s;
    }
    .hub-sub-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fbbf24;
    }
    .hub-badge {
        margin-left: auto;
        background: #ef4444;
        color: #fff;
        font-size: 0.74rem;
        font-weight: 700;
        padding: 0.15rem 0.5rem;
        border-radius: 999px;
    }
    @media (max-width: 900px) {
        .hub-sidebar {
            position: fixed;
            top: 76px;
            left: 0;
            height: calc(100vh - 76px);
            overflow-y: auto;
            transform: translateX(-100%);
        }
        .hub-sidebar.mobile-visible {
            transform: translateX(0);
        }
    }
</style>

<div class="hub-layout-row">
<aside class="hub-sidebar" id="hubSidebar">
    <div class="hub-sidebar-header">
        <h3><?php echo htmlspecialchars($panel_heading, ENT_QUOTES, 'UTF-8'); ?></h3>
    </div>
    <nav class="hub-sidebar-menu">
        <?php foreach ($menu_items as $m):
            $m_url_path = parse_url($m['url'], PHP_URL_PATH) ?? $m['url'];
            $m_query_str = parse_url($m['url'], PHP_URL_QUERY) ?? '';
            $curr_query_str = $_SERVER['QUERY_STRING'] ?? '';
            $is_same_file = (basename($m_url_path) === $current_page);
            $is_active = '';
            if ($is_same_file && strpos($m['url'], '#') === false && $m_query_str === '') {
                $is_active = 'active';
            } elseif ($is_same_file && strpos($m_query_str, 'section=oversight') !== false && strpos($curr_query_str, 'section=oversight') !== false) {
                $is_active = 'active';
            }
            $has_dd = !empty($m['dropdown']);
        ?>
            <?php if ($has_dd):
                $dd_id = 'dd_' . preg_replace('/[^a-zA-Z0-9_]/', '_', basename($m_url_path));
                $is_dd_open = (basename($m_url_path) === $current_page) ? 'open' : '';
            ?>
            <div class="hub-dd-container" data-dd-id="<?php echo htmlspecialchars($dd_id, ENT_QUOTES, 'UTF-8'); ?>" data-stay-open="<?php echo (basename($m_url_path) === $current_page) ? '1' : '0'; ?>">
                <a href="<?php echo htmlspecialchars($m['url'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="hub-sidebar-item <?php echo $is_active; ?>"
                   onclick="toggleSubMenu(event, '<?php echo htmlspecialchars($dd_id, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($m['url'], ENT_QUOTES, 'UTF-8'); ?>')">
                    <i class="bi <?php echo htmlspecialchars($m['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span><?php echo htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <i class="bi bi-chevron-down" style="margin-left:auto; font-size:0.8rem;"></i>
                </a>
                <div class="hub-sub-menu <?php echo $is_dd_open; ?>" id="<?php echo htmlspecialchars($dd_id, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach ($m['dropdown'] as $sub): ?>
                        <a href="<?php echo htmlspecialchars($sub['url'], ENT_QUOTES, 'UTF-8'); ?>" class="hub-sub-item">
                            <i class="bi bi-dot"></i>
                            <span><?php echo htmlspecialchars($sub['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($m['url'], ENT_QUOTES, 'UTF-8'); ?>" class="hub-sidebar-item <?php echo $is_active; ?>">
                    <i class="bi <?php echo htmlspecialchars($m['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span><?php echo htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($m['badge']) && $m['badge'] > 0): ?>
                        <span class="hub-badge"><?php echo (int)$m['badge']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>

<script>
function toggleSidebar() {
    const sb = document.getElementById('hubSidebar');
    if (sb) sb.classList.toggle('mobile-visible');
}
function toggleSubMenu(e, id, targetUrl) {
    const sub = document.getElementById(id);
    if (sub) {
        if (!sub.classList.contains('open')) {
            e.preventDefault();
            sub.classList.add('open');
        }
    }
}
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.hub-sidebar-item').forEach(function (item) {
        item.addEventListener('touchstart', function () {
            document.querySelectorAll('.hub-sidebar-item.touched').forEach(function (el) {
                if (el !== item) el.classList.remove('touched');
            });
            item.classList.add('touched');
        }, { passive: true });
        item.addEventListener('click', function () {
            item.classList.add('touched');
        });
    });

    document.querySelectorAll('.hub-dd-container').forEach(function (container) {
        const ddId = container.getAttribute('data-dd-id');
        const stayOpen = container.getAttribute('data-stay-open') === '1';
        const sub = document.getElementById(ddId);
        if (!sub) return;

        container.addEventListener('mouseenter', function () {
            sub.classList.add('open');
        });

        container.addEventListener('mouseleave', function () {
            if (!stayOpen) {
                sub.classList.remove('open');
            }
        });
    });
});
</script>