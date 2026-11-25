<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_path = '';
if (strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false) {
    $base_path = '../../';
}

if (!isset($lang)) {
    $lang_code = $_SESSION['lang'] ?? 'en';
    $lang_path = __DIR__ . "/lang_" . $lang_code . ".php";
    $lang = file_exists($lang_path) ? include($lang_path) : include(__DIR__ . "/lang_en.php");
}

$current_lang = $_SESSION['lang'] ?? 'en';
$clean_uri = preg_replace('/([?&])lang=(en|am)/', '', $_SERVER['REQUEST_URI']);
$clean_uri = rtrim($clean_uri, '?&');
$sep = (strpos($clean_uri, '?') !== false) ? '&' : '?';

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['name'] ?? '';
$avatar_rel = isset($conn) && $is_logged_in ? get_profile_image($conn, $_SESSION['user_id']) : 'assets/default_avatar.svg';
$logo_rel = $base_path . 'assets/inu_logo.jpg';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lang['hub_full_name']); ?></title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css?v=13">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Critical Header & Dropdown Layout Guard */
        header.inu-top-header {
            background: linear-gradient(90deg, #125634 0%, #17663f 55%, #145c38 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.55rem 1.25rem;
            min-height: 76px;
            position: sticky;
            top: 0;
            z-index: 2000;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
            font-family: 'Poppins', 'Noto Sans Ethiopic', sans-serif;
            border-bottom: 2px solid #f59e0b;
        }
        .inu-header-inner {
            width: 100%;
            max-width: 1380px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.35rem;
            flex-wrap: nowrap;
        }
        .inu-header-left {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-shrink: 0;
        }
        .inu-burger-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #ffffff;
            font-size: 1.2rem;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .inu-brand-lockup {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            color: #ffffff;
        }
        .inu-seal-img {
            width: 52px;
            height: 52px;
            max-width: 52px;
            max-height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fbbf24;
            flex-shrink: 0;
            background: #ffffff;
        }
        .inu-brand-lines {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }
        .inu-line-en {
            font-size: 0.88rem;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            letter-spacing: 0.02em;
        }
        .inu-line-am {
            font-family: 'Noto Sans Ethiopic', sans-serif;
            font-size: 0.84rem;
            font-weight: 700;
            color: #fef3c7;
            white-space: nowrap;
        }
        .inu-line-motto {
            font-size: 0.72rem;
            font-style: italic;
            color: #dcfce7;
            white-space: nowrap;
        }
        .inu-nav-cluster {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: nowrap;
        }
        .inu-nav-bar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: nowrap;
        }
        .inu-nav-item {
            position: relative;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
            padding: 0.45rem 0.25rem;
            font-family: inherit;
            transition: color 0.22s ease;
        }
        .inu-nav-item::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2.5px;
            background: #fbbf24;
            border-radius: 2px;
            transition: width 0.25s ease;
        }
        .inu-nav-item:hover,
        .inu-dd-group:hover > .inu-nav-item {
            color: #fbbf24;
            text-decoration: underline;
            text-underline-offset: 5px;
            text-decoration-thickness: 2px;
            text-decoration-color: #fbbf24;
        }
        .inu-nav-item:hover::after,
        .inu-dd-group:hover > .inu-nav-item::after {
            width: 100%;
        }
        .inu-nav-item .bi-chevron-down {
            font-size: 0.72rem;
            transition: transform 0.22s ease;
        }
        .inu-dd-group:hover > .inu-nav-item .bi-chevron-down,
        .inu-dd-group.is-open > .inu-nav-item .bi-chevron-down {
            transform: rotate(180deg);
        }
        .inu-dd-group {
            position: relative;
            display: inline-flex;
            align-items: center;
            padding-bottom: 12px;
            margin-bottom: -12px;
        }
        /* Hover bridge so cursor never loses hover between trigger and menu */
        .inu-dd-group::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            height: 14px;
            background: transparent;
        }
        /* Smooth downward slide & reveal on hover */
        .inu-dd-menu {
            display: block !important;
            position: absolute !important;
            top: calc(100% + 2px) !important;
            left: 0 !important;
            min-width: 248px;
            background: #ffffff;
            color: #0f172a;
            border-radius: 10px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.24);
            border-top: 3px solid #f59e0b;
            padding: 0.4rem 0;
            z-index: 5000;
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
            transform: translateY(-14px) !important;
            transition: opacity 0.25s cubic-bezier(0.22, 1, 0.36, 1), transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), visibility 0s linear 0.25s;
        }
        .inu-dd-group:hover > .inu-dd-menu,
        .inu-dd-group:focus-within > .inu-dd-menu,
        .inu-dd-menu.open {
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: auto !important;
            transform: translateY(4px) !important;
            transition: opacity 0.25s cubic-bezier(0.22, 1, 0.36, 1), transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), visibility 0s linear 0s;
        }
        .inu-dd-menu a {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.58rem 1rem;
            color: #0f172a;
            font-size: 0.83rem;
            font-weight: 500;
            text-decoration: none;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
            opacity: 0;
            transform: translateY(-8px);
            transition: background-color 0.18s ease, color 0.18s ease, padding-left 0.18s ease, opacity 0.22s ease, transform 0.22s ease;
        }
        .inu-dd-group:hover > .inu-dd-menu a,
        .inu-dd-group:focus-within > .inu-dd-menu a,
        .inu-dd-menu.open a {
            opacity: 1;
            transform: translateY(0);
        }
        .inu-dd-group:hover > .inu-dd-menu a:nth-child(1), .inu-dd-group:focus-within > .inu-dd-menu a:nth-child(1), .inu-dd-menu.open a:nth-child(1) { transition-delay: 0.03s; }
        .inu-dd-group:hover > .inu-dd-menu a:nth-child(2), .inu-dd-group:focus-within > .inu-dd-menu a:nth-child(2), .inu-dd-menu.open a:nth-child(2) { transition-delay: 0.06s; }
        .inu-dd-group:hover > .inu-dd-menu a:nth-child(3), .inu-dd-group:focus-within > .inu-dd-menu a:nth-child(3), .inu-dd-menu.open a:nth-child(3) { transition-delay: 0.09s; }
        .inu-dd-group:hover > .inu-dd-menu a:nth-child(4), .inu-dd-group:focus-within > .inu-dd-menu a:nth-child(4), .inu-dd-menu.open a:nth-child(4) { transition-delay: 0.12s; }
        .inu-dd-menu a:last-child {
            border-bottom: none;
        }
        .inu-dd-menu a:hover {
            background: #ecfdf5;
            color: #17663f;
            padding-left: 1.25rem;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .inu-header-right {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-shrink: 0;
            padding-left: 0.55rem;
            border-left: 1px solid rgba(255, 255, 255, 0.28);
        }
        .inu-lang-trigger {
            background: rgba(255, 255, 255, 0.14) !important;
            border: 1px solid rgba(251, 191, 36, 0.55) !important;
            border-radius: 7px !important;
            padding: 0.34rem 0.62rem !important;
            gap: 0.35rem !important;
        }
        .inu-lang-trigger::after {
            display: none !important;
        }
        .inu-lang-menu {
            left: auto !important;
            right: 0 !important;
            min-width: 175px;
        }
        .inu-dd-menu a.active-lang {
            background: #ecfdf5;
            color: #17663f;
            font-weight: 700;
        }

        /* Language switcher styled cleanly matching the header nav & pill aesthetic */
        .inu-lang-select {
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            border: 1px solid rgba(251, 191, 36, 0.65);
            border-radius: 7px;
            padding: 0.32rem 0.62rem;
            font-size: 0.8rem;
            font-weight: 600;
            font-family: 'Poppins', 'Noto Sans Ethiopic', sans-serif;
            cursor: pointer;
            outline: none;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.15s ease;
        }
        .inu-lang-select:hover,
        .inu-lang-select:focus {
            background: rgba(251, 191, 36, 0.22);
            border-color: #fbbf24;
            color: #fef3c7;
            transform: translateY(-1px);
        }
        .inu-lang-select option {
            color: #0f172a;
            background: #ffffff;
            font-weight: 600;
        }
        .inu-pill-btn {
            background: #fbbf24;
            color: #0f172a;
            border: 1px solid #fbbf24;
            padding: 0.34rem 0.78rem;
            border-radius: 7px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.2s ease, color 0.2s ease, transform 0.15s ease;
        }
        .inu-pill-btn:hover {
            background: #ffffff;
            color: #17663f;
            border-color: #ffffff;
            text-decoration: underline;
            text-underline-offset: 3px;
            transform: translateY(-1px);
        }
        .inu-user-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        .inu-user-pill:hover {
            color: #fbbf24;
            text-decoration: underline;
        }
        .inu-user-pill img {
            width: 30px;
            height: 30px;
            max-width: 30px;
            max-height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid #fbbf24;
        }
        .inu-logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #dc2626;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.45);
            padding: 0.35rem 0.72rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background 0.2s ease, transform 0.15s ease;
        }
        .inu-logout-btn:hover {
            background: #b91c1c;
            color: #ffffff;
        }
        @media (max-width: 1120px) {
            header.inu-top-header {
                padding: 0.5rem 0.75rem;
                justify-content: space-between;
            }
            .inu-header-inner {
                justify-content: space-between;
                gap: 0.5rem;
            }
            .inu-burger-btn {
                display: inline-flex;
            }
            .inu-nav-bar {
                display: none;
            }
            .inu-header-right {
                border-left: none;
                padding-left: 0;
            }
            .inu-seal-img {
                width: 40px;
                height: 40px;
                max-width: 40px;
                max-height: 40px;
            }
            .inu-line-motto {
                display: none;
            }
            .inu-line-en {
                font-size: 0.76rem;
            }
            .inu-line-am {
                font-size: 0.74rem;
            }
            .inu-user-first-name {
                display: none;
            }
            .inu-lang-select {
                padding: 0.26rem 0.45rem;
                font-size: 0.76rem;
            }
            .inu-logout-btn {
                padding: 0.32rem 0.55rem;
                font-size: 0.78rem;
            }
        }
        @media (max-width: 480px) {
            .inu-brand-lines {
                max-width: 115px;
                overflow: hidden;
            }
            .inu-line-en, .inu-line-am {
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }
    </style>
    <script>
        window.i18n = <?php echo json_encode([
            'fill_all_fields' => $lang['fill_all_fields'],
            'invalid_email' => $lang['invalid_email'],
            'min_6_chars_detailed' => $lang['min_6_chars_detailed'],
            'letters_only' => $lang['letters_only'],
            'error_occurred' => $lang['error_occurred']
        ]); ?>;

        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll("input, textarea, select").forEach(function (el) {
                el.addEventListener("invalid", function () {
                    el.setCustomValidity("");
                    if (el.validity.valueMissing) {
                        el.setCustomValidity(window.i18n.fill_all_fields);
                    } else if (el.type === "email" && el.validity.typeMismatch) {
                        el.setCustomValidity(window.i18n.invalid_email);
                    } else if (el.validity.tooShort) {
                        el.setCustomValidity(window.i18n.min_6_chars_detailed.replace("{current}", el.value.length));
                    }
                });
                el.addEventListener("input", function () {
                    el.setCustomValidity("");
                });
            });

            document.querySelectorAll("input[name*='phone'], input[type='tel'], input[placeholder*='251'], input[placeholder*='Phone'], input[placeholder*='ስልክ']").forEach(function (ph) {
                ph.setAttribute("maxlength", "10");
                ph.addEventListener("input", function () {
                    let digitsOnly = this.value.replace(/\D+/g, "");
                    if (digitsOnly.length > 10) digitsOnly = digitsOnly.slice(0, 10);
                    this.value = digitsOnly;
                });
            });
        });

        function toggleMobileNavDrawer() {
            const drawer = document.getElementById("mobileNavDrawer");
            if (drawer) drawer.classList.toggle("open");
        }
        function toggleHeaderDd(id, ev) {
            if (ev) {
                ev.preventDefault();
                ev.stopPropagation();
            }
            document.querySelectorAll('.inu-dd-menu').forEach(function (m) {
                if (m.id !== id) m.classList.remove('open');
            });
            const target = document.getElementById(id);
            if (target) target.classList.toggle('open');
        }
        document.addEventListener('click', function () {
            document.querySelectorAll('.inu-dd-menu').forEach(function (m) {
                m.classList.remove('open');
            });
        });
    </script>
</head>
<body>
<header class="inu-top-header">
  <div class="inu-header-inner">
    <div class="inu-header-left">
        <?php if ($is_logged_in): ?>
            <button type="button" class="inu-burger-btn" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                <i class="bi bi-list"></i>
            </button>
        <?php else: ?>
            <button type="button" class="inu-burger-btn" id="mobileNavToggle" onclick="toggleMobileNavDrawer()" aria-label="Toggle Navigation">
                <i class="bi bi-list"></i>
            </button>
        <?php endif; ?>

        <a href="<?php echo $base_path; ?>index.php" class="inu-brand-lockup">
            <img src="<?php echo htmlspecialchars($logo_rel); ?>" alt="Injibara University Seal" class="inu-seal-img">
            <span class="inu-brand-lines">
                <span class="inu-line-en"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'እንጅባራ ዩኒቨርሲቲ' : 'INJIBARA UNIVERSITY'; ?></span>
                <span class="inu-line-am">እንጅባራ ዩኒቨርሲቲ</span>
                <span class="inu-line-motto"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የፈጠራ አቅምዎን ያጎልብቱ' : 'Explore your creative potentials'; ?></span>
            </span>
        </a>
    </div>

    <div class="inu-nav-cluster">
    <nav class="inu-nav-bar" id="desktopNavLinks">
        <a href="<?php echo $base_path; ?>index.php" class="inu-nav-item"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'መነሻ' : 'Home'; ?></a>

        <div class="inu-dd-group">
            <button type="button" class="inu-nav-item" onclick="toggleHeaderDd('ddAbout', event)">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ስለ እኛ' : 'About us'; ?> <i class="bi bi-chevron-down"></i>
            </button>
            <div class="inu-dd-menu" id="ddAbout">
                <a href="<?php echo $base_path; ?>index.php#about-inu"><i class="bi bi-bank"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የዩኒቨርሲቲው መለያ እና ተልዕኮ' : 'University Identity &amp; Mission'; ?></a>
                <a href="<?php echo $base_path; ?>index.php#inu-portal-info"><i class="bi bi-images"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የካምፓስ እና የማዕከሉ ምስሎች' : 'Campus &amp; Hub Gallery'; ?></a>
                <a href="<?php echo $base_path; ?>index.php#contact"><i class="bi bi-geo-alt"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አድራሻ እና መገኛ' : 'Contact &amp; Location'; ?></a>
            </div>
        </div>

        <div class="inu-dd-group">
            <button type="button" class="inu-nav-item" onclick="toggleHeaderDd('ddLatest', event)">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ወቅታዊ መረጃ' : 'Latest'; ?> <i class="bi bi-chevron-down"></i>
            </button>
            <div class="inu-dd-menu" id="ddLatest">
                <a href="<?php echo $base_path; ?>index.php#announcements"><i class="bi bi-megaphone"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የማዕከሉ ማስታወቂያዎች' : 'Hub Announcements'; ?></a>
                <a href="<?php echo $base_path; ?>index.php#programs"><i class="bi bi-calendar-check"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ክፍት የአጭር ጊዜ ስልጠናዎች' : 'Open Short-Term Cohorts'; ?></a>
                <a href="<?php echo $base_path; ?>verify_certificate.php"><i class="bi bi-shield-check"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሰርተፍኬት ማረጋገጫ' : 'Certificate Verification'; ?></a>
            </div>
        </div>

        <div class="inu-dd-group">
            <button type="button" class="inu-nav-item" onclick="toggleHeaderDd('ddAdmin', event)">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አስተዳደር' : 'Administration'; ?> <i class="bi bi-chevron-down"></i>
            </button>
            <div class="inu-dd-menu" id="ddAdmin">
                <a href="<?php echo $base_path; ?>index.php#leadership"><i class="bi bi-person-badge"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የዩኒቨርሲቲው ፕሬዚዳንት እና ምክትሎች' : 'University President &amp; VPs'; ?></a>
                <a href="<?php echo $base_path; ?>login.php"><i class="bi bi-box-arrow-in-right"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሚና ፖርታል መግቢያ' : 'Role Portal Login'; ?></a>
            </div>
        </div>

        <div class="inu-dd-group">
            <button type="button" class="inu-nav-item" onclick="toggleHeaderDd('ddAcademics', event)">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አካዳሚክስ' : 'Academics'; ?> <i class="bi bi-chevron-down"></i>
            </button>
            <div class="inu-dd-menu" id="ddAcademics">
                <a href="<?php echo $base_path; ?>index.php#programs"><i class="bi bi-journal-bookmark"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአጭር ጊዜ ስልጠና ፕሮግራሞች' : 'Short-Term Training Programs'; ?></a>
                <a href="<?php echo $base_path; ?>register.php"><i class="bi bi-person-plus"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሰልጣኝ ምዝገባ' : 'Trainee Registration'; ?></a>
            </div>
        </div>

        <div class="inu-dd-group">
            <button type="button" class="inu-nav-item" onclick="toggleHeaderDd('ddResearch', event)">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ምርምር' : 'Research'; ?> <i class="bi bi-chevron-down"></i>
            </button>
            <div class="inu-dd-menu" id="ddResearch">
                <a href="<?php echo $base_path; ?>index.php#inu-portal-info"><i class="bi bi-laptop"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ተግባራዊ ላቦራቶሪዎች እና ምርምር' : 'Practical Labs &amp; Applied Research'; ?></a>
                <a href="<?php echo $base_path; ?>sponsor_request.php"><i class="bi bi-building"></i> <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተቋማት የስልጠና ጥያቄ' : 'Institutional Training Requests'; ?></a>
            </div>
        </div>

    <div class="inu-header-right">
        <div class="inu-dd-group inu-lang-dd">
            <button type="button" class="inu-nav-item inu-lang-trigger" onclick="toggleHeaderDd('ddLang', event)" aria-label="Language Switcher">
                <i class="bi bi-translate"></i>
                <span><?php echo $current_lang === 'am' ? 'አማርኛ' : 'EN'; ?></span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="inu-dd-menu inu-lang-menu" id="ddLang">
                <a href="<?php echo htmlspecialchars($clean_uri . $sep . 'lang=en'); ?>" class="<?php echo $current_lang === 'en' ? 'active-lang' : ''; ?>">
                    <i class="bi bi-globe2"></i> English (EN)
                </a>
                <a href="<?php echo htmlspecialchars($clean_uri . $sep . 'lang=am'); ?>" class="<?php echo $current_lang === 'am' ? 'active-lang' : ''; ?>">
                    <i class="bi bi-translate"></i> አማርኛ (AM)
                </a>
            </div>
        </div>

        <?php if ($is_logged_in): ?>
            <a href="<?php echo $base_path; ?>profile.php" class="inu-user-pill">
                <img src="<?php echo $base_path . htmlspecialchars($avatar_rel); ?>" alt="Avatar" onerror="this.src='<?php echo $base_path; ?>assets/image.png'">
                <span class="inu-user-first-name"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
            </a>
            <a href="<?php echo $base_path; ?>logout.php" class="inu-logout-btn" title="<?php echo htmlspecialchars($lang['logout']); ?>">
                <i class="bi bi-box-arrow-right"></i>
                <span class="inu-logout-label"><?php echo htmlspecialchars($lang['logout']); ?></span>
            </a>
        <?php else: ?>
            <a href="<?php echo $base_path; ?>login.php" class="inu-pill-btn"><?php echo htmlspecialchars($lang['login']); ?></a>
        <?php endif; ?>
    </div>
    </div>
  </div>
</header>

<div class="mobile-nav-drawer" id="mobileNavDrawer">
    <?php if ($is_logged_in): ?>
        <a href="<?php echo $base_path; ?>dashboard.php" onclick="toggleMobileNavDrawer()"><i class="bi bi-speedometer2"></i> <?php echo htmlspecialchars($lang['my_dashboard']); ?></a>
        <a href="<?php echo $base_path; ?>messages.php" onclick="toggleMobileNavDrawer()"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($lang['messages']); ?></a>
        <a href="<?php echo $base_path; ?>profile.php" onclick="toggleMobileNavDrawer()"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($lang['profile']); ?></a>
        <a href="<?php echo $base_path; ?>settings.php" onclick="toggleMobileNavDrawer()"><i class="bi bi-gear"></i> Settings</a>
        <a href="<?php echo $base_path; ?>logout.php" style="color:#fecaca; font-weight:700;"><i class="bi bi-box-arrow-right"></i> <?php echo htmlspecialchars($lang['logout']); ?></a>
    <?php else: ?>
    <a href="<?php echo $base_path; ?>index.php" onclick="toggleMobileNavDrawer()"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'መነሻ' : 'Home'; ?></a>
    <a href="<?php echo $base_path; ?>index.php#about-inu" onclick="toggleMobileNavDrawer()"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ስለ እኛ' : 'About us'; ?></a>
    <a href="<?php echo $base_path; ?>index.php#announcements" onclick="toggleMobileNavDrawer()">Latest &amp; Announcements</a>
    <a href="<?php echo $base_path; ?>index.php#leadership" onclick="toggleMobileNavDrawer()"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አስተዳደር እና አመራር' : 'Administration &amp; Leadership'; ?></a>
    <a href="<?php echo $base_path; ?>index.php#programs" onclick="toggleMobileNavDrawer()"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አካዳሚክስ እና ፕሮግራሞች' : 'Academics &amp; Programs'; ?></a>
    <a href="<?php echo $base_path; ?>sponsor_request.php" onclick="toggleMobileNavDrawer()">Partnership &amp; Group Request</a>
    <a href="https://www.inu.edu.et" target="_blank" rel="noopener noreferrer">INU TV &amp; Official Portal (inu.edu.et)</a>
    <a href="<?php echo $base_path; ?>register.php" onclick="toggleMobileNavDrawer()"><?php echo htmlspecialchars($lang['register']); ?></a>
    <?php endif; ?>
</div>