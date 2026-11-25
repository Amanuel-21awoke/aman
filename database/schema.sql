-- ============================================================================
-- Injibara University Short-Term Training Hub (INU-STTH)
-- Complete Database Schema & Seed Data
-- Default password for seeded accounts: 123456
-- ============================================================================

CREATE DATABASE IF NOT EXISTS injibara_training_hub;
USE injibara_training_hub;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 1. Users Table (Multi-Actor RBAC)
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'coordinator', 'trainer', 'trainee', 'finance_officer', 'qa_evaluator', 'registrar', 'lab_technician', 'curriculum_developer', 'sponsor_liaison', 'certificate_officer', 'events_coordinator', 'department_head', 'monitoring_officer', 'support_staff', 'alumni_coordinator') DEFAULT 'trainee',
    department VARCHAR(120) DEFAULT 'Short-Term Training Hub',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. User Profiles
DROP TABLE IF EXISTS profiles;
CREATE TABLE profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(25),
    address TEXT,
    bio TEXT,
    photo_url VARCHAR(255) DEFAULT 'assets/default_avatar.png',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_profile (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Training Programs / Courses
DROP TABLE IF EXISTS training_programs;
CREATE TABLE training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    title_en VARCHAR(180) NOT NULL,
    title_am VARCHAR(180) NOT NULL,
    category ENUM('ICT & Digital Skills', 'Leadership & Management', 'Pedagogy & STEM', 'Entrepreneurship & TVET', 'Health & Community') DEFAULT 'ICT & Digital Skills',
    duration_days INT DEFAULT 10,
    capacity INT DEFAULT 35,
    fee_birr DECIMAL(10,2) DEFAULT 0.00,
    trainer_id INT NULL,
    status ENUM('Open', 'Ongoing', 'Completed', 'Closed') DEFAULT 'Open',
    description_en TEXT,
    description_am TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Training Cohorts / Batches & Schedules
DROP TABLE IF EXISTS training_sessions;
CREATE TABLE training_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    batch_code VARCHAR(40) NOT NULL,
    venue VARCHAR(140) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    daily_time VARCHAR(60) DEFAULT '08:30 AM - 04:30 PM',
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    notes TEXT,
    FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Trainee Enrollments & Applications
DROP TABLE IF EXISTS enrollments;
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainee_id INT NOT NULL,
    program_id INT NOT NULL,
    session_id INT NULL,
    organization VARCHAR(150),
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Waitlist', 'Rejected', 'Completed') DEFAULT 'Pending',
    payment_status ENUM('Unpaid', 'Pending Verification', 'Paid', 'Sponsored') DEFAULT 'Pending Verification',
    attendance_rate DECIMAL(5,2) DEFAULT 0.00,
    assessment_score DECIMAL(5,2) NULL,
    certificate_issued TINYINT(1) DEFAULT 0,
    certificate_code VARCHAR(60) NULL,
    coordinator_notes TEXT,
    FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE SET NULL,
    UNIQUE KEY unique_trainee_program (trainee_id, program_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Daily Attendance Records
DROP TABLE IF EXISTS attendance_records;
CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    session_id INT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Excused') DEFAULT 'Present',
    recorded_by INT NULL,
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_daily_attendance (enrollment_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Financial Transactions & Invoices
DROP TABLE IF EXISTS financial_transactions;
CREATE TABLE financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    receipt_no VARCHAR(60) NOT NULL UNIQUE,
    amount_birr DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Bank Transfer (CBE)', 'Telebirr', 'University Finance', 'Sponsor Voucher') DEFAULT 'Bank Transfer (CBE)',
    reference_code VARCHAR(100),
    status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Quality Assurance & Post-Training Evaluations
DROP TABLE IF EXISTS evaluations;
CREATE TABLE evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    evaluator_role ENUM('trainee', 'qa_evaluator') DEFAULT 'trainee',
    content_rating TINYINT NOT NULL DEFAULT 5,
    trainer_rating TINYINT NOT NULL DEFAULT 5,
    facility_rating TINYINT NOT NULL DEFAULT 5,
    feedback_text TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Announcements & Notices
DROP TABLE IF EXISTS announcements;
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title_en VARCHAR(200) NOT NULL,
    title_am VARCHAR(200) NOT NULL,
    body_en TEXT NOT NULL,
    body_am TEXT NOT NULL,
    target_role VARCHAR(30) DEFAULT 'all',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Internal Messages
DROP TABLE IF EXISTS messages;
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA (Password for all users below: 123456)
-- Hash for '123456': $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================================

INSERT INTO users (name, email, password_hash, role, department, status) VALUES
('Dr. Abebe Kebede', 'admin@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Hub Director & System Admin', 'active'),
('Tigist Alemu', 'coordinator@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coordinator', 'Training Operations & Scheduling', 'active'),
('Dawit Mekonnen', 'trainer@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'trainer', 'Computing & Digital Innovation', 'active'),
('Selamawit Tadesse', 'trainee@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'trainee', 'Awi Zone Education Office', 'active'),
('Yohannes Girma', 'finance@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'finance_officer', 'Training Finance & Revenue', 'active'),
('Prof. Marta Bekele', 'qa@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'qa_evaluator', 'Quality Assurance & Impact Audit', 'active'),

('Mulugeta Bekele', 'registrar@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'registrar', 'Academic & Training Records', 'active'),
('Bereket Tadesse', 'labtech@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lab_technician', 'ICT & Training Labs', 'active'),
('Dr. Hirut Assefa', 'curriculum@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'curriculum_developer', 'Syllabus & Module Design', 'active'),
('Samson Alemu', 'sponsor@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sponsor_liaison', 'Partnerships & Institutional Sponsorship', 'active'),
('Meron Girma', 'certificates@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'certificate_officer', 'Certification & Verification Desk', 'active'),
('Kibrom Gebre', 'events@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'events_coordinator', 'Workshops, Venues & Logistics', 'active'),
('Dr. Demeke Baaaalew', 'dean@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dean', 'college dean', 'active'),
('Dr. Yohannes Worku', 'depthead@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_head', 'College & Department Oversight', 'active'),
('Rahel Desta', 'monitoring@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'monitoring_officer', 'M&E and Cohort Tracking', 'active'),
('Ermias Kebede', 'support@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'support_staff', 'Trainee Helpdesk & Operations', 'active'),
('Aster Mengistu', 'alumni@inu.edu.et', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni_coordinator', 'Trainee Alumni & Career Network', 'active');


INSERT INTO profiles (user_id, phone, address, bio) VALUES
(1, '+251911223301', 'Injibara University Main Campus, Admin Block', 'Director of Short-Term Training & Community Engagement.'),
(2, '+251911223302', 'Injibara University ICT & Training Center', 'Coordinates short-term cohorts, classrooms, and trainee schedules.'),
(3, '+251911223303', 'College of Computing, Injibara University', 'Lead facilitator for practical ICT, data literacy, and digital pedagogy.'),
(4, '+251911223304', 'Injibara Town, Awi Zone', 'Enrolled participant in short-term professional training.'),
(5, '+251911223305', 'Finance & Budget Directorate, INU', 'Manages training registration fees, receipts, and sponsor settlements.'),
(6, '+251911223306', 'Academic Quality Assurance Office, INU', 'Evaluates curriculum delivery, lab readiness, and post-training outcomes.');

INSERT INTO training_programs (code, title_en, title_am, category, duration_days, capacity, fee_birr, trainer_id, status, description_en, description_am) VALUES
('INU-ST-101', 'Practical Data Analysis & Excel/Python', 'ተግባራዊ የዳታ ትንተና እና ኤክሴል/ፓይቶን', 'ICT & Digital Skills', 10, 35, 2500.00, 3, 'Open', 'Hands-on training in data cleaning, visualization, and reporting for civil servants, researchers, and professionals.', 'ለባለሙያዎች፣ ለተመራማሪዎች እና ለሰራተኞች የዳታ ትንተና፣ ሪፖርት እና ቪዥዋላይዜሽን ተግባራዊ ስልጠና።'),
('INU-ST-102', 'Public Leadership & Project Management', 'የህዝብ አመራር እና የፕሮጀክት አስተዳደር', 'Leadership & Management', 7, 40, 2000.00, 3, 'Open', 'Practical frameworks for planning, budgeting, team leadership, and results-based monitoring in institutions.', 'ለተቋማት የእቅድ ዝግጅት፣ የበጀት አጠቃቀም፣ የቡድን አመራር እና የፕሮጀክት ክትትል ስልጠና።'),
('INU-ST-103', 'Modern Pedagogy & Digital Assessment', 'ዘመናዊ የማስተማር ዘዴ እና ዲጂታል ምዘና', 'Pedagogy & STEM', 5, 45, 1500.00, 3, 'Ongoing', 'Interactive teaching methods, rubric design, and digital assessment tools for educators and instructors.', 'ለመምህራን እና ለአሰልጣኞች አሳታፊ የማስተማር ስልቶች እና ዲጂታል የምዘና መሳሪያዎች ስልጠና።'),
('INU-ST-104', 'Agri-Business & TVET Entrepreneurship', 'የግብርና ንግድ እና የቴክኒክ ሥራ ፈጠራ', 'Entrepreneurship & TVET', 8, 30, 1800.00, 3, 'Open', 'Business canvas modeling, cooperative management, and value-chain skills for youth and local enterprises.', 'ለወጣቶች እና ለአካባቢው የንግድ ተቋማት የቢዝነስ እቅድ፣ የህብረት ስራ እና የገበያ ትስስር ስልጠና።');

INSERT INTO training_sessions (program_id, batch_code, venue, start_date, end_date, daily_time, status, notes) VALUES
(1, 'BATCH-2026-01', 'INU ICT Lab 02', '2026-12-01', '2026-12-12', '08:30 AM - 04:00 PM', 'Scheduled', 'Bring laptop or use lab workstations.'),
(2, 'BATCH-2026-02', 'Senate Hall B', '2026-12-07', '2026-12-15', '09:00 AM - 04:30 PM', 'Scheduled', 'Case-study group workshop included.'),
(3, 'BATCH-2026-03', 'Education Block Room 105', '2026-11-16', '2026-11-21', '08:30 AM - 03:30 PM', 'In Progress', 'Daily micro-teaching practicum active.');

INSERT INTO enrollments (trainee_id, program_id, session_id, organization, status, payment_status, attendance_rate, assessment_score, certificate_issued, certificate_code, coordinator_notes) VALUES
(4, 1, 1, 'Awi Zone Education Office', 'Approved', 'Paid', 95.00, 88.50, 0, NULL, 'Confirmed seat in Batch 01.'),
(4, 3, 3, 'Awi Zone Education Office', 'Approved', 'Sponsored', 92.00, 91.00, 1, 'INU-CERT-2026-0089', 'Completed practicum and passed assessment.');

INSERT INTO financial_transactions (enrollment_id, receipt_no, amount_birr, payment_method, reference_code, status, verified_by, verified_at) VALUES
(1, 'RCP-INU-2026-1001', 2500.00, 'Bank Transfer (CBE)', 'FT26324CBE881', 'Verified', 5, NOW());

INSERT INTO evaluations (program_id, evaluator_id, evaluator_role, content_rating, trainer_rating, facility_rating, feedback_text) VALUES
(3, 4, 'trainee', 5, 5, 4, 'Very practical sessions on digital assessment and classroom engagement.'),
(1, 6, 'qa_evaluator', 5, 4, 5, 'Lab equipment and syllabus alignment meet university short-term training standards.');

INSERT INTO announcements (title_en, title_am, body_en, body_am, target_role, created_by) VALUES
('December 2026 Short-Term Training Call is Now Open', 'የታኅሣሥ 2026 የአጭር ጊዜ ስልጠና ጥሪ ክፍት ሆኗል', 'Applications are open for Practical Data Analysis, Public Leadership, and Agri-Business cohorts at Injibara University.', 'በእንጅባራ ዩኒቨርሲቲ ለዳታ ትንተና፣ ለህዝብ አመራር እና ለሥራ ፈጠራ የአጭር ጊዜ ስልጠናዎች ምዝገባ ተጀምሯል።', 'all', 1),
('Attendance & Certificate Verification Policy', 'የተሳትፎ እና የሰርተፍኬት ማረጋገጫ መመሪያ', 'Trainees must maintain at least 85% attendance and complete practical assignments to receive an INU hub certificate.', 'ሰልጣኞች የእንጅባራ ዩኒቨርሲቲ የስልጠና ሰርተፍኬት ለማግኘት ቢያንስ 85% የተሳትፎ እና የተግባር ምዘና ማጠናቀቅ አለባቸው።', 'trainee', 2);

INSERT INTO messages (sender_id, receiver_id, subject, body, is_read) VALUES
(2, 4, 'Welcome to INU Short-Term Training Hub', 'Hello Selamawit, your enrollment for Practical Data Analysis (Batch-2026-01) is confirmed. Please arrive 20 minutes early on day one.', 0),
(5, 4, 'Payment Receipt Verified', 'Your training fee receipt RCP-INU-2026-1001 has been verified by the Finance Office.', 1);
-- 1. users.role ENUM ውስጥ college_dean መኖሩን ማረጋገጥ / ማከል
ALTER TABLE users
MODIFY COLUMN role VARCHAR(64) NOT NULL DEFAULT 'trainee';

-- 2. የ College Dean መለያ role እና status ማስተካከል (ኢሜይሉን በትክክለኛው ቀይር)
UPDATE users
SET role = 'college_dean', status = 'active'
WHERE email = 'dean@inu.edu.et';

-- 3. dean_endorsements ሠንጠረዥን በንጽህና መፍጠር
CREATE TABLE IF NOT EXISTS dean_endorsements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    decision ENUM('Endorsed', 'Revision Requested', 'Deferred') NOT NULL DEFAULT 'Endorsed',
    dean_remarks TEXT NULL,
    reviewed_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_program_endorsement (program_id),
    INDEX idx_de_decision (decision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
