<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) redirect('app.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, role, is_active FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'full_name' => $user['full_name'], 'role' => $user['role']];
        audit($pdo, 'LOGIN', 'Successful login');
        redirect('app.php');
    }
    $error = 'Invalid username or password, or the account is inactive.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · Student Records</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="login-page"><main class="login-card">
<div class="brand-mark">SR</div><p class="eyebrow">STUDENT ADMINISTRATION</p><h1>Welcome back</h1><p class="muted">Sign in to manage student records.</p>
<?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<form method="post" class="stack">
<input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label>Username<input name="username" autocomplete="username" required autofocus></label>
<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
<button class="btn primary full" type="submit">Sign in</button>
</form><p class="small muted">First time here? Run <code>setup_admin.php</code> once to create the first administrator.</p>
</main></body></html>
