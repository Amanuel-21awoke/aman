<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rel_root = '../../';
require_once $rel_root . 'includes/functions.php';
require_once $rel_root . 'backend/db_connect.php';
require_role(['registrar'], $rel_root);

$base_path = $rel_root;
$GLOBALS['base_path'] = $rel_root;

$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$uid   = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$msg   = $_SESSION['reg_msg'] ?? '';
$err   = $_SESSION['reg_err'] ?? '';
unset($_SESSION['reg_msg'], $_SESSION['reg_err']);

$valid_sections = ['enrollment', 'academics', 'documents', 'maintenance'];
$req_section    = strtolower(trim((string)($_GET['section'] ?? ($_POST['section'] ?? 'enrollment'))));
$active_section = in_array($req_section, $valid_sections, true) ? $req_section : 'enrollment';
$self_file      = 'index.php';

$allowed_status = ['Pending', 'Approved', 'Rejected', 'Completed'];
$status_filter  = trim((string)($_GET['status_filter'] ?? 'ALL'));
if ($status_filter !== 'ALL' && !in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'ALL';
}
$search_q     = trim((string)($_GET['q'] ?? ''));
$print_doc_id = (int)($_GET['print_doc'] ?? 0);

$enr_cols       = [];
$user_cols      = [];
$prog_cols      = [];
$sch_cols       = [];
$schema_repairs = [];

$reg_ensure_schema = static function () use ($conn, &$enr_cols, &$user_cols, &$prog_cols, &$sch_cols, &$schema_repairs): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `enrollments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `trainee_id` INT NOT NULL DEFAULT 0,
            `program_id` INT NOT NULL DEFAULT 0,
            `student_code` VARCHAR(60) NULL,
            `cohort_name` VARCHAR(120) NULL,
            `assessment_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `attendance_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `grade_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `status` VARCHAR(30) NOT NULL DEFAULT 'Pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS `training_schedules` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `program_id` INT NULL,
            `cohort_name` VARCHAR(120) NULL,
            `session_title` VARCHAR(255) NOT NULL,
            `session_date` DATE NULL,
            `session_time` VARCHAR(80) NULL,
            `venue` VARCHAR(180) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS `registrar_documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `enrollment_id` INT NOT NULL DEFAULT 0,
            `doc_type` VARCHAR(50) NOT NULL,
            `doc_serial` VARCHAR(80) NOT NULL,
            `issued_by` INT NULL,
            `remarks` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $enr_cols = [];
    $r_ec = $conn->query("SHOW COLUMNS FROM `enrollments`");
    if ($r_ec) {
        while ($c = $r_ec->fetch_assoc()) {
            $enr_cols[] = (string)($c['Field'] ?? '');
        }
    }

    $has_student_fk = in_array('trainee_id', $enr_cols, true)
        || in_array('user_id', $enr_cols, true)
        || in_array('student_id', $enr_cols, true);
    if (!$has_student_fk) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `trainee_id` INT NOT NULL DEFAULT 0");
        $enr_cols[] = 'trainee_id';
        $schema_repairs[] = 'enrollments.trainee_id';
    }
    if (!in_array('program_id', $enr_cols, true) && !in_array('course_id', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `program_id` INT NOT NULL DEFAULT 0");
        $enr_cols[] = 'program_id';
        $schema_repairs[] = 'enrollments.program_id';
    }
    if (!in_array('student_code', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `student_code` VARCHAR(60) NULL");
        $enr_cols[] = 'student_code';
        $schema_repairs[] = 'enrollments.student_code';
    }
    if (!in_array('cohort_name', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `cohort_name` VARCHAR(120) NULL");
        $enr_cols[] = 'cohort_name';
        $schema_repairs[] = 'enrollments.cohort_name';
    }
    if (!in_array('assessment_score', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `assessment_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
        $enr_cols[] = 'assessment_score';
        $schema_repairs[] = 'enrollments.assessment_score';
    }
    if (!in_array('attendance_pct', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `attendance_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
        $enr_cols[] = 'attendance_pct';
        $schema_repairs[] = 'enrollments.attendance_pct';
    }
    if (!in_array('grade_verified', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `grade_verified` TINYINT(1) NOT NULL DEFAULT 0");
        $enr_cols[] = 'grade_verified';
        $schema_repairs[] = 'enrollments.grade_verified';
    }
    if (!in_array('status', $enr_cols, true)) {
        $conn->query("ALTER TABLE `enrollments` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'Pending'");
        $enr_cols[] = 'status';
        $schema_repairs[] = 'enrollments.status';
    }

    $sch_cols = [];
    $r_sc = $conn->query("SHOW COLUMNS FROM `training_schedules`");
    if ($r_sc) {
        while ($c = $r_sc->fetch_assoc()) {
            $sch_cols[] = (string)($c['Field'] ?? '');
        }
    }
    if (!in_array('session_title', $sch_cols, true)) {
        $conn->query("ALTER TABLE `training_schedules` ADD COLUMN `session_title` VARCHAR(255) NOT NULL DEFAULT ''");
        $sch_cols[] = 'session_title';
        $schema_repairs[] = 'training_schedules.session_title';
    }
    if (!in_array('cohort_name', $sch_cols, true)) {
        $conn->query("ALTER TABLE `training_schedules` ADD COLUMN `cohort_name` VARCHAR(120) NULL");
        $sch_cols[] = 'cohort_name';
        $schema_repairs[] = 'training_schedules.cohort_name';
    }
    if (!in_array('session_date', $sch_cols, true)) {
        $conn->query("ALTER TABLE `training_schedules` ADD COLUMN `session_date` DATE NULL");
        $sch_cols[] = 'session_date';
        $schema_repairs[] = 'training_schedules.session_date';
    }
    if (!in_array('venue', $sch_cols, true)) {
        $conn->query("ALTER TABLE `training_schedules` ADD COLUMN `venue` VARCHAR(180) NULL");
        $sch_cols[] = 'venue';
        $schema_repairs[] = 'training_schedules.venue';
    }

    $user_cols = [];
    $r_uc = $conn->query("SHOW COLUMNS FROM `users`");
    if ($r_uc) {
        while ($c = $r_uc->fetch_assoc()) {
            $user_cols[] = (string)($c['Field'] ?? '');
        }
    }

    $prog_cols = [];
    $r_pc = $conn->query("SHOW COLUMNS FROM `training_programs`");
    if ($r_pc) {
        while ($c = $r_pc->fetch_assoc()) {
            $prog_cols[] = (string)($c['Field'] ?? '');
        }
    }
};

try {
    $reg_ensure_schema();
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

$enr_student_col = null;
foreach (['trainee_id', 'user_id', 'student_id'] as $cand) {
    if (in_array($cand, $enr_cols, true)) {
        $enr_student_col = $cand;
        break;
    }
}

$enr_program_col = null;
foreach (['program_id', 'course_id'] as $cand) {
    if (in_array($cand, $enr_cols, true)) {
        $enr_program_col = $cand;
        break;
    }
}

$user_name_candidates = [];
foreach (['full_name', 'name', 'username'] as $u_cand) {
    if (in_array($u_cand, $user_cols, true)) {
        $user_name_candidates[] = "u.`" . $u_cand . "`";
    }
}
$has_user_email = in_array('email', $user_cols, true);

$prog_title_en_col = in_array('title_en', $prog_cols, true) ? 'title_en' : (in_array('title', $prog_cols, true) ? 'title' : null);
$prog_title_am_col = in_array('title_am', $prog_cols, true) ? 'title_am' : null;
$prog_code_col     = in_array('code', $prog_cols, true) ? 'code' : null;

$has_enr_status = in_array('status', $enr_cols, true);
$has_stu_code   = in_array('student_code', $enr_cols, true);
$has_cohort     = in_array('cohort_name', $enr_cols, true);
$has_score      = in_array('assessment_score', $enr_cols, true);
$has_att        = in_array('attendance_pct', $enr_cols, true);
$has_g_ver      = in_array('grade_verified', $enr_cols, true);

$reg_generate_student_id = static function (int $enr_id): string {
    return 'REG-' . date('Y') . '-' . str_pad((string)$enr_id, 5, '0', STR_PAD_LEFT);
};

$reg_section_url = static function (string $sec, array $extra = [], string $anchor = '') use ($self_file): string {
    $q = array_merge(['section' => $sec], $extra);
    return $self_file . '?' . http_build_query($q) . $anchor;
};

$reg_fetch_programs = static function () use ($conn, $prog_title_en_col, $prog_title_am_col, $prog_code_col): array {
    $list = [];
    if ($prog_title_en_col === null) {
        return $list;
    }
    $p_sql = "SELECT id, " . ($prog_code_col ? "`" . $prog_code_col . "` AS code, " : "'' AS code, ")
           . "`" . $prog_title_en_col . "` AS title_en"
           . ($prog_title_am_col ? ", `" . $prog_title_am_col . "` AS title_am" : ", '' AS title_am")
           . " FROM `training_programs` ORDER BY id DESC";
    $rp = $conn->query($p_sql);
    if ($rp) {
        while ($p = $rp->fetch_assoc()) {
            $list[] = $p;
        }
    }
    return $list;
};

$reg_fetch_enrollments = static function (string $status_filter = 'ALL', string $search_q = '') use (
    $conn, $enr_cols, $has_enr_status, $has_stu_code, $has_cohort, $has_score, $has_att, $has_g_ver,
    $enr_program_col, $enr_student_col, $user_name_candidates, $has_user_email,
    $prog_title_en_col, $prog_title_am_col, $prog_code_col
): array {
    if (empty($enr_cols)) {
        return [];
    }

    $select_parts = ["e.id"];
    $select_parts[] = $has_enr_status ? "e.status" : "'Pending' AS status";
    $select_parts[] = $has_stu_code ? "e.student_code" : "NULL AS student_code";
    $select_parts[] = $has_cohort ? "e.cohort_name" : "NULL AS cohort_name";
    $select_parts[] = $has_score ? "e.assessment_score" : "0 AS assessment_score";
    $select_parts[] = $has_att ? "e.attendance_pct" : "0 AS attendance_pct";
    $select_parts[] = $has_g_ver ? "e.grade_verified" : "0 AS grade_verified";
    $select_parts[] = ($enr_program_col !== null) ? "e.`" . $enr_program_col . "` AS program_id" : "0 AS program_id";

    if (in_array('created_at', $enr_cols, true)) {
        $select_parts[] = "e.created_at";
    } elseif (in_array('enrolled_at', $enr_cols, true)) {
        $select_parts[] = "e.enrolled_at AS created_at";
    } else {
        $select_parts[] = "NULL AS created_at";
    }

    $joins = [];
    if ($enr_student_col !== null && !empty($user_name_candidates)) {
        $select_parts[] = "COALESCE(" . implode(", ", $user_name_candidates) . ", CONCAT('User #', e.`" . $enr_student_col . "`)) AS student_name";
        $select_parts[] = $has_user_email ? "u.email AS student_email" : "'' AS student_email";
        $joins[] = "LEFT JOIN `users` u ON e.`" . $enr_student_col . "` = u.id";
    } else {
        $select_parts[] = "'Trainee' AS student_name";
        $select_parts[] = "'' AS student_email";
    }

    if ($enr_program_col !== null && $prog_title_en_col !== null) {
        $title_expr = $prog_title_am_col
            ? "COALESCE(NULLIF(p.`" . $prog_title_am_col . "`, ''), p.`" . $prog_title_en_col . "`)"
            : "p.`" . $prog_title_en_col . "`";
        $select_parts[] = $title_expr . " AS program_title";
        $select_parts[] = $prog_code_col ? "p.`" . $prog_code_col . "` AS program_code" : "'' AS program_code";
        $joins[] = "LEFT JOIN `training_programs` p ON e.`" . $enr_program_col . "` = p.id";
    } else {
        $select_parts[] = "'Program' AS program_title";
        $select_parts[] = "'' AS program_code";
    }

    $where_sql = [];
    $types     = '';
    $params    = [];

    if ($status_filter !== 'ALL' && $has_enr_status) {
        $where_sql[] = "e.status = ?";
        $types .= 's';
        $params[] = $status_filter;
    }

    if ($search_q !== '') {
        $like_clauses = [];
        $like_val = '%' . $search_q . '%';

        if ($has_stu_code) {
            $like_clauses[] = "e.student_code LIKE ?";
            $types .= 's';
            $params[] = $like_val;
        }
        if ($has_cohort) {
            $like_clauses[] = "e.cohort_name LIKE ?";
            $types .= 's';
            $params[] = $like_val;
        }
        if ($enr_student_col !== null && !empty($user_name_candidates)) {
            foreach ($user_name_candidates as $u_expr) {
                $like_clauses[] = $u_expr . " LIKE ?";
                $types .= 's';
                $params[] = $like_val;
            }
            if ($has_user_email) {
                $like_clauses[] = "u.email LIKE ?";
                $types .= 's';
                $params[] = $like_val;
            }
        }
        if ($enr_program_col !== null && $prog_title_en_col !== null) {
            if ($prog_code_col) {
                $like_clauses[] = "p.`" . $prog_code_col . "` LIKE ?";
                $types .= 's';
                $params[] = $like_val;
            }
            $like_clauses[] = "p.`" . $prog_title_en_col . "` LIKE ?";
            $types .= 's';
            $params[] = $like_val;
            if ($prog_title_am_col) {
                $like_clauses[] = "p.`" . $prog_title_am_col . "` LIKE ?";
                $types .= 's';
                $params[] = $like_val;
            }
        }

        if (!empty($like_clauses)) {
            $where_sql[] = "(" . implode(" OR ", $like_clauses) . ")";
        }
    }

    $sql = "SELECT " . implode(", ", $select_parts) . " FROM `enrollments` e " . implode(" ", $joins);
    if (!empty($where_sql)) {
        $sql .= " WHERE " . implode(" AND ", $where_sql);
    }
    $sql .= " ORDER BY e.id DESC";

    $rows = [];
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }
    return $rows;
};

if (isset($_GET['export'])) {
    $exp_type = (string)$_GET['export'];
    if ($exp_type === 'enrollments_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="registrar_enrollments_' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Enrollment ID', 'Student ID', 'Trainee Name', 'Email',
            'Program Code', 'Program Title', 'Cohort',
            'Score (%)', 'Attendance (%)', 'Grade Verified', 'Status', 'Created At'
        ]);
        try {
            foreach ($reg_fetch_enrollments($status_filter, $search_q) as $r) {
                fputcsv($out, [
                    (int)($r['id'] ?? 0),
                    (string)($r['student_code'] ?? ''),
                    (string)($r['student_name'] ?? ''),
                    (string)($r['student_email'] ?? ''),
                    (string)($r['program_code'] ?? ''),
                    (string)($r['program_title'] ?? ''),
                    (string)($r['cohort_name'] ?? ''),
                    (string)($r['assessment_score'] ?? '0'),
                    (string)($r['attendance_pct'] ?? '0'),
                    ((int)($r['grade_verified'] ?? 0) === 1) ? 'Yes' : 'No',
                    (string)($r['status'] ?? 'Pending'),
                    (string)($r['created_at'] ?? ''),
                ]);
            }
        } catch (Throwable $e) {
            // Ignore stream errors after headers
        }
        fclose($out);
        exit;
    } elseif ($exp_type === 'documents_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="registrar_documents_' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Serial No', 'Document Type', 'Enrollment ID', 'Remarks', 'Issued Date']);
        try {
            $rcsv = $conn->query("SELECT id, doc_serial, doc_type, enrollment_id, remarks, created_at FROM `registrar_documents` ORDER BY id DESC");
            if ($rcsv) {
                while ($r = $rcsv->fetch_assoc()) {
                    fputcsv($out, [
                        (int)($r['id'] ?? 0),
                        (string)($r['doc_serial'] ?? ''),
                        (string)($r['doc_type'] ?? ''),
                        (int)($r['enrollment_id'] ?? 0),
                        (string)($r['remarks'] ?? ''),
                        (string)($r['created_at'] ?? ''),
                    ]);
                }
            }
        } catch (Throwable $e) {
            // Ignore stream errors after headers
        }
        fclose($out);
        exit;
    }
}

$enr_filter_extra = [];
if ($status_filter !== '' && $status_filter !== 'ALL') {
    $enr_filter_extra['status_filter'] = $status_filter;
}
if ($search_q !== '') {
    $enr_filter_extra['q'] = $search_q;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'approve_enrollment' || $act === 'reject_enrollment') {
        $enr_id = (int)($_POST['enrollment_id'] ?? 0);
        if ($enr_id > 0) {
            $new_status = ($act === 'approve_enrollment') ? 'Approved' : 'Rejected';
            try {
                if ($new_status === 'Approved') {
                    $code_gen = $reg_generate_student_id($enr_id);
                    $stmt = $conn->prepare("UPDATE `enrollments` SET `status` = ?, `student_code` = COALESCE(NULLIF(`student_code`, ''), ?) WHERE `id` = ?");
                    if ($stmt) {
                        $stmt->bind_param("ssi", $new_status, $code_gen, $enr_id);
                        if ($stmt->execute()) {
                            $_SESSION['reg_msg'] = $is_am
                                ? "ምዝገባው ተረጋግጧል፤ መታወቂያ ({$code_gen}) ተመድቧል።"
                                : "Enrollment approved and Student ID ({$code_gen}) assigned.";
                        } else {
                            $_SESSION['reg_err'] = $is_am ? 'የምዝገባ ሁኔታ ማዘመን አልተቻለም።' : 'Failed to update enrollment status.';
                        }
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE `enrollments` SET `status` = ? WHERE `id` = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $new_status, $enr_id);
                        if ($stmt->execute()) {
                            $_SESSION['reg_msg'] = $is_am ? 'ምዝገባው ተቀባይነት አላገኘም።' : 'Enrollment rejected.';
                        } else {
                            $_SESSION['reg_err'] = $is_am ? 'የምዝገባ ሁኔታ ማዘመን አልተቻለም።' : 'Failed to update enrollment status.';
                        }
                        $stmt->close();
                    }
                }
            } catch (Throwable $e) {
                $_SESSION['reg_err'] = $e->getMessage();
            }
        }
        $anchor = ($enr_id > 0) ? ('#enr-row-' . $enr_id) : '#registrar-enrollment-panel';
        header("Location: " . $reg_section_url('enrollment', $enr_filter_extra, $anchor));
        exit;
    } elseif ($act === 'assign_placement') {
        $enr_id     = (int)($_POST['enrollment_id'] ?? 0);
        $cohort_val = htmlspecialchars_decode(clean_input(trim((string)($_POST['cohort_name'] ?? ''))), ENT_QUOTES);
        $prog_val   = (int)($_POST['program_id'] ?? 0);

        if ($enr_id > 0) {
            try {
                $code_gen = $reg_generate_student_id($enr_id);
                if ($enr_program_col !== null && $prog_val > 0) {
                    $stmt = $conn->prepare("
                        UPDATE `enrollments`
                        SET `cohort_name` = ?, `{$enr_program_col}` = ?, `student_code` = COALESCE(NULLIF(`student_code`, ''), ?), `status` = 'Approved'
                        WHERE `id` = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param("sisi", $cohort_val, $prog_val, $code_gen, $enr_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare("
                        UPDATE `enrollments`
                        SET `cohort_name` = ?, `student_code` = COALESCE(NULLIF(`student_code`, ''), ?), `status` = 'Approved'
                        WHERE `id` = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param("ssi", $cohort_val, $code_gen, $enr_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                $_SESSION['reg_msg'] = $is_am
                    ? 'የተማሪው ምድብ (Cohort) እና መታወቂያ ቁጥር ተዘምኗል።'
                    : 'Student placement, cohort, and ID updated.';
            } catch (Throwable $e) {
                $_SESSION['reg_err'] = $e->getMessage();
            }
        }
        $anchor = ($enr_id > 0) ? ('#enr-row-' . $enr_id) : '#registrar-enrollment-panel';
        header("Location: " . $reg_section_url('enrollment', $enr_filter_extra, $anchor));
        exit;
    } elseif ($act === 'auto_process_pending') {
        $processed = 0;
        try {
            $res_p = $conn->query("SELECT id, cohort_name FROM `enrollments` WHERE LOWER(`status`) = 'pending' ORDER BY id ASC");
            if ($res_p) {
                while ($p_row = $res_p->fetch_assoc()) {
                    $pid_enr = (int)($p_row['id'] ?? 0);
                    if ($pid_enr <= 0) {
                        continue;
                    }
                    $auto_code   = $reg_generate_student_id($pid_enr);
                    $auto_cohort = trim((string)($p_row['cohort_name'] ?? ''));
                    if ($auto_cohort === '') {
                        $auto_cohort = 'Batch-' . date('Y') . '-' . str_pad((string)((($pid_enr - 1) % 4) + 1), 2, '0', STR_PAD_LEFT);
                    }
                    $stmt = $conn->prepare("
                        UPDATE `enrollments`
                        SET `status` = 'Approved',
                            `cohort_name` = ?,
                            `student_code` = COALESCE(NULLIF(`student_code`, ''), ?)
                        WHERE `id` = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param("ssi", $auto_cohort, $auto_code, $pid_enr);
                        if ($stmt->execute()) {
                            $processed++;
                        }
                        $stmt->close();
                    }
                }
            }
            $_SESSION['reg_msg'] = $is_am
                ? "{$processed} በመጠባበቅ ላይ የነበሩ ምዝገባዎች በራስ-ሰር ተስተናግደው መታወቂያ ተሰጥቷቸዋል።"
                : "Auto-processed {$processed} pending enrollment(s) with IDs and cohorts.";
        } catch (Throwable $e) {
            $_SESSION['reg_err'] = $e->getMessage();
        }
        header("Location: " . $reg_section_url('enrollment', $enr_filter_extra, '#registrar-enrollment-panel'));
        exit;
    } elseif ($act === 'verify_grade_record') {
        $enr_id = (int)($_POST['enrollment_id'] ?? 0);
        $score  = max(0.0, min(100.0, (float)($_POST['assessment_score'] ?? 0)));
        $att    = max(0.0, min(100.0, (float)($_POST['attendance_pct'] ?? 0)));

        if ($enr_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE `enrollments` SET `assessment_score` = ?, `attendance_pct` = ?, `grade_verified` = 1 WHERE `id` = ?");
                if ($stmt) {
                    $stmt->bind_param("ddi", $score, $att, $enr_id);
                    if ($stmt->execute()) {
                        $_SESSION['reg_msg'] = $is_am ? 'ውጤት እና መገኘት ተረጋግጦ ተቀምጧል።' : 'Grade and attendance verified and saved.';
                    } else {
                        $_SESSION['reg_err'] = $is_am ? 'ውጤቱን ማረጋገጥ አልተቻለም።' : 'Failed to verify grade record.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['reg_err'] = $e->getMessage();
            }
        }
        $anchor = ($enr_id > 0) ? ('#grd-row-' . $enr_id) : '#registrar-academics-panel';
        header("Location: " . $reg_section_url('academics', [], $anchor));
        exit;
    } elseif ($act === 'add_academic_schedule') {
        $prog_id = (int)($_POST['program_id'] ?? 0);
        $cohort  = htmlspecialchars_decode(clean_input(trim((string)($_POST['cohort_name'] ?? ''))), ENT_QUOTES);
        $title   = htmlspecialchars_decode(clean_input(trim((string)($_POST['session_title'] ?? ''))), ENT_QUOTES);
        $s_date  = trim((string)($_POST['session_date'] ?? ''));
        $s_time  = htmlspecialchars_decode(clean_input(trim((string)($_POST['session_time'] ?? ''))), ENT_QUOTES);
        $venue   = htmlspecialchars_decode(clean_input(trim((string)($_POST['venue'] ?? ''))), ENT_QUOTES);

        if ($title === '' || $s_date === '') {
            $_SESSION['reg_err'] = $is_am ? 'እባክዎ የክፍለ-ጊዜ ርዕስ እና ቀን ያስገቡ።' : 'Please provide a session title and date.';
        } else {
            try {
                $pid_val = ($prog_id > 0) ? $prog_id : null;
                $stmt = $conn->prepare("INSERT INTO `training_schedules` (`program_id`, `cohort_name`, `session_title`, `session_date`, `session_time`, `venue`) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isssss", $pid_val, $cohort, $title, $s_date, $s_time, $venue);
                    if ($stmt->execute()) {
                        $_SESSION['reg_msg'] = $is_am ? 'የትምህርት መርሐ-ግብር ተቀምጧል።' : 'Academic schedule saved.';
                    } else {
                        $_SESSION['reg_err'] = $is_am ? 'መርሐ-ግብሩን ማስቀመጥ አልተቻለም።' : 'Failed to save schedule.';
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $_SESSION['reg_err'] = $e->getMessage();
            }
        }
        header("Location: " . $reg_section_url('academics', [], '#registrar-schedule-panel'));
        exit;
    } elseif ($act === 'issue_registrar_doc') {
        $enr_id   = (int)($_POST['enrollment_id'] ?? 0);
        $doc_type = trim((string)($_POST['doc_type'] ?? 'Transcript'));
        $allowed_docs = ['Transcript', 'Certificate', 'Graduation List', 'Progress Report'];
        if (!in_array($doc_type, $allowed_docs, true)) {
            $doc_type = 'Transcript';
        }
        $remarks = htmlspecialchars_decode(clean_input(trim((string)($_POST['remarks'] ?? ''))), ENT_QUOTES);
        $serial  = 'DOC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $new_doc_id = 0;

        try {
            $issuer_val = ($uid > 0) ? $uid : null;
            $stmt = $conn->prepare("INSERT INTO `registrar_documents` (`enrollment_id`, `doc_type`, `doc_serial`, `issued_by`, `remarks`) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issis", $enr_id, $doc_type, $serial, $issuer_val, $remarks);
                if ($stmt->execute()) {
                    $new_doc_id = (int)$conn->insert_id;
                    $_SESSION['reg_msg'] = $is_am ? "{$doc_type} ሰነድ በመለያ ቁጥር ({$serial}) ተዘጋጅቷል።" : "{$doc_type} generated with serial ({$serial}).";
                } else {
                    $_SESSION['reg_err'] = $is_am ? 'ሰነዱን ማዘጋጀት አልተቻለም።' : 'Failed to generate document.';
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $_SESSION['reg_err'] = $e->getMessage();
        }

        $extra_doc = ($new_doc_id > 0) ? ['print_doc' => $new_doc_id] : [];
        header("Location: " . $reg_section_url('documents', $extra_doc, '#registrar-documents-panel'));
        exit;
    } elseif ($act === 'run_schema_self_heal') {
        try {
            $reg_ensure_schema();
            $_SESSION['reg_msg'] = $is_am
                ? (empty($schema_repairs) ? 'የዳታቤዝ ሰንጠረዦች እና አምዶች በሙሉ ጤናማ እና የተሟሉ ናቸው።' : 'የተስተካከሉ/የተጨመሩ አምዶች: ' . implode(', ', $schema_repairs))
                : (empty($schema_repairs) ? 'Database tables and columns are healthy and complete.' : 'Repaired/added columns: ' . implode(', ', $schema_repairs));
        } catch (Throwable $e) {
            $_SESSION['reg_err'] = $e->getMessage();
        }
        header("Location: " . $reg_section_url('maintenance', [], '#registrar-maintenance-panel'));
        exit;
    }
}

$total_enr     = 0;
$pending_enr   = 0;
$approved_enr  = 0;
$verified_enr  = 0;
$missing_codes = 0;

if (!empty($enr_cols)) {
    try {
        $r_all = $conn->query("
            SELECT " . ($has_enr_status ? "`status`" : "'Pending' AS `status`") . ",
                   " . ($has_stu_code ? "`student_code`" : "NULL AS `student_code`") . ",
                   " . ($has_g_ver ? "`grade_verified`" : "0 AS `grade_verified`") . "
            FROM `enrollments`
        ");
        if ($r_all) {
            while ($a_row = $r_all->fetch_assoc()) {
                $total_enr++;
                $st_a = strtolower((string)($a_row['status'] ?? 'pending'));
                if ($st_a === 'pending') {
                    $pending_enr++;
                } elseif (in_array($st_a, ['approved', 'active', 'completed'], true)) {
                    $approved_enr++;
                }
                if ((int)($a_row['grade_verified'] ?? 0) === 1) {
                    $verified_enr++;
                }
                if (trim((string)($a_row['student_code'] ?? '')) === '') {
                    $missing_codes++;
                }
            }
        }
    } catch (Throwable $e) {
        $err = $err ?: $e->getMessage();
    }
}

$enrollments        = [];
$programs_list      = [];
$schedules_list     = [];
$docs_list          = [];
$enr_by_id          = [];
$selected_print_doc = null;

try {
    if ($active_section === 'enrollment') {
        $enrollments   = $reg_fetch_enrollments($status_filter, $search_q);
        $programs_list = $reg_fetch_programs();
    } elseif ($active_section === 'academics') {
        $enrollments   = $reg_fetch_enrollments('ALL', '');
        $programs_list = $reg_fetch_programs();
        $rs = $conn->query("
            SELECT s.id, s.cohort_name, s.session_title, s.session_date, s.session_time, s.venue,
                   p.code AS program_code, p.title_en, p.title_am
            FROM `training_schedules` s
            LEFT JOIN `training_programs` p ON s.program_id = p.id
            ORDER BY s.id DESC
        ");
        if ($rs) {
            while ($s = $rs->fetch_assoc()) {
                $schedules_list[] = $s;
            }
        }
    } elseif ($active_section === 'documents') {
        $enrollments = $reg_fetch_enrollments('ALL', '');
        foreach ($enrollments as $er) {
            $enr_by_id[(int)($er['id'] ?? 0)] = $er;
        }
        $rd = $conn->query("SELECT d.id, d.enrollment_id, d.doc_type, d.doc_serial, d.remarks, d.created_at FROM `registrar_documents` d ORDER BY d.id DESC LIMIT 50");
        if ($rd) {
            while ($d = $rd->fetch_assoc()) {
                $docs_list[] = $d;
                if ($print_doc_id > 0 && (int)($d['id'] ?? 0) === $print_doc_id) {
                    $selected_print_doc = $d;
                }
            }
        }
    }
} catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
}

$export_enr_url = $reg_section_url('enrollment', array_merge($enr_filter_extra, ['export' => 'enrollments_csv']));
$export_doc_url = $reg_section_url('documents', ['export' => 'documents_csv']);

$section_titles = [
    'enrollment'  => [
        'title' => $is_am ? 'ምዝገባ እና ምድብ አስተዳደር' : 'Enrollment & Placement',
        'sub'   => $is_am ? 'የመግቢያ ሂደት ማጽደቅ፣ የተማሪ መታወቂያ ማመንጨት እና ወደ ክፍል መመደብ' : 'Approve admissions, assign student IDs, and place trainees into cohorts',
    ],
    'academics'   => [
        'title' => $is_am ? 'የትምህርት መዝገብ፣ ውጤት እና መርሐ-ግብር' : 'Academic Records, Grades & Schedule',
        'sub'   => $is_am ? 'የውጤት እና የመገኘት ማረጋገጫ እና የትምህርት መርሐ-ግብር አስተዳደር' : 'Verify grades and attendance and manage academic schedules',
    ],
    'documents'   => [
        'title' => $is_am ? 'አገልግሎት እና ሰነድ ማዘጋጀት' : 'Documents, Transcripts & Graduation',
        'sub'   => $is_am ? 'ኦፊሴላዊ የውጤት ሰነድ፣ የምስክር ወረቀት፣ የምረቃ ዝርዝር እና እድገት ሪፖርት ማዘጋጀትና ማተም' : 'Issue and print official transcripts, certificates, graduation lists, and progress reports',
    ],
    'maintenance' => [
        'title' => $is_am ? 'መረጃ ማረጋገጫ እና የስርዓት ጥገና' : 'Data Audit & System Maintenance',
        'sub'   => $is_am ? 'የተማሪ መረጃ ትክክለኛነት ማረጋገጫ እና የዳታቤዝ ሰንጠረዦች/አምዶች ጥገና' : 'Verify trainee data integrity and maintain database tables and columns',
    ],
];
$cur_head = $section_titles[$active_section];

include $rel_root . 'includes/header.php';
include $rel_root . 'includes/sidebar.php';
?>
<style>
.registrar-filter-bar { display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end; padding:0.85rem 1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:1.1rem; }
.registrar-action-stack { display:flex; flex-direction:column; gap:0.45rem; min-width:240px; }
.registrar-placement-row, .registrar-approval-row { display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center; }
.registrar-prog-select { max-width:230px; padding:0.28rem 0.5rem; font-size:0.82rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.registrar-id-chip { display:inline-block; margin-top:0.25rem; padding:0.14rem 0.5rem; border-radius:999px; font-size:0.75rem; font-weight:600; background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
.registrar-id-chip.pending { background:#fffbeb; color:#92400e; border-color:#fde68a; }
@media print {
    .hub-sidebar, .no-print, .dashboard-header, .alert, form, button { display: none !important; }
    .content-with-sidebar { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .registrar-print-sheet { display: block !important; border: 2px solid #0f172a !important; }
}
</style>

<div class="content-with-sidebar">
    <div class="dashboard-header">
        <div>
            <h1><?php echo htmlspecialchars($cur_head['title']); ?></h1>
            <p style="color:#64748b; margin-bottom:0;"><?php echo htmlspecialchars($cur_head['sub']); ?></p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <?php if ($active_section === 'enrollment'): ?>
        <div class="card-box" id="registrar-enrollment-panel">
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:0.75rem; margin-bottom:0.9rem;">
                <h3 style="color:var(--primary-color); margin:0;">
                    <?php echo $is_am ? 'የምዝገባ እና ምድብ ዝርዝር' : 'Enrollment & Placement Records'; ?> (<?php echo count($enrollments); ?> / <?php echo (int)$total_enr; ?>)
                </h3>
                <div style="display:flex; flex-wrap:wrap; gap:0.45rem;">
                    <a href="<?php echo htmlspecialchars($export_enr_url); ?>" class="btn btn-outline btn-sm">
                        <i class="bi bi-download"></i> <?php echo $is_am ? 'CSV አውርድ' : 'Export CSV'; ?>
                    </a>
                    <form method="POST" action="<?php echo htmlspecialchars($reg_section_url('enrollment', $enr_filter_extra)); ?>" style="display:inline;">
                        <input type="hidden" name="section" value="enrollment">
                        <input type="hidden" name="action" value="auto_process_pending">
                        <button type="submit" class="btn btn-outline btn-sm">
                            <i class="bi bi-magic"></i> <?php echo $is_am ? 'በመጠባበቅ ላይ ያሉትን በራስ-ሰር አጽድቅ' : 'Auto-Process Pending'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <form method="GET" action="<?php echo htmlspecialchars($self_file); ?>" class="registrar-filter-bar">
                <input type="hidden" name="section" value="enrollment">
                <div class="form-group" style="margin:0;">
                    <label><?php echo $is_am ? 'ሁኔታ አጣራ' : 'Filter by Status'; ?></label>
                    <select name="status_filter" class="form-control" style="min-width:165px;">
                        <option value="ALL" <?php echo ($status_filter === 'ALL') ? 'selected' : ''; ?>><?php echo $is_am ? 'ሁሉም ሁኔታዎች' : 'All Statuses'; ?></option>
                        <?php foreach ($allowed_status as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($status_filter === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0; flex:1 1 220px;">
                    <label><?php echo $is_am ? 'ፈልግ (ሰልጣኝ / ፕሮግራም / መታወቂያ)' : 'Search (Trainee / Program / ID)'; ?></label>
                    <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo $is_am ? 'ስም፣ ኮድ ወይም ፕሮግራም...' : 'Name, code, or program...'; ?>">
                </div>
                <div class="form-group" style="margin:0; display:flex; gap:0.45rem; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> <?php echo $is_am ? 'አሳይ' : 'Show'; ?></button>
                    <a href="<?php echo htmlspecialchars($reg_section_url('enrollment', [], '#registrar-enrollment-panel')); ?>" class="btn btn-outline"><?php echo $is_am ? 'ሁሉንም አሳይ' : 'Reset All'; ?></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo $is_am ? 'ሰልጣኝ / መታወቂያ' : 'Trainee & ID'; ?></th>
                            <th><?php echo $is_am ? 'ፕሮግራም / ክፍል' : 'Program & Cohort'; ?></th>
                            <th><?php echo $is_am ? 'ሁኔታ' : 'Status'; ?></th>
                            <th><?php echo $is_am ? 'ምድብ እና ማጽደቅ' : 'Placement & Action'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($enrollments)): ?>
                            <?php foreach ($enrollments as $enr):
                                $eid      = (int)($enr['id'] ?? 0);
                                $p_code   = trim((string)($enr['program_code'] ?? ''));
                                $p_title  = trim((string)($enr['program_title'] ?? ''));
                                $stu_code = trim((string)($enr['student_code'] ?? ''));
                                $cohort_n = trim((string)($enr['cohort_name'] ?? ''));
                                $cur_pid  = (int)($enr['program_id'] ?? 0);
                            ?>
                            <tr id="enr-row-<?php echo $eid; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars(trim((string)($enr['student_name'] ?? ''))); ?></strong>
                                    <div><span class="registrar-id-chip <?php echo ($stu_code === '') ? 'pending' : ''; ?>"><?php echo htmlspecialchars($stu_code !== '' ? $stu_code : ($is_am ? 'መታወቂያ አልተሰጠም' : 'ID Pending')); ?></span></div>
                                </td>
                                <td>
                                    <div><?php if ($p_code !== ''): ?><strong><?php echo htmlspecialchars($p_code); ?></strong> — <?php endif; ?><?php echo htmlspecialchars($p_title); ?></div>
                                    <?php if ($cohort_n !== ''): ?><div style="font-size:0.8rem; color:#475569; margin-top:0.2rem;"><i class="bi bi-layers"></i> <?php echo htmlspecialchars($cohort_n); ?></div><?php endif; ?>
                                </td>
                                <td><?php echo status_badge((string)($enr['status'] ?? 'Pending')); ?></td>
                                <td>
                                    <div class="registrar-action-stack">
                                        <form method="POST" action="<?php echo htmlspecialchars($reg_section_url('enrollment', $enr_filter_extra, '#enr-row-' . $eid)); ?>" class="registrar-placement-row">
                                            <input type="hidden" name="section" value="enrollment">
                                            <input type="hidden" name="action" value="assign_placement">
                                            <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                            <input type="text" name="cohort_name" class="form-control" value="<?php echo htmlspecialchars($cohort_n); ?>" placeholder="<?php echo $is_am ? 'ክፍል/ኮሆርት' : 'Cohort'; ?>" style="width:115px; padding:0.28rem 0.5rem; font-size:0.82rem;">
                                            <?php if (!empty($programs_list)): ?>
                                                <select name="program_id" class="form-control registrar-prog-select">
                                                    <?php foreach ($programs_list as $pr):
                                                        $pid_opt = (int)$pr['id'];
                                                        $pr_c    = trim((string)($pr['code'] ?? ''));
                                                        $pt_opt  = trim((string)(($is_am && !empty($pr['title_am'])) ? $pr['title_am'] : ($pr['title_en'] ?? '')));
                                                        $opt_txt = trim(($pr_c !== '' ? ($pr_c . ' ') : '') . $pt_opt);
                                                    ?>
                                                        <option value="<?php echo $pid_opt; ?>" <?php echo ($cur_pid === $pid_opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt_txt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-outline btn-sm"><?php echo $is_am ? 'መድብ' : 'Assign'; ?></button>
                                        </form>
                                        <form method="POST" action="<?php echo htmlspecialchars($reg_section_url('enrollment', $enr_filter_extra, '#enr-row-' . $eid)); ?>" class="registrar-approval-row">
                                            <input type="hidden" name="section" value="enrollment">
                                            <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                            <button type="submit" name="action" value="approve_enrollment" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> <?php echo $is_am ? 'አጽድቅ' : 'Approve'; ?></button>
                                            <button type="submit" name="action" value="reject_enrollment" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#dc2626;"><i class="bi bi-x-lg"></i> <?php echo $is_am ? 'ውድቅ' : 'Reject'; ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4"><?php echo htmlspecialchars($lang['no_records'] ?? ($is_am ? 'ምንም የምዝገባ መረጃ አልተገኘም።' : 'No enrollment records found.')); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($active_section === 'academics'): ?>
        <div class="card-box" id="registrar-academics-panel" style="margin-bottom:1.1rem;">
            <h3 style="color:var(--primary-color); margin-bottom:0.75rem;"><?php echo $is_am ? 'የውጤት እና የመገኘት ማረጋገጫ' : 'Grade & Attendance Verification'; ?></h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo $is_am ? 'ሰልጣኝ' : 'Trainee'; ?></th>
                            <th><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></th>
                            <th><?php echo $is_am ? 'ማረጋገጫ' : 'Verified'; ?></th>
                            <th><?php echo $is_am ? 'ውጤት (%) / መገኘት (%)' : 'Score (%) & Att. (%)'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($enrollments)): ?>
                            <?php foreach ($enrollments as $enr):
                                $eid    = (int)($enr['id'] ?? 0);
                                $is_ver = ((int)($enr['grade_verified'] ?? 0) === 1);
                                $sc_val = (float)($enr['assessment_score'] ?? 0);
                                $at_val = (float)($enr['attendance_pct'] ?? 0);
                            ?>
                            <tr id="grd-row-<?php echo $eid; ?>">
                                <td><strong><?php echo htmlspecialchars((string)($enr['student_name'] ?? '')); ?></strong></td>
                                <td><?php echo htmlspecialchars(trim((string)(($enr['program_code'] ?? '') . ' — ' . ($enr['program_title'] ?? '')))); ?></td>
                                <td>
                                    <?php if ($is_ver): ?>
                                        <span class="badge" style="background:#dcfce7; color:#166534;"><?php echo $is_am ? 'ተረጋግጧል' : 'Verified'; ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#fef3c7; color:#92400e;"><?php echo $is_am ? 'በሂደት ላይ' : 'Unverified'; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="<?php echo htmlspecialchars($reg_section_url('academics', [], '#grd-row-' . $eid)); ?>" style="display:flex; flex-wrap:wrap; gap:0.4rem; align-items:center;">
                                        <input type="hidden" name="section" value="academics">
                                        <input type="hidden" name="action" value="verify_grade_record">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $eid; ?>">
                                        <input type="number" name="assessment_score" class="form-control" min="0" max="100" step="0.1" value="<?php echo htmlspecialchars((string)$sc_val); ?>" style="width:84px; padding:0.28rem 0.45rem; font-size:0.82rem;">
                                        <input type="number" name="attendance_pct" class="form-control" min="0" max="100" step="0.1" value="<?php echo htmlspecialchars((string)$at_val); ?>" style="width:84px; padding:0.28rem 0.45rem; font-size:0.82rem;">
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2-all"></i> <?php echo $is_am ? 'አረጋግጥ' : 'Verify'; ?></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4"><?php echo $is_am ? 'ምንም የትምህርት መዝገብ አልተገኘም።' : 'No academic records found.'; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-box" id="registrar-schedule-panel">
            <h3 style="color:var(--primary-color); margin-bottom:0.75rem;"><?php echo $is_am ? 'የትምህርት መርሐ-ግብር (Academic Schedule)' : 'Academic Schedule'; ?> (<?php echo count($schedules_list); ?>)</h3>
            <form method="POST" action="<?php echo htmlspecialchars($reg_section_url('academics', [], '#registrar-schedule-panel')); ?>" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:0.65rem; align-items:end; margin-bottom:1rem;">
                <input type="hidden" name="section" value="academics">
                <input type="hidden" name="action" value="add_academic_schedule">
                <div class="form-group" style="margin:0;">
                    <label><?php echo $is_am ? 'ፕሮግራም' : 'Program'; ?></label>
                    <select name="program_id" class="form-control">
                        <option value="0"><?php echo $is_am ? 'አጠቃላይ' : 'General'; ?></option>
                        <?php foreach ($programs_list as $pr):
                            $pt_opt = trim((string)(($is_am && !empty($pr['title_am'])) ? $pr['title_am'] : ($pr['title_en'] ?? '')));
                        ?>
                            <option value="<?php echo (int)$pr['id']; ?>"><?php echo htmlspecialchars(trim((string)(($pr['code'] ?? '') . ' ' . $pt_opt))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;"><label><?php echo $is_am ? 'ክፍል / ኮሆርት' : 'Cohort'; ?></label><input type="text" name="cohort_name" class="form-control" placeholder="Cohort 1"></div>
                <div class="form-group" style="margin:0;"><label><?php echo $is_am ? 'ርዕስ *' : 'Title *'; ?></label><input type="text" name="session_title" class="form-control" required></div>
                <div class="form-group" style="margin:0;"><label><?php echo $is_am ? 'ቀን *' : 'Date *'; ?></label><input type="date" name="session_date" class="form-control" required></div>
                <div class="form-group" style="margin:0;"><label><?php echo $is_am ? 'ቦታ' : 'Venue'; ?></label><input type="text" name="venue" class="form-control"></div>
                <div class="form-group" style="margin:0;"><button type="submit" class="btn btn-primary" style="width:100%;"><i class="bi bi-calendar-plus"></i> <?php echo $is_am ? 'ጨምር' : 'Add'; ?></button></div>
            </form>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo $is_am ? 'ርዕስ' : 'Session'; ?></th>
                            <th><?php echo $is_am ? 'ኮሆርት / ፕሮግራም' : 'Cohort & Program'; ?></th>
                            <th><?php echo $is_am ? 'ቀን' : 'Date'; ?></th>
                            <th><?php echo $is_am ? 'ቦታ' : 'Venue'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($schedules_list)): ?>
                            <?php foreach ($schedules_list as $sc): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string)($sc['session_title'] ?? '')); ?></strong></td>
                                <td><?php echo htmlspecialchars(trim((string)(($sc['cohort_name'] ?? '') . ' ' . ($sc['program_code'] ?? '')))); ?></td>
                                <td><?php echo htmlspecialchars((string)($sc['session_date'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)($sc['venue'] ?? '-')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4"><?php echo $is_am ? 'መርሐ-ግብር አልተመዘገበም።' : 'No schedules recorded.'; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($active_section === 'documents'): ?>
        <div class="card-box" id="registrar-documents-panel" style="margin-bottom:1.1rem;">
            <h3 style="color:var(--primary-color); margin-bottom:0.75rem;"><?php echo $is_am ? 'አዲስ ሰነድ ማዘጋጀት' : 'Generate Official Document'; ?></h3>
            <form method="POST" action="<?php echo htmlspecialchars($reg_section_url('documents', [], '#registrar-documents-panel')); ?>" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:0.7rem; align-items:end;">
                <input type="hidden" name="section" value="documents">
                <input type="hidden" name="action" value="issue_registrar_doc">
                <div class="form-group" style="margin:0;">
                    <label><?php echo $is_am ? 'የሰነድ ዓይነት' : 'Document Type'; ?></label>
                    <select name="doc_type" class="form-control">
                        <option value="Transcript"><?php echo $is_am ? 'ኦፊሴላዊ የውጤት ሰነድ (Transcript)' : 'Official Transcript'; ?></option>
                        <option value="Certificate"><?php echo $is_am ? 'የምስክር ወረቀት (Certificate)' : 'Certificate'; ?></option>
                        <option value="Graduation List"><?php echo $is_am ? 'የምረቃ ዝርዝር / ዲግሪ' : 'Graduation / Degree List'; ?></option>
                        <option value="Progress Report"><?php echo $is_am ? 'የትምህርት እድገት ሪፖርት' : 'Progress Report'; ?></option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label><?php echo $is_am ? 'ሰልጣኝ / ምዝገባ' : 'Enrollment Record'; ?></label>
                    <select name="enrollment_id" class="form-control">
                        <option value="0"><?php echo $is_am ? 'አጠቃላይ ዝርዝር' : 'General / Cohort Batch'; ?></option>
                        <?php foreach ($enrollments as $enr): ?>
                            <option value="<?php echo (int)$enr['id']; ?>">#<?php echo (int)$enr['id']; ?> — <?php echo htmlspecialchars((string)($enr['student_name'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label><?php echo $is_am ? 'ማስታወሻ' : 'Remarks'; ?></label>
                    <input type="text" name="remarks" class="form-control" placeholder="<?php echo $is_am ? 'ለምሳሌ 2018 E.C. ምረቃ' : 'e.g. 2026 Graduation'; ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="bi bi-file-earmark-plus"></i> <?php echo $is_am ? 'ሰነድ አመንጭ' : 'Generate Document'; ?></button>
                </div>
            </form>
        </div>

        <?php if ($selected_print_doc !== null):
            $p_eid = (int)($selected_print_doc['enrollment_id'] ?? 0);
            $p_enr = ($p_eid > 0 && isset($enr_by_id[$p_eid])) ? $enr_by_id[$p_eid] : null;
        ?>
        <div class="card-box registrar-print-sheet" style="margin-bottom:1.1rem; border-left:5px solid #17663f;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                <div>
                    <h3 style="margin:0; color:#0f172a;"><?php echo htmlspecialchars((string)($selected_print_doc['doc_type'] ?? 'Document')); ?> — <span style="color:#17663f;"><?php echo htmlspecialchars((string)($selected_print_doc['doc_serial'] ?? '')); ?></span></h3>
                    <div style="font-size:0.82rem; color:#64748b;"><?php echo $is_am ? 'የተዘጋጀበት ቀን:' : 'Issued Date:'; ?> <?php echo htmlspecialchars((string)($selected_print_doc['created_at'] ?? '')); ?></div>
                </div>
                <div class="no-print" style="display:flex; gap:0.4rem;">
                    <button type="button" onclick="window.print();" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> <?php echo $is_am ? 'አሁን አትም (Print / PDF)' : 'Print / Save PDF'; ?></button>
                    <a href="<?php echo htmlspecialchars($reg_section_url('documents', [], '#registrar-documents-panel')); ?>" class="btn btn-outline btn-sm"><?php echo $is_am ? 'ዝጋ' : 'Close'; ?></a>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:0.65rem; font-size:0.9rem;">
                <div><strong><?php echo $is_am ? 'ሰልጣኝ:' : 'Trainee:'; ?></strong> <?php echo htmlspecialchars((string)($p_enr['student_name'] ?? ($is_am ? 'አጠቃላይ ቡድን' : 'Cohort Batch'))); ?></div>
                <div><strong><?php echo $is_am ? 'መታወቂያ:' : 'Student ID:'; ?></strong> <?php echo htmlspecialchars((string)($p_enr['student_code'] ?? '-')); ?></div>
                <div><strong><?php echo $is_am ? 'ፕሮግራም:' : 'Program:'; ?></strong> <?php echo htmlspecialchars((string)($p_enr['program_title'] ?? '-')); ?></div>
                <div><strong><?php echo $is_am ? 'ውጤት / መገኘት:' : 'Score / Attendance:'; ?></strong> <?php echo htmlspecialchars((string)($p_enr['assessment_score'] ?? '0')); ?>% / <?php echo htmlspecialchars((string)($p_enr['attendance_pct'] ?? '0')); ?>%</div>
            </div>
            <?php if (!empty($selected_print_doc['remarks'])): ?>
                <p style="margin-top:0.65rem; margin-bottom:0; color:#334155;"><strong><?php echo $is_am ? 'ማስታወሻ:' : 'Remarks:'; ?></strong> <?php echo htmlspecialchars((string)$selected_print_doc['remarks']); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card-box">
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:0.6rem; margin-bottom:0.75rem;">
                <h3 style="color:var(--primary-color); margin:0;"><?php echo $is_am ? 'የተዘጋጁ ሰነዶች ታሪክ' : 'Issued Documents'; ?> (<?php echo count($docs_list); ?>)</h3>
                <a href="<?php echo htmlspecialchars($export_doc_url); ?>" class="btn btn-outline btn-sm"><i class="bi bi-download"></i> <?php echo $is_am ? 'CSV አውርድ' : 'Download CSV'; ?></a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo $is_am ? 'መለያ ቁጥር' : 'Serial No'; ?></th>
                            <th><?php echo $is_am ? 'ዓይነት' : 'Type'; ?></th>
                            <th><?php echo $is_am ? 'ምዝገባ #' : 'Enrollment #'; ?></th>
                            <th><?php echo $is_am ? 'ማስታወሻ' : 'Remarks'; ?></th>
                            <th><?php echo $is_am ? 'ህትመት' : 'Print'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($docs_list)): ?>
                            <?php foreach ($docs_list as $d): $did = (int)($d['id'] ?? 0); ?>
                            <tr id="doc-row-<?php echo $did; ?>">
                                <td><strong><?php echo htmlspecialchars((string)($d['doc_serial'] ?? '')); ?></strong></td>
                                <td><?php echo htmlspecialchars((string)($d['doc_type'] ?? '')); ?></td>
                                <td><?php echo (int)($d['enrollment_id'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string)($d['remarks'] ?? '-')); ?></td>
                                <td><a href="<?php echo htmlspecialchars($reg_section_url('documents', ['print_doc' => $did], '#doc-row-' . $did)); ?>" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> <?php echo $is_am ? 'አትም' : 'Print'; ?></a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5"><?php echo $is_am ? 'ገና ምንም ሰነድ አልተዘጋጀም።' : 'No documents issued yet.'; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($active_section === 'maintenance'): ?>
        <div class="card-box" id="registrar-maintenance-panel">
            <h3 style="color:var(--primary-color); margin-bottom:0.75rem;"><?php echo $is_am ? 'የመረጃ ትክክለኛነት እና የስርዓት ሁኔታ' : 'Data Integrity & Schema Status'; ?></h3>
            <p style="color:#475569; margin-bottom:1rem;"><?php echo $is_am ? 'የ `enrollments`፣ `training_schedules`፣ እና `registrar_documents` አምዶችን ይፈትሻል እና መታወቂያ ያልተሰጣቸውን ምዝገባዎች ያሳያል።' : 'Checks core registrar tables and columns and reports enrollments without a generated student ID.'; ?></p>
            <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                <div class="alert alert-success" style="margin:0;"><?php echo $is_am ? 'ጠቅላላ ምዝገባዎች:' : 'Total Enrollments:'; ?> <strong><?php echo (int)$total_enr; ?></strong></div>
                <div class="alert alert-success" style="margin:0;"><?php echo $is_am ? 'መታወቂያ ያልተሰጣቸው:' : 'Missing Student ID:'; ?> <strong><?php echo (int)$missing_codes; ?></strong></div>
                <div class="alert alert-success" style="margin:0;"><?php echo $is_am ? 'የተለየ የተማሪ አምድ:' : 'Student Column:'; ?> <strong><?php echo htmlspecialchars((string)($enr_student_col ?? 'none')); ?></strong></div>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($reg_section_url('maintenance', [], '#registrar-maintenance-panel')); ?>">
                <input type="hidden" name="section" value="maintenance">
                <input type="hidden" name="action" value="run_schema_self_heal">
                <button type="submit" class="btn btn-primary"><i class="bi bi-wrench-adjustable"></i> <?php echo $is_am ? 'የዳታቤዝ ጥገና አከናውን' : 'Run Schema Self-Heal'; ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash) {
        const el = document.querySelector(window.location.hash);
        if (el) el.scrollIntoView({ behavior: 'auto', block: 'start' });
    }
});
</script>
<?php include $rel_root . 'includes/footer.php'; ?>