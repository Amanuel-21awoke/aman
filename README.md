# Injibara University Short-Term Training Hub (INU-STTH)

A role-based, bilingual (English / Amharic) web management system designed for **Injibara University's Short-Term Training Hub**.

## Setup Instructions (XAMPP / Apache + PHP + MySQL)
1. Copy the `injibara_training_hub` folder into your web server root (e.g., `htdocs/injibara_training_hub`).
2. Open **phpMyAdmin** or MySQL CLI and import `database/schema.sql`.
3. Verify database credentials in `backend/db_connect.php` (default DB: `injibara_training_hub`, user: `root`, password: ``).
4. Open `http://localhost/injibara_training_hub/index.php` in your browser.

## Roles & Dashboards Included
- **Hub Admin (`admin`)**: `dashboard/admin/index.php`, `users.php`, `programs.php`, `announcements.php`, `reports.php`
- **Training Coordinator (`coordinator`)**: `dashboard/coordinator/index.php`, `enrollments.php`, `sessions.php`
- **Lead Trainer (`trainer`)**: `dashboard/trainer/index.php`, `attendance.php`
- **Trainee (`trainee`)**: `dashboard/trainee/index.php`, `programs.php`, `certificates.php`
- **Finance Officer (`finance_officer`)**: `dashboard/finance/index.php`
- **QA & Impact Evaluator (`qa_evaluator`)**: `dashboard/qa/index.php`
- **Shared Modules**: `login.php`, `register.php`, `messages.php`, `profile.php`, `logout.php`
