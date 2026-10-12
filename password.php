<?php
require_once __DIR__ . '/includes/init.php';

$u = current_user();
if (!$u) {
    header('Location: login.php');
    exit;
}

$page_title = 'Change Password';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$u['id']]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($current, $hash)) {
        $errors[] = 'Your current password is not correct.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters long.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new password and confirmation do not match.';
    }

    if (!$errors) {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        flash('Password updated. Use the new one next time you log in.');
        header('Location: password.php');
        exit;
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Change Password</h1>
    <p class="muted">Keep your admin account secure. Choose something long and unique.</p>
  </div>
</div>

<div class="card form-card narrow">
  <?php if ($errors): ?>
    <div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label>Current Password</label>
      <input type="password" name="current_password" required autofocus>
    </div>
    <div class="field">
      <label>New Password (min 8 characters)</label>
      <input type="password" name="new_password" minlength="8" required>
    </div>
    <div class="field">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" minlength="8" required>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Update Password</button>
      <a class="btn btn-ghost" href="index.php">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
