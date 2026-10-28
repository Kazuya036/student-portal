<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$role = $user['role'];
$page = preg_replace('/[^a-z]/', '', (string)($_GET['page'] ?? 'dashboard'));
$allowedPages = ['dashboard', 'students', 'studentform', 'studentview', 'courses', 'subjects', 'enrollment', 'grades', 'reports', 'users', 'activity'];
if (!in_array($page, $allowedPages, true)) $page = 'dashboard';

function can_manage(): bool
{
    return in_array(current_user()['role'], ['admin', 'staff'], true);
}
function page_title(string $page): string
{
    return ['dashboard' => 'Dashboard', 'students' => 'Students', 'studentform' => 'Student Registration', 'studentview' => 'Student Profile', 'courses' => 'Courses & Programs', 'subjects' => 'Subjects', 'enrollment' => 'Enrollment', 'grades' => 'Grades & Academic Records', 'reports' => 'Reports', 'users' => 'User Management', 'activity' => 'Audit Activity'][$page] ?? 'Dashboard';
}
function field(string $label, string $name, string $type = 'text', $value = '', array $opts = []): void
{
    echo '<label>' . e($label);
    if ($type === 'select') {
        echo '<select name="' . e($name) . '" ' . (!empty($opts['required']) ? 'required' : '') . '>';
        foreach (($opts['options'] ?? []) as $k => $v) echo '<option value="' . e($k) . '" ' . ((string)$value === (string)$k ? 'selected' : '') . '>' . e($v) . '</option>';
        echo '</select>';
    } else {
        echo '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" ' . (!empty($opts['required']) ? 'required' : '') . ' ' . (!empty($opts['maxlength']) ? 'maxlength="' . (int)$opts['maxlength'] . '"' : '') . '>';
    }
    echo '</label>';
}
function options_from(PDO $pdo, string $table, string $valueCol, string $labelCol): array
{
    $rows = $pdo->query("SELECT `$valueCol`, `$labelCol` FROM `$table` ORDER BY `$labelCol`")->fetchAll();
    $out = ['' => '— Select —'];
    foreach ($rows as $r) $out[$r[$valueCol]] = $r[$labelCol];
    return $out;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_student') {
            require_roles(['admin', 'staff']);
            $id = (int)($_POST['id'] ?? 0);
            $studentNo = trim((string)($_POST['student_no'] ?? ''));
            $first = trim((string)($_POST['first_name'] ?? ''));
            $last = trim((string)($_POST['last_name'] ?? ''));
            if ($studentNo === '' || $first === '' || $last === '') throw new RuntimeException('Student ID, first name, and last name are required.');
            $data = [
                $studentNo,
                $first,
                trim((string)($_POST['middle_name'] ?? '')) ?: null,
                $last,
                trim((string)($_POST['suffix'] ?? '')) ?: null,
                (string)($_POST['sex'] ?? 'Prefer not to say'),
                valid_date_or_null($_POST['date_of_birth'] ?? ''),
                trim((string)($_POST['place_of_birth'] ?? '')) ?: null,
                trim((string)($_POST['civil_status'] ?? '')) ?: null,
                trim((string)($_POST['nationality'] ?? 'Filipino')) ?: 'Filipino',
                trim((string)($_POST['religion'] ?? '')) ?: null,
                trim((string)($_POST['email'] ?? '')) ?: null,
                trim((string)($_POST['phone'] ?? '')) ?: null,
                trim((string)($_POST['current_address'] ?? '')) ?: null,
                trim((string)($_POST['permanent_address'] ?? '')) ?: null,
                trim((string)($_POST['guardian_name'] ?? '')) ?: null,
                trim((string)($_POST['guardian_relationship'] ?? '')) ?: null,
                trim((string)($_POST['guardian_phone'] ?? '')) ?: null,
                trim((string)($_POST['guardian_address'] ?? '')) ?: null
            ];
            if ($data[11] && !filter_var($data[11], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
            if ($id > 0) {
                $data[] = $id;
                $stmt = $pdo->prepare('UPDATE students SET student_no=?,first_name=?,middle_name=?,last_name=?,suffix=?,sex=?,date_of_birth=?,place_of_birth=?,civil_status=?,nationality=?,religion=?,email=?,phone=?,current_address=?,permanent_address=?,guardian_name=?,guardian_relationship=?,guardian_phone=?,guardian_address=? WHERE id=?');
                $stmt->execute($data);
                audit($pdo, 'UPDATE_STUDENT', "Updated student record #$id");
                flash('Student record updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO students (student_no,first_name,middle_name,last_name,suffix,sex,date_of_birth,place_of_birth,civil_status,nationality,religion,email,phone,current_address,permanent_address,guardian_name,guardian_relationship,guardian_phone,guardian_address) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute($data);
                audit($pdo, 'CREATE_STUDENT', 'Created student ' . $studentNo);
                flash('Student registered successfully.');
            }
            redirect('app.php?page=students');
        } elseif ($action === 'delete_student') {
            require_roles(['admin']);
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM students WHERE id=?');
            $stmt->execute([$id]);
            audit($pdo, 'DELETE_STUDENT', "Deleted student record #$id");
            flash('Student deleted. Related academic records are retained only where database rules permit.');
            redirect('app.php?page=students');
        } elseif ($action === 'save_course') {
            require_roles(['admin']);
            $id = (int)($_POST['id'] ?? 0);
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            if (!$code || !$name) throw new RuntimeException('Course code and name are required.');
            if ($id) {
                $s = $pdo->prepare('UPDATE courses SET code=?,name=?,description=? WHERE id=?');
                $s->execute([$code, $name, $description, $id]);
            } else {
                $s = $pdo->prepare('INSERT INTO courses(code,name,description) VALUES(?,?,?)');
                $s->execute([$code, $name, $description]);
            }
            audit($pdo, 'SAVE_COURSE', $code . ' ' . $name);
            flash('Course saved.');
            redirect('app.php?page=courses');
        } elseif ($action === 'delete_course') {
            require_roles(['admin']);
            $s = $pdo->prepare('DELETE FROM courses WHERE id=?');
            $s->execute([(int)$_POST['id']]);
            flash('Course deleted.');
            redirect('app.php?page=courses');
        } elseif ($action === 'save_subject') {
            require_roles(['admin']);
            $id = (int)($_POST['id'] ?? 0);
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $units = (float)($_POST['units'] ?? 0);
            $course = (int)($_POST['course_id'] ?? 0);
            if (!$code || !$name || $units <= 0) throw new RuntimeException('Subject code, name, and positive units are required.');
            if ($id) {
                $s = $pdo->prepare('UPDATE subjects SET code=?,name=?,units=?,course_id=NULLIF(?,0) WHERE id=?');
                $s->execute([$code, $name, $units, $course, $id]);
            } else {
                $s = $pdo->prepare('INSERT INTO subjects(code,name,units,course_id) VALUES(?,?,?,NULLIF(?,0))');
                $s->execute([$code, $name, $units, $course]);
            }
            audit($pdo, 'SAVE_SUBJECT', $code . ' ' . $name);
            flash('Subject saved.');
            redirect('app.php?page=subjects');
        } elseif ($action === 'delete_subject') {
            require_roles(['admin']);
            $s = $pdo->prepare('DELETE FROM subjects WHERE id=?');
            $s->execute([(int)$_POST['id']]);
            flash('Subject deleted.');
            redirect('app.php?page=subjects');
        } elseif ($action === 'save_enrollment') {
            require_roles(['admin', 'staff']);
            $student = (int)($_POST['student_id'] ?? 0);
            $course = (int)($_POST['course_id'] ?? 0);
            $year = (int)($_POST['academic_year_id'] ?? 0);
            $semester = (string)($_POST['semester'] ?? '1st');
            $level = (int)($_POST['year_level'] ?? 1);
            $section = trim((string)($_POST['section_name'] ?? ''));
            $status = (string)($_POST['status'] ?? 'Pending');
            if (!$student || !$course || !$year) throw new RuntimeException('Student, course, and academic year are required.');
            $s = $pdo->prepare('INSERT INTO enrollments(student_id,course_id,academic_year_id,semester,year_level,section_name,status) VALUES(?,?,?,?,?,?,?)');
            $s->execute([$student, $course, $year, $semester, $level, $section ?: null, $status]);
            audit($pdo, 'CREATE_ENROLLMENT', "Student #$student");
            flash('Enrollment created.');
            redirect('app.php?page=enrollment');
        } elseif ($action === 'update_enrollment_status') {
            require_roles(['admin', 'staff']);
            $status = (string)($_POST['status'] ?? 'Pending');
            if (!in_array($status, ['Pending', 'Enrolled', 'Inactive', 'Graduated'], true)) throw new RuntimeException('Invalid enrollment status.');
            $s = $pdo->prepare('UPDATE enrollments SET status=? WHERE id=?');
            $s->execute([$status, (int)$_POST['id']]);
            audit($pdo, 'UPDATE_ENROLLMENT', 'Enrollment status updated');
            flash('Enrollment status updated.');
            redirect('app.php?page=enrollment');
        } elseif ($action === 'save_grade') {
            require_roles(['admin', 'staff', 'teacher']);
            $enrollment = (int)($_POST['enrollment_id'] ?? 0);
            $subject = (int)($_POST['subject_id'] ?? 0);
            $grade = trim((string)($_POST['grade_value'] ?? ''));
            $remarks = trim((string)($_POST['remarks'] ?? ''));
            if (!$enrollment || !$subject || $grade === '') throw new RuntimeException('Enrollment, subject, and grade are required.');
            if (!is_numeric($grade) || (float)$grade < 0 || (float)$grade > 100) throw new RuntimeException('Grade must be a number from 0 to 100.');
            $s = $pdo->prepare('INSERT INTO grades(enrollment_id,subject_id,grade_value,remarks,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE grade_value=VALUES(grade_value),remarks=VALUES(remarks),updated_by=VALUES(updated_by)');
            $s->execute([$enrollment, $subject, (float)$grade, $remarks ?: null, $user['id']]);
            audit($pdo, 'SAVE_GRADE', "Enrollment #$enrollment subject #$subject");
            flash('Grade saved.');
            redirect('app.php?page=grades');
        } elseif ($action === 'save_user') {
            require_roles(['admin']);
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['full_name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $roleNew = (string)($_POST['role'] ?? 'staff');
            $password = (string)($_POST['password'] ?? '');
            $active = isset($_POST['is_active']) ? 1 : 0;
            if (!$name || !preg_match('/^[a-zA-Z0-9_.-]{4,40}$/', $username) || !in_array($roleNew, ['admin', 'staff', 'teacher', 'viewer'], true)) throw new RuntimeException('Check the name, username, and role.');
            if ($id) {
                if ($password !== '') {
                    if (strlen($password) < 10) throw new RuntimeException('Password must be at least 10 characters.');
                    $s = $pdo->prepare('UPDATE users SET full_name=?,username=?,role=?,is_active=?,password_hash=? WHERE id=?');
                    $s->execute([$name, $username, $roleNew, $active, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $s = $pdo->prepare('UPDATE users SET full_name=?,username=?,role=?,is_active=? WHERE id=?');
                    $s->execute([$name, $username, $roleNew, $active, $id]);
                }
            } else {
                if (strlen($password) < 10) throw new RuntimeException('New user password must be at least 10 characters.');
                $s = $pdo->prepare('INSERT INTO users(full_name,username,role,is_active,password_hash) VALUES(?,?,?,?,?)');
                $s->execute([$name, $username, $roleNew, $active, password_hash($password, PASSWORD_DEFAULT)]);
            }
            audit($pdo, 'SAVE_USER', $username);
            flash('User saved.');
            redirect('app.php?page=users');
        } elseif ($action === 'delete_user') {
            require_roles(['admin']);
            $id = (int)$_POST['id'];
            if ($id === (int)$user['id']) throw new RuntimeException('You cannot delete your own account while signed in.');
            $s = $pdo->prepare("DELETE FROM users WHERE id=? AND role <> 'admin'");
            $s->execute([$id]);
            audit($pdo, 'DELETE_USER', "User #$id");
            flash('User deleted if the account was eligible.');
            redirect('app.php?page=users');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $ex) {
        if ($ex instanceof PDOException && (string)$ex->getCode() === '23000') $error = 'A duplicate value or related record prevented this operation. Check IDs, codes, and existing relationships.';
        else $error = $ex instanceof RuntimeException ? $ex->getMessage() : 'The operation could not be completed. Check the entered data and database relationships.';
    }
}

$title = page_title($page);
$flash = take_flash();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Student Records</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar"><a class="brand" href="app.php"><span class="brand-mark small-mark">SR</span><span><b>Student Records</b><small>Management System</small></span></a>
            <nav class="nav-links">
                <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="app.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
                <a class="<?= in_array($page, ['students', 'studentform', 'studentview']) ? 'active' : '' ?>" href="app.php?page=students"><i class="bi bi-people"></i> Students</a>
                <a class="<?= $page === 'enrollment' ? 'active' : '' ?>" href="app.php?page=enrollment"><i class="bi bi-journal-check"></i> Enrollment</a>
                <a class="<?= $page === 'grades' ? 'active' : '' ?>" href="app.php?page=grades"><i class="bi bi-mortarboard"></i> Grades & Records</a>
                <?php if ($role === 'admin'): ?>
                    <a class="<?= $page === 'courses' ? 'active' : '' ?>" href="app.php?page=courses"><i class="bi bi-building"></i> Courses</a>
                    <a class="<?= $page === 'subjects' ? 'active' : '' ?>" href="app.php?page=subjects"><i class="bi bi-journal-bookmark"></i> Subjects</a>
                <?php endif; ?>
                <a class="<?= $page === 'reports' ? 'active' : '' ?>" href="app.php?page=reports"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
                <?php if ($role === 'admin'): ?><a class="<?= $page === 'users' ? 'active' : '' ?>" href="app.php?page=users"><i class="bi bi-person-gear"></i> User Management</a><a class="<?= $page === 'activity' ? 'active' : '' ?>" href="app.php?page=activity"><i class="bi bi-clock-history"></i> Audit Activity</a><?php endif; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
                <div class="user-mini"><b><?= e($user['full_name']) ?></b><small><?= e(ucfirst($role)) ?></small></div><a class="logout-icon" href="logout.php" title="Sign out"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </aside>
        <main class="main-area">
            <header class="topbar"><button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
                <div>
                    <p class="eyebrow">SCHOOL ADMINISTRATION</p>
                    <h1><?= e($title) ?></h1>
                </div>
                <div class="topbar-right"><span class="today"><?= date('D, M j, Y') ?></span>
                    <div class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
                </div>
            </header>
            <section class="content">
                <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

                <?php if ($page === 'dashboard'):
                    $total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
                    $enrolled = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE status='Enrolled'")->fetchColumn();
                    $courses = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
                    $pending = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status='Pending'")->fetchColumn();
                    $sexRows = $pdo->query('SELECT sex,COUNT(*) total FROM students GROUP BY sex')->fetchAll();

                    $courseRows = $pdo->query("
    SELECT c.name, COUNT(DISTINCT e.student_id) AS total
    FROM courses c
    LEFT JOIN enrollments e
        ON e.course_id = c.id
        AND e.status = 'Enrolled'
    GROUP BY c.id, c.name
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();

                    $recent = $pdo->query('SELECT * FROM students ORDER BY created_at DESC LIMIT 6')->fetchAll();
                ?>
                    <div class="welcome-row">
                        <div>
                            <h2>Good day, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h2>
                            <p class="muted">Here’s what’s happening with your student records.</p>
                        </div><a class="btn primary" href="app.php?page=studentform"><i class="bi bi-plus-lg"></i> Add student</a>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon blue"><i class="bi bi-people"></i></div><span>Total students</span><strong><?= number_format($total) ?></strong><small>All registered records</small>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon green"><i class="bi bi-person-check"></i></div><span>Currently enrolled</span><strong><?= number_format($enrolled) ?></strong><small>Unique active students</small>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon violet"><i class="bi bi-building"></i></div><span>Programs</span><strong><?= number_format($courses) ?></strong><small>Courses available</small>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div><span>Pending enrollments</span><strong><?= number_format($pending) ?></strong><small>Awaiting processing</small>
                        </div>
                    </div>
                    <div class="dashboard-grid">
                        <div class="panel">
                            <div class="panel-heading">
                                <div>
                                    <h3>Enrolled students by course</h3>
                                    <p class="muted">Current enrollment counts</p>
                                </div>
                            </div>
                            <div class="chart-wrap"><canvas id="courseChart"></canvas></div>
                        </div>
                        <div class="panel">
                            <div class="panel-heading">
                                <div>
                                    <h3>Student population</h3>
                                    <p class="muted">By sex recorded in profiles</p>
                                </div>
                            </div>
                            <div class="chart-wrap small-chart"><canvas id="sexChart"></canvas></div>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Recently registered</h3>
                                <p class="muted">Latest student records</p>
                            </div><a href="app.php?page=students" class="text-link">View all <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <?php if (!$recent): ?><div class="empty-state"><i class="bi bi-person-plus"></i>
                                <h3>No students yet</h3>
                                <p>Add your first student to begin building your records.</p><a class="btn primary" href="app.php?page=studentform">Register student</a>
                            </div><?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Student name</th>
                                            <th>Contact</th>
                                            <th>Date added</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($recent as $s): ?><tr>
                                                <td><span class="id-tag"><?= e($s['student_no']) ?></span></td>
                                                <td><b><?= e(student_name($s)) ?></b><small class="table-sub"><?= e($s['email'] ?: 'No email added') ?></small></td>
                                                <td><?= e($s['phone'] ?: '—') ?></td>
                                                <td><?= e(date('M j, Y', strtotime($s['created_at']))) ?></td>
                                                <td><a class="icon-btn" href="app.php?page=studentview&id=<?= (int)$s['id'] ?>" title="View"><i class="bi bi-arrow-up-right"></i></a></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div><?php endif; ?>
                    </div>
                    <script>
                        new Chart(document.getElementById('courseChart'), {
                            type: 'bar',
                            data: {
                                labels: <?= json_encode(array_column($courseRows, 'name')) ?>,
                                datasets: [{
                                    label: 'Enrolled students',
                                    data: <?= json_encode(array_map('intval', array_column($courseRows, 'total'))) ?>,
                                    backgroundColor: '#4776e6',
                                    borderRadius: 7,
                                    maxBarThickness: 34
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        }
                                    }
                                }
                            }
                        });
                        new Chart(document.getElementById('sexChart'), {
                            type: 'doughnut',
                            data: {
                                labels: <?= json_encode(array_column($sexRows, 'sex')) ?>,
                                datasets: [{
                                    data: <?= json_encode(array_map('intval', array_column($sexRows, 'total'))) ?>,
                                    backgroundColor: ['#4776e6', '#36b37e', '#f3a847', '#9b8afb'],
                                    borderWidth: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }
                        });
                    </script>

                <?php elseif ($page === 'students'):
                    require_login();
                    $q = trim((string)($_GET['q'] ?? ''));
                    $courseFilter = (int)($_GET['course_id'] ?? 0);
                    $sexFilter = (string)($_GET['sex'] ?? '');
                    $limit = 15;
                    $p = max(1, (int)($_GET['p'] ?? 1));
                    $offset = ($p - 1) * $limit;
                    $where = [];
                    $params = [];
                    if ($q !== '') {
                        $where[] = '(s.student_no LIKE ? OR s.first_name LIKE ? OR s.middle_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)';
                        for ($i = 0; $i < 5; $i++) $params[] = '%' . $q . '%';
                    }
                    if ($sexFilter !== '') {
                        $where[] = 's.sex=?';
                        $params[] = $sexFilter;
                    }
                    if ($courseFilter) {
                        $where[] = 'EXISTS (SELECT 1 FROM enrollments ee WHERE ee.student_id=s.id AND ee.course_id=?)';
                        $params[] = $courseFilter;
                    }
                    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                    $cnt = $pdo->prepare('SELECT COUNT(*) FROM students s' . $w);
                    $cnt->execute($params);
                    $totalRows = (int)$cnt->fetchColumn();
                    $stmt = $pdo->prepare('SELECT s.*, (SELECT c.name FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE en.student_id=s.id ORDER BY en.created_at DESC LIMIT 1) course_name, (SELECT en.status FROM enrollments en WHERE en.student_id=s.id ORDER BY en.created_at DESC LIMIT 1) enrollment_status FROM students s' . $w . ' ORDER BY s.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
                    $stmt->execute($params);
                    $students = $stmt->fetchAll();
                ?>
                    <div class="toolbar">
                        <form class="filter-form" method="get"><input type="hidden" name="page" value="students">
                            <div class="search-box"><i class="bi bi-search"></i><input name="q" value="<?= e($q) ?>" placeholder="Search name, ID, or email..."></div><select name="sex">
                                <option value="">All sex</option><?php foreach (['Male', 'Female', 'Prefer not to say'] as $sx): ?><option <?= $sexFilter === $sx ? 'selected' : '' ?>><?= e($sx) ?></option><?php endforeach; ?>
                            </select><select name="course_id">
                                <option value="0">All courses</option><?php foreach ($pdo->query('SELECT id,name FROM courses ORDER BY name') as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                            </select><button class="btn secondary">Filter</button>
                        </form>
                        <div class="toolbar-actions"><?php if (can_manage()): ?><a class="btn primary" href="app.php?page=studentform"><i class="bi bi-plus-lg"></i> Add student</a><?php endif; ?><a class="btn secondary" href="app.php?page=reports&export=students"><i class="bi bi-download"></i> Export CSV</a></div>
                    </div>
                    <div class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Student directory</h3>
                                <p class="muted"><?= number_format($totalRows) ?> record(s) found</p>
                            </div>
                        </div>
                        <?php if (!$students): ?><div class="empty-state"><i class="bi bi-search"></i>
                                <h3>No students found</h3>
                                <p>Try changing your search or filters.</p>
                            </div><?php else: ?><div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Student</th>
                                            <th>Sex</th>
                                            <th>Contact</th>
                                            <th>Course</th>
                                            <th>Enrollment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($students as $s): ?><tr>
                                                <td><span class="id-tag"><?= e($s['student_no']) ?></span></td>
                                                <td>
                                                    <div class="student-cell">
                                                        <div class="avatar soft"><?= e(strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1))) ?></div>
                                                        <div><b><?= e(student_name($s)) ?></b><small class="table-sub"><?= e($s['email'] ?: 'No email') ?></small></div>
                                                    </div>
                                                </td>
                                                <td><?= e($s['sex']) ?></td>
                                                <td><?= e($s['phone'] ?: '—') ?></td>
                                                <td><?= e($s['course_name'] ?: '—') ?></td>
                                                <td><span class="status <?= strtolower(str_replace(' ', '-', $s['enrollment_status'] ?? 'Not enrolled')) ?>"><?= e($s['enrollment_status'] ?: 'Not enrolled') ?></span></td>
                                                <td>
                                                    <div class="row-actions"><a class="icon-btn" href="app.php?page=studentview&id=<?= (int)$s['id'] ?>" title="View"><i class="bi bi-eye"></i></a><?php if (can_manage()): ?><a class="icon-btn" href="app.php?page=studentform&id=<?= (int)$s['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?><?php if ($role === 'admin'): ?><form method="post" onsubmit="return confirm('Delete this student record? This may be blocked if academic records depend on it.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_student"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="icon-btn danger-text" title="Delete"><i class="bi bi-trash"></i></button></form><?php endif; ?></div>
                                                </td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                            <div class="pagination"><span>Page <?= $p ?> of <?= max(1, (int)ceil($totalRows / $limit)) ?></span>
                                <div><?php if ($p > 1): ?><a class="btn secondary small-btn" href="<?= e('app.php?' . http_build_query(array_merge($_GET, ['p' => $p - 1]))) ?>">Previous</a><?php endif; ?><?php if ($p < (int)ceil($totalRows / $limit)): ?><a class="btn secondary small-btn" href="<?= e('app.php?' . http_build_query(array_merge($_GET, ['p' => $p + 1]))) ?>">Next</a><?php endif; ?></div>
                            </div><?php endif; ?>
                    </div>

                <?php elseif ($page === 'studentform'):
                    require_roles(['admin', 'staff']);
                    $id = (int)($_GET['id'] ?? 0);
                    $s = [];
                    if ($id) {
                        $st = $pdo->prepare('SELECT * FROM students WHERE id=?');
                        $st->execute([$id]);
                        $s = $st->fetch() ?: [];
                        if (!$s) throw new RuntimeException('Student not found.');
                    }
                ?>
                    <div class="panel form-panel">
                        <div class="panel-heading">
                            <div>
                                <h3><?= $id ? 'Edit student details' : 'Register a student' ?></h3>
                                <p class="muted">Fields marked with * are required.</p>
                            </div><a class="btn secondary" href="app.php?page=students">Cancel</a>
                        </div>
                        <form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_student"><input type="hidden" name="id" value="<?= $id ?>">
                            <h4 class="form-section-title">Personal information</h4>
                            <div class="form-grid">
                                <?php field('Student ID *', 'student_no', 'text', $s['student_no'] ?? ('STU-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(2)))), ['required' => true, 'maxlength' => 30]);
                                field('First name *', 'first_name', 'text', $s['first_name'] ?? '', ['required' => true]);
                                field('Middle name', 'middle_name', 'text', $s['middle_name'] ?? '');
                                field('Last name *', 'last_name', 'text', $s['last_name'] ?? '', ['required' => true]);
                                field('Suffix', 'suffix', 'text', $s['suffix'] ?? '');
                                field('Sex', 'sex', 'select', $s['sex'] ?? 'Prefer not to say', ['options' => ['Male' => 'Male', 'Female' => 'Female', 'Prefer not to say' => 'Prefer not to say']]);
                                field('Date of birth', 'date_of_birth', 'date', $s['date_of_birth'] ?? '');
                                field('Place of birth', 'place_of_birth', 'text', $s['place_of_birth'] ?? '');
                                field('Civil status', 'civil_status', 'select', $s['civil_status'] ?? 'Single', ['options' => ['Single' => 'Single', 'Married' => 'Married', 'Widowed' => 'Widowed', 'Separated' => 'Separated', 'Other' => 'Other']]);
                                field('Nationality', 'nationality', 'text', $s['nationality'] ?? 'Filipino');
                                field('Religion (optional)', 'religion', 'text', $s['religion'] ?? ''); ?>
                            </div>
                            <h4 class="form-section-title">Contact information</h4>
                            <div class="form-grid"><?php field('Email address', 'email', 'email', $s['email'] ?? '');
                                                    field('Mobile number', 'phone', 'tel', $s['phone'] ?? '');
                                                    field('Current address', 'current_address', 'text', $s['current_address'] ?? '');
                                                    field('Permanent address', 'permanent_address', 'text', $s['permanent_address'] ?? ''); ?></div>
                            <h4 class="form-section-title">Parent / guardian</h4>
                            <div class="form-grid"><?php field('Guardian full name', 'guardian_name', 'text', $s['guardian_name'] ?? '');
                                                    field('Relationship', 'guardian_relationship', 'text', $s['guardian_relationship'] ?? '');
                                                    field('Guardian contact', 'guardian_phone', 'tel', $s['guardian_phone'] ?? '');
                                                    field('Guardian address', 'guardian_address', 'text', $s['guardian_address'] ?? ''); ?></div>
                            <div class="form-actions"><a class="btn secondary" href="app.php?page=students">Cancel</a><button class="btn primary"><i class="bi bi-check2"></i> <?= $id ? 'Save changes' : 'Register student' ?></button></div>
                        </form>
                    </div>

                    <?php elseif ($page === 'studentview'):
                    $id = (int)($_GET['id'] ?? 0);
                    $st = $pdo->prepare('SELECT * FROM students WHERE id=?');
                    $st->execute([$id]);
                    $s = $st->fetch();
                    if (!$s): ?><div class="alert error">Student not found.</div><?php else:
                                                                                    $en = $pdo->prepare('SELECT e.*,c.name course_name,ay.label academic_year FROM enrollments e JOIN courses c ON c.id=e.course_id JOIN academic_years ay ON ay.id=e.academic_year_id WHERE e.student_id=? ORDER BY e.created_at DESC');
                                                                                    $en->execute([$id]);
                                                                                    $enrollments = $en->fetchAll(); ?>
                        <div class="profile-header panel">
                            <div class="avatar profile-avatar"><?= e(strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1))) ?></div>
                            <div class="profile-main"><span class="id-tag"><?= e($s['student_no']) ?></span>
                                <h2><?= e(student_name($s)) ?></h2>
                                <p class="muted"><?= e($s['email'] ?: 'No email') ?> · <?= e($s['phone'] ?: 'No phone') ?></p>
                            </div><?php if (can_manage()): ?><a class="btn primary" href="app.php?page=studentform&id=<?= $id ?>"><i class="bi bi-pencil"></i> Edit record</a><?php endif; ?>
                        </div>
                        <div class="panel">
                            <div class="panel-heading">
                                <h3>Personal and contact details</h3>
                            </div>
                            <div class="detail-grid"><?php foreach (['Sex' => 'sex', 'Date of birth' => 'date_of_birth', 'Place of birth' => 'place_of_birth', 'Civil status' => 'civil_status', 'Nationality' => 'nationality', 'Religion' => 'religion', 'Current address' => 'current_address', 'Permanent address' => 'permanent_address', 'Guardian' => 'guardian_name', 'Guardian relationship' => 'guardian_relationship', 'Guardian phone' => 'guardian_phone', 'Guardian address' => 'guardian_address'] as $label => $key): ?><div class="detail-item"><small><?= e($label) ?></small><b><?= e($s[$key] ?: '—') ?></b></div><?php endforeach; ?></div>
                        </div>
                        <div class="panel">
                            <div class="panel-heading">
                                <h3>Enrollment history</h3>
                            </div><?php if (!$enrollments): ?><div class="empty-state">
                                    <p>No enrollment records yet.</p><a class="btn primary" href="app.php?page=enrollment">Create enrollment</a>
                                </div><?php else: ?><div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Academic year</th>
                                                <th>Semester</th>
                                                <th>Course</th>
                                                <th>Year level</th>
                                                <th>Section</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php foreach ($enrollments as $enr): ?><tr>
                                                    <td><?= e($enr['academic_year']) ?></td>
                                                    <td><?= e($enr['semester']) ?></td>
                                                    <td><?= e($enr['course_name']) ?></td>
                                                    <td><?= e($enr['year_level']) ?></td>
                                                    <td><?= e($enr['section_name'] ?: '—') ?></td>
                                                    <td><span class="status <?= strtolower($enr['status']) ?>"><?= e($enr['status']) ?></span></td>
                                                </tr><?php endforeach; ?></tbody>
                                    </table>
                                </div><?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'courses'):
                    require_roles(['admin']);
                    $courses = $pdo->query('SELECT c.*,COUNT(DISTINCT e.student_id) students_count FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id GROUP BY c.id ORDER BY c.name')->fetchAll(); ?>
                    <div class="toolbar">
                        <div class="muted">Manage academic programs offered by the school.</div><button class="btn primary" onclick="document.getElementById('courseForm').classList.toggle('hidden')"><i class="bi bi-plus-lg"></i> Add course</button>
                    </div>
                    <div class="panel hidden" id="courseForm">
                        <div class="panel-heading">
                            <h3>Course details</h3>
                        </div>
                        <form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_course">
                            <div class="form-grid"><?php field('Course code *', 'code', 'text', '', ['required' => true]);
                                                    field('Course name *', 'name', 'text', '', ['required' => true]);
                                                    field('Description', 'description', 'text', ''); ?></div><button class="btn primary">Save course</button>
                        </form>
                    </div>
                    <div class="panel">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Course / program</th>
                                        <th>Description</th>
                                        <th>Students</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($courses as $c): ?><tr>
                                            <td><span class="id-tag"><?= e($c['code']) ?></span></td>
                                            <td><b><?= e($c['name']) ?></b></td>
                                            <td><?= e($c['description'] ?: '—') ?></td>
                                            <td><?= number_format($c['students_count']) ?></td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Delete this course? Existing enrollments may prevent deletion.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_course"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="icon-btn danger-text"><i class="bi bi-trash"></i></button></form>
                                            </td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'subjects'):
                    require_roles(['admin']);
                    $subjects = $pdo->query('SELECT s.*,c.name course_name FROM subjects s LEFT JOIN courses c ON c.id=s.course_id ORDER BY s.code')->fetchAll(); ?>
                    <div class="toolbar">
                        <p class="muted">Manage subject codes, units, and associated programs.</p><button class="btn primary" onclick="document.getElementById('subjectForm').classList.toggle('hidden')"><i class="bi bi-plus-lg"></i> Add subject</button>
                    </div>
                    <div class="panel hidden" id="subjectForm">
                        <div class="panel-heading">
                            <h3>Subject details</h3>
                        </div>
                        <form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_subject">
                            <div class="form-grid"><?php field('Subject code *', 'code', 'text', '', ['required' => true]);
                                                    field('Subject name *', 'name', 'text', '', ['required' => true]);
                                                    field('Units *', 'units', 'number', '3', ['required' => true]);
                                                    field('Course (optional)', 'course_id', 'select', '', ['options' => options_from($pdo, 'courses', 'id', 'name')]); ?></div><button class="btn primary">Save subject</button>
                        </form>
                    </div>
                    <div class="panel">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Subject</th>
                                        <th>Units</th>
                                        <th>Course</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($subjects as $s): ?><tr>
                                            <td><span class="id-tag"><?= e($s['code']) ?></span></td>
                                            <td><b><?= e($s['name']) ?></b></td>
                                            <td><?= e($s['units']) ?></td>
                                            <td><?= e($s['course_name'] ?: 'All courses / unassigned') ?></td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Delete this subject? Existing grades may prevent deletion.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_subject"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="icon-btn danger-text"><i class="bi bi-trash"></i></button></form>
                                            </td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'enrollment'):
                    require_roles(['admin', 'staff', 'viewer']);
                    $enrollments = $pdo->query('SELECT e.*,s.student_no,s.first_name,s.last_name,c.name course_name,ay.label academic_year FROM enrollments e JOIN students s ON s.id=e.student_id JOIN courses c ON c.id=e.course_id JOIN academic_years ay ON ay.id=e.academic_year_id ORDER BY e.created_at DESC LIMIT 300')->fetchAll(); ?>
                    <?php if (can_manage()): ?><div class="panel">
                            <div class="panel-heading">
                                <div>
                                    <h3>Create enrollment</h3>
                                    <p class="muted">Choose a student, course, and academic year.</p>
                                </div>
                            </div>
                            <form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_enrollment">
                                <div class="form-grid"><?php field('Student *', 'student_id', 'select', '', ['required' => true, 'options' => options_from($pdo, 'students', 'id', 'student_no')]);
                                                        field('Course *', 'course_id', 'select', '', ['required' => true, 'options' => options_from($pdo, 'courses', 'id', 'name')]);
                                                        field('Academic year *', 'academic_year_id', 'select', '', ['required' => true, 'options' => options_from($pdo, 'academic_years', 'id', 'label')]);
                                                        field('Semester', 'semester', 'select', '1st', ['options' => ['1st' => '1st', '2nd' => '2nd', 'Summer' => 'Summer', 'Trimester 1' => 'Trimester 1', 'Trimester 2' => 'Trimester 2', 'Trimester 3' => 'Trimester 3']]);
                                                        field('Year level', 'year_level', 'select', '1', ['options' => [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year']]);
                                                        field('Section', 'section_name');
                                                        field('Status', 'status', 'select', 'Pending', ['options' => ['Pending' => 'Pending', 'Enrolled' => 'Enrolled', 'Inactive' => 'Inactive', 'Graduated' => 'Graduated']]); ?></div><button class="btn primary">Create enrollment</button>
                            </form>
                        </div><?php endif; ?>
                    <div class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Enrollment records</h3>
                                <p class="muted">Latest 300 records</p>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Academic year</th>
                                        <th>Semester</th>
                                        <th>Course</th>
                                        <th>Level / section</th>
                                        <th>Status</th>
                                        <th>Update</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($enrollments as $enr): ?><tr>
                                            <td><b><?= e($enr['first_name'] . ' ' . $enr['last_name']) ?></b><small class="table-sub"><?= e($enr['student_no']) ?></small></td>
                                            <td><?= e($enr['academic_year']) ?></td>
                                            <td><?= e($enr['semester']) ?></td>
                                            <td><?= e($enr['course_name']) ?></td>
                                            <td><?= e($enr['year_level']) ?> / <?= e($enr['section_name'] ?: '—') ?></td>
                                            <td><span class="status <?= strtolower($enr['status']) ?>"><?= e($enr['status']) ?></span></td>
                                            <td><?php if (can_manage()): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_enrollment_status"><input type="hidden" name="id" value="<?= (int)$enr['id'] ?>"><select name="status"><?php foreach (['Pending', 'Enrolled', 'Inactive', 'Graduated'] as $st): ?><option <?= $enr['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select><button class="btn secondary small-btn">Save</button></form><?php else: ?>—<?php endif; ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'grades'):
                    require_roles(['admin', 'staff', 'teacher', 'viewer']);
                    $gradeRows = $pdo->query('SELECT g.*,s.student_no,s.first_name,s.last_name,su.code subject_code,su.name subject_name,su.units,e.semester,ay.label academic_year FROM grades g JOIN enrollments e ON e.id=g.enrollment_id JOIN students s ON s.id=e.student_id JOIN subjects su ON su.id=g.subject_id JOIN academic_years ay ON ay.id=e.academic_year_id ORDER BY g.updated_at DESC LIMIT 500')->fetchAll();
                    $enOpts = $pdo->query("SELECT e.id,CONCAT(s.student_no,' — ',s.last_name,', ',s.first_name,' | ',ay.label,' ',e.semester) label FROM enrollments e JOIN students s ON s.id=e.student_id JOIN academic_years ay ON ay.id=e.academic_year_id ORDER BY e.created_at DESC")->fetchAll();
                    $subOpts = $pdo->query('SELECT id,CONCAT(code," — ",name) label FROM subjects ORDER BY code')->fetchAll(); ?>
                    <?php if (in_array($role, ['admin', 'staff', 'teacher'], true)): ?><div class="panel">
                            <div class="panel-heading">
                                <div>
                                    <h3>Record a grade</h3>
                                    <p class="muted">Grades use a 0–100 numeric scale; confirm your school's official grading policy.</p>
                                </div>
                            </div>
                            <form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_grade">
                                <div class="form-grid"><label>Enrollment *<select name="enrollment_id" required>
                                            <option value="">— Select enrollment —</option><?php foreach ($enOpts as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['label']) ?></option><?php endforeach; ?>
                                        </select></label><label>Subject *<select name="subject_id" required>
                                            <option value="">— Select subject —</option><?php foreach ($subOpts as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['label']) ?></option><?php endforeach; ?>
                                        </select></label><?php field('Grade (0–100) *', 'grade_value', 'number', '', ['required' => true]);
                                                            field('Remarks', 'remarks'); ?></div><button class="btn primary">Save grade</button>
                            </form>
                        </div><?php endif; ?>
                    <div class="panel">
                        <div class="panel-heading">
                            <h3>Academic records</h3><a class="btn secondary" href="app.php?page=reports&export=grades">Export CSV</a>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Academic year</th>
                                        <th>Subject</th>
                                        <th>Units</th>
                                        <th>Grade</th>
                                        <th>Remarks</th>
                                        <th>Last updated</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($gradeRows as $g): ?><tr>
                                            <td><?= e($g['first_name'] . ' ' . $g['last_name']) ?><small class="table-sub"><?= e($g['student_no']) ?></small></td>
                                            <td><?= e($g['academic_year'] . ' · ' . $g['semester']) ?></td>
                                            <td><?= e($g['subject_code'] . ' — ' . $g['subject_name']) ?></td>
                                            <td><?= e($g['units']) ?></td>
                                            <td><b><?= e(number_format((float)$g['grade_value'], 2)) ?></b></td>
                                            <td><?= e($g['remarks'] ?: '—') ?></td>
                                            <td><?= e(date('M j, Y', strtotime($g['updated_at']))) ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'reports'):
                    require_roles(['admin', 'staff', 'viewer']);
                    $export = (string)($_GET['export'] ?? '');
                    if ($export === 'students') {
                        $rows = $pdo->query('SELECT student_no,first_name,middle_name,last_name,suffix,sex,date_of_birth,email,phone,current_address FROM students ORDER BY last_name,first_name')->fetchAll();
                        $filename = 'student-master-list.csv';
                    } elseif ($export === 'grades') {
                        $rows = $pdo->query('SELECT s.student_no,s.first_name,s.last_name,ay.label academic_year,e.semester,su.code subject_code,su.name subject_name,su.units,g.grade_value,g.remarks FROM grades g JOIN enrollments e ON e.id=g.enrollment_id JOIN students s ON s.id=e.student_id JOIN subjects su ON su.id=g.subject_id JOIN academic_years ay ON ay.id=e.academic_year_id ORDER BY s.last_name,s.first_name')->fetchAll();
                        $filename = 'student-grades.csv';
                    } else $rows = [];
                    if ($export && isset($filename)) {
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        $out = fopen('php://output', 'w');
                        if ($rows) {
                            fputcsv($out, array_keys($rows[0]));
                            foreach ($rows as $r) fputcsv($out, $r);
                        }
                        fclose($out);
                        exit;
                    }
                    $summary = $pdo->query("SELECT c.name course,COUNT(DISTINCT CASE WHEN e.status='Enrolled' THEN e.student_id END) enrolled,COUNT(e.id) enrollments FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id GROUP BY c.id ORDER BY c.name")->fetchAll();
                    $sex = $pdo->query('SELECT sex,COUNT(*) total FROM students GROUP BY sex')->fetchAll(); ?>
                    <div class="stats-grid compact-stats">
                        <div class="stat-card">
                            <div class="stat-icon blue"><i class="bi bi-file-earmark-spreadsheet"></i></div><span>Student master list</span><strong>CSV</strong><a class="text-link" href="app.php?page=reports&export=students">Download report <i class="bi bi-download"></i></a>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon violet"><i class="bi bi-mortarboard"></i></div><span>Grade report</span><strong>CSV</strong><a class="text-link" href="app.php?page=reports&export=grades">Download report <i class="bi bi-download"></i></a>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon green"><i class="bi bi-printer"></i></div><span>Print current report</span><strong>Print</strong><button class="text-link as-button" onclick="window.print()">Open print dialog <i class="bi bi-arrow-up-right"></i></button>
                        </div>
                    </div>
                    <div class="dashboard-grid">
                        <div class="panel">
                            <div class="panel-heading">
                                <h3>Enrollment summary by course</h3>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Enrolled students</th>
                                            <th>Total enrollments</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($summary as $r): ?><tr>
                                                <td><?= e($r['course']) ?></td>
                                                <td><?= number_format($r['enrolled']) ?></td>
                                                <td><?= number_format($r['enrollments']) ?></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-heading">
                                <h3>Student population by sex</h3>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sex</th>
                                            <th>Students</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($sex as $r): ?><tr>
                                                <td><?= e($r['sex']) ?></td>
                                                <td><?= number_format($r['total']) ?></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif ($page === 'users'):
                    require_roles(['admin']);
                    $users = $pdo->query('SELECT id,username,full_name,role,is_active,created_at FROM users ORDER BY created_at DESC')->fetchAll(); ?>
                    <div class="toolbar">
                        <p class="muted">Create accounts and assign least-privilege roles.</p><button class="btn primary" onclick="document.getElementById('userForm').classList.toggle('hidden')"><i class="bi bi-person-plus"></i> Add user</button>
                    </div>
                    <div class="panel hidden" id="userForm">
                        <div class="panel-heading">
                            <h3>User account</h3>
                        </div>
                        <form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_user">
                            <div class="form-grid"><?php field('Full name *', 'full_name', 'text', '', ['required' => true]);
                                                    field('Username *', 'username', 'text', '', ['required' => true]);
                                                    field('Password (10+ characters) *', 'password', 'password', '', ['required' => true]);
                                                    field('Role', 'role', 'select', 'staff', ['options' => ['admin' => 'Administrator', 'staff' => 'Registrar / Staff', 'teacher' => 'Teacher', 'viewer' => 'Viewer']]); ?><label class="check-label"><input type="checkbox" name="is_active" checked> Account active</label></div><button class="btn primary">Create user</button>
                        </form>
                    </div>
                    <div class="panel">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($users as $u): ?><tr>
                                            <td><b><?= e($u['full_name']) ?></b></td>
                                            <td><?= e($u['username']) ?></td>
                                            <td><?= e(ucfirst($u['role'])) ?></td>
                                            <td><span class="status <?= $u['is_active'] ? 'enrolled' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                            <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                                            <td><?php if ((int)$u['id'] !== (int)$user['id'] && $u['role'] !== 'admin'): ?><form method="post" onsubmit="return confirm('Delete this user account?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="icon-btn danger-text"><i class="bi bi-trash"></i></button></form><?php else: ?>—<?php endif; ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'activity'):
                    require_roles(['admin']);
                    $logs = $pdo->query('SELECT a.*,u.username,u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 300')->fetchAll(); ?>
                    <div class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Recent system activity</h3>
                                <p class="muted">Latest 300 audit events</p>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date and time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP address</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($logs as $l): ?><tr>
                                            <td><?= e(date('M j, Y g:i A', strtotime($l['created_at']))) ?></td>
                                            <td><?= e($l['full_name'] ?: $l['username'] ?: 'System') ?></td>
                                            <td><span class="id-tag"><?= e($l['action']) ?></span></td>
                                            <td><?= e($l['details'] ?: '—') ?></td>
                                            <td><?= e($l['ip_address'] ?: '—') ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <footer class="footer">Student Record Management System <span>·</span> Keep student information private and secure.</footer>
            </section>
        </main>
    </div>
    <script src="assets/js/app.js"></script>
</body>

</html>