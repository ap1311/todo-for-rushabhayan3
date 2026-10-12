<?php
session_start();

error_reporting(E_ALL);
$__host = $_SERVER['HTTP_HOST'] ?? '';
$__local = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $__host) === 1;
ini_set('display_errors', $__local ? '1' : '0');
ini_set('log_errors', '1');

// --- HTTPS + HSTS ---------------------------------------------------------
// Serving a login form over plain HTTP is a common phishing trigger. Force
// every request onto HTTPS and tell browsers to stick with it.
$__https = (($_SERVER['HTTPS'] ?? '') === 'on')
    || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
if (!$__local && !$__https && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && empty($_COOKIE['__https_try'])) {
    // The cookie is a one-shot loop guard: if the HTTPS detection is ever
    // wrong behind a proxy, a visitor is sent over plain HTTP once instead of
    // being trapped in an endless redirect.
    setcookie('__https_try', '1', time() + 60, '/');
    $__target = 'https://' . $__host . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $__target, true, 301);
    exit;
}
if ($__https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

define('DB_PATH', dirname(__DIR__) . '/data/todo.sqlite');

function str_param(array $src, string $key, string $default = ''): string {
    $v = $src[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

function trim_param(array $src, string $key, string $default = ''): string {
    return trim(str_param($src, $key, $default));
}

function error_page(int $code, string $message, string $hint = '') {
    http_response_code($code);
    $title = ['404' => 'Page not found', '500' => 'Something went wrong', '403' => 'Not allowed', '400' => 'Bad request'][$code] ?? 'Error';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title) . ' · Rushabhayan Task Manager</title><link rel="stylesheet" href="assets/style.css"></head>'
        . '<body><main class="container" style="padding-top:70px;text-align:center">'
        . '<div class="brand" style="justify-content:center;margin-bottom:22px"><span class="brand-mark">RT</span> Rushabhayan <span class="brand-sub">Task Manager</span></div>'
        . '<div class="card" style="max-width:460px;margin:0 auto">'
        . '<div style="font-size:44px;font-weight:800;color:var(--brand)">' . $code . '</div>'
        . '<h1 style="font-size:20px">' . htmlspecialchars($title) . '</h1>'
        . '<p class="muted">' . htmlspecialchars($message) . '</p>'
        . ($hint ? '<p class="muted small">' . htmlspecialchars($hint) . '</p>' : '')
        . '<p style="margin-top:16px"><a class="btn btn-primary" href="index.php">Back to Dashboard</a></p>'
        . '</div></main></body></html>';
    exit;
}

set_exception_handler(function (Throwable $ex) {
    error_log('[Rushabhayan] ' . get_class($ex) . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    $msg = ($_SERVER['HTTP_HOST'] ?? '') && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $_SERVER['HTTP_HOST'] ?? '')
        ? get_class($ex) . ': ' . $ex->getMessage()
        : 'An unexpected error occurred. The error has been logged.';
    error_page(500, $msg, 'Try again, or return to the dashboard.');
});

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        init_db($pdo);
    }
    return $pdo;
}

function init_db(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        priority TEXT NOT NULL DEFAULT 'medium',
        deadline_date TEXT NOT NULL,
        deadline_time TEXT,
        has_time INTEGER NOT NULL DEFAULT 0,
        assigned_to TEXT NOT NULL,
        assigned_by TEXT NOT NULL,
        assigned_on TEXT NOT NULL DEFAULT (date('now')),
        status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS edit_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        requester_name TEXT NOT NULL,
        changes_json TEXT NOT NULL,
        reason TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending',
        reviewed_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        reviewed_at TEXT
    );
    CREATE TABLE IF NOT EXISTS completion_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        requester_name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        reviewed_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        reviewed_at TEXT
    );
    ");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        // Never ship a hardcoded password. Prefer ADMIN_USER / ADMIN_PASSWORD
        // environment variables; otherwise generate a random one and write it
        // to data/ (which is blocked from the web by data/.htaccess).
        $username = getenv('ADMIN_USER') ?: 'admin';
        $fullName = getenv('ADMIN_NAME') ?: 'Administrator';
        $password = getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(8));
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, 'admin']);
        if (!getenv('ADMIN_PASSWORD')) {
            @file_put_contents(
                dirname(DB_PATH) . '/initial_admin_password.txt',
                "Initial admin account\r\nusername: {$username}\r\npassword: {$password}\r\n\r\nLog in and change this immediately, then delete this file.\r\n"
            );
        }
    }
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool {
    return (current_user()['role'] ?? '') === 'admin';
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
}

function flash(string $msg = null, string $type = 'success') {
    if ($msg !== null) {
        $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $token = str_param($_POST, 'csrf');
    if ($token === '' || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        flash('Your session expired or the page was stale. Please try again.', 'error');
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $back = ($ref !== '' && preg_match('/^https?:\/\/' . preg_quote($host, '/') . '\//i', $ref) === 1)
            ? $ref
            : 'index.php';
        header('Location: ' . $back);
        exit;
    }
}

function priority_badge(string $p): string {
    $map = ['high' => 'badge-high', 'medium' => 'badge-medium', 'low' => 'badge-low'];
    $cls = $map[$p] ?? 'badge-medium';
    return '<span class="badge ' . $cls . '">' . e(ucfirst($p)) . '</span>';
}

function status_badge(string $s): string {
    $cls = $s === 'completed' ? 'badge-done' : 'badge-pending';
    return '<span class="badge ' . $cls . '">' . e(ucfirst($s)) . '</span>';
}

function format_deadline(array $t): string {
    $date = date('D, d M Y', strtotime($t['deadline_date']));
    if (!empty($t['has_time']) && $t['deadline_time']) {
        return $date . ' — ' . date('H:i', strtotime($t['deadline_time']));
    }
    return $date . ' — Full day';
}
