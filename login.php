<?php
require_once __DIR__ . '/includes/init.php';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
        ];
        flash('Welcome back, ' . $user['full_name'] . '.');
        header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'index.php'));
        exit;
    }
    $error = 'Invalid username or password.';
}

$page_title = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Admin Login</h1><p class="muted">Log in to review and approve task requests.</p></div></div>

<div class="card form-card narrow">
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label>Username</label>
      <input type="text" name="username" required autofocus>
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" required>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Login</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
