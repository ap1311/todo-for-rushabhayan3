<?php require_once __DIR__ . '/init.php'; $u = current_user(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($page_title) ? e($page_title) . ' · ' : '' ?>Rushabhayan Task Manager</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='24' fill='%232f5cff'/><text x='50' y='68' font-size='52' font-family='Arial' font-weight='bold' fill='white' text-anchor='middle'>RT</text></svg>">
</head>
<body>
<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="index.php"><span class="brand-mark">RT</span> Rushabhayan <span class="brand-sub">Task Manager</span></a>
    <nav class="nav">
      <a href="index.php">Dashboard</a>
      <a href="new_task.php">New Task</a>
      <?php if ($u): ?>
        <a href="my_requests.php">My Requests</a>
        <a href="password.php">Change Password</a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
        <a href="admin.php" class="nav-admin">Admin Panel</a>
      <?php endif; ?>
    </nav>
    <div class="auth">
      <?php if ($u): ?>
        <span class="whoami"><?= e($u['full_name']) ?></span>
        <a class="btn btn-ghost" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="btn btn-ghost" href="login.php">Admin Login</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="container">
<?php foreach (flash() ?: [] as $flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endforeach; ?>
