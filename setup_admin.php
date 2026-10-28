<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$error = '';
if ($count > 0) {
    http_response_code(403);
    exit('An administrator already exists. For security, delete or rename setup_admin.php now.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_.-]{4,40}$/', $username) || strlen($password) < 10) {
        $error = 'Enter a name, a username (4–40 letters/numbers/._-), and a password of at least 10 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, 'admin', 1)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name]);
            redirect('login.php?created=1');
        } catch (PDOException $e) { $error = 'Could not create account. The username may already exist.'; }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create first administrator</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="login-page"><main class="login-card"><div class="brand-mark">SR</div><h1>Create administrator</h1><p class="muted">This one-time page is available only while no administrator exists.</p>
<?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label>Full name<input name="full_name" required></label><label>Username<input name="username" minlength="4" maxlength="40" required></label><label>Password (minimum 10 characters)<input type="password" name="password" minlength="10" required></label><button class="btn primary full">Create administrator</button></form>
<p class="small muted">After creating the account, delete <code>setup_admin.php</code> from the project folder.</p></main></body></html>
