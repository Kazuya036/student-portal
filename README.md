# Student Record Management System

A PHP + MySQL student record system designed for local development with XAMPP. It includes login, role-based page access, dashboard statistics, student CRUD, enrollment history, course and subject management, grades, CSV reports, user management, and audit activity.

## Requirements
- XAMPP with PHP 8.1+ and MySQL/MariaDB
- A modern browser
- Internet connection for optional Google Fonts, Bootstrap Icons, and Chart.js CDN assets (the application itself uses local PHP/CSS/JS)

## Install on XAMPP
1. Extract the `student-record-system` folder into `C:\xampp\htdocs\`.
2. Open XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin`.
4. Choose **Import**, select `database/schema.sql`, and run the import. The script creates `student_record_system` and its tables.
5. Check `config/database.php`. Default XAMPP settings are host `localhost`, user `root`, and blank password. Update these values if your MySQL account differs.
6. Open `http://localhost/student-record-system/setup_admin.php`.
7. Create the first administrator. Use a unique username and a password of at least 10 characters.
8. Delete `setup_admin.php` from the project folder immediately after creating the account.
9. Sign in at `http://localhost/student-record-system/login.php`.

## First steps after login
1. Add courses in **Courses**.
2. Add subjects in **Subjects**.
3. Confirm the academic year in the `academic_years` table (the SQL file inserts 2026–2027 as an initial example).
4. Register students.
5. Create enrollments.
6. Record grades.

## Roles
- **Administrator:** all modules, user management, audit activity, courses, and subjects.
- **Registrar / Staff:** student records, enrollments, grades, reports.
- **Teacher:** grades and academic records. This starter app's teacher access is role-based; it does not yet implement per-teacher subject assignment enforcement.
- **Viewer:** read-only access to permitted student, enrollment, grades, and reports pages.

## Important limitations / production checklist
This is a runnable starter application, not a fully audited production school information system. Before using real student data:
- Enable HTTPS and configure secure session cookies for the deployment environment.
- Add backups, retention rules, and a privacy notice/consent process appropriate to your school and local data-protection obligations.
- Implement teacher-to-subject authorization, student profile photo uploads, PDF report generation, and more granular field permissions if needed.
- Review the grading scale and GPA/GWA policy with the school; this starter stores numeric grades from 0 to 100 and does not assume a universal pass/fail formula.
- Confirm that every role has the exact permissions required by the school.
- Keep PHP, MySQL, and dependencies updated and never expose the application directly to the public internet without proper hosting and security configuration.

## Troubleshooting
- **Database connection failed:** check that MySQL is running and database credentials match `config/database.php`.
- **Unknown database:** import `database/schema.sql`.
- **Cannot create administrator:** check that the SQL import succeeded and that no admin already exists. If an admin already exists, sign in instead.
- **Duplicate student ID / course code / subject code:** each must be unique.
- **Cannot delete course/subject/student:** related enrollment or grade records may protect historical data. Remove or update dependent records only if school policy permits.
