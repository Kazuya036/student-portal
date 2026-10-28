<?php
declare(strict_types=1);

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Invalid request token. Go back, refresh the page, and try again.');
    }
}
function current_user(): ?array {
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}
function require_login(): void {
    if (!current_user()) redirect('login.php');
}
function require_roles(array $roles): void {
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}
function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
function audit(PDO $pdo, string $action, string $details = ''): void {
    $user = current_user();
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user['id'] ?? null, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}
function valid_date_or_null($date): ?string {
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', (string)$date);
    return $d && $d->format('Y-m-d') === $date ? $date : null;
}
function student_name(array $s): string {
    return trim(($s['first_name'] ?? '') . ' ' . (($s['middle_name'] ?? '') ? $s['middle_name'] . ' ' : '') . ($s['last_name'] ?? '') . (($s['suffix'] ?? '') ? ' ' . $s['suffix'] : ''));
}
