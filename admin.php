<?php
require_once __DIR__ . '/includes/init.php';
require_admin();
$page_title = 'Admin Panel';

$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'all', 'users'], true)) $tab = 'pending';

$editStmt = db()->prepare("SELECT er.*, t.title AS task_title, t.status AS task_status FROM edit_requests er JOIN tasks t ON t.id = er.task_id" . ($tab === 'pending' ? " WHERE er.status = 'pending'" : '') . " ORDER BY er.id DESC");
$editStmt->execute();
$editRequests = $editStmt->fetchAll();

$compStmt = db()->prepare("SELECT cr.*, t.title AS task_title, t.status AS task_status FROM completion_requests cr JOIN tasks t ON t.id = cr.task_id" . ($tab === 'pending' ? " WHERE cr.status = 'pending'" : '') . " ORDER BY cr.id DESC");
$compStmt->execute();
$completeRequests = $compStmt->fetchAll();

$users = db()->query("SELECT * FROM users ORDER BY id")->fetchAll();
$pendingCount = (int)db()->query("SELECT (SELECT COUNT(*) FROM edit_requests WHERE status='pending') + (SELECT COUNT(*) FROM completion_requests WHERE status='pending')")->fetchColumn();

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Admin Panel</h1>
    <p class="muted">Approve edit requests, verify completions, and manage tasks.</p>
  </div>
</div>

<div class="tabs">
  <a class="tab <?= $tab === 'pending' ? 'active' : '' ?>" href="admin.php?tab=pending">Pending (<?= $pendingCount ?>)</a>
  <a class="tab <?= $tab === 'all' ? 'active' : '' ?>" href="admin.php?tab=all">All Requests</a>
  <a class="tab <?= $tab === 'users' ? 'active' : '' ?>" href="admin.php?tab=users">Users</a>
</div>

<?php if ($tab === 'users'): ?>
<div class="card">
  <div class="card-head"><h2>Registered Users</h2></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Created</th></tr></thead>
      <tbody>
      <?php foreach ($users as $usr): ?>
        <tr>
          <td><?= e($usr['username']) ?></td>
          <td><?= e($usr['full_name']) ?></td>
          <td><?= $usr['role'] === 'admin' ? '<span class="badge badge-high">Admin</span>' : '<span class="badge badge-low">User</span>' ?></td>
          <td><?= e($usr['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>

<div class="detail-grid">
  <div class="card">
    <div class="card-head"><h2>Edit Requests</h2></div>
    <?php if (!$editRequests): ?><div class="empty">No <?= $tab === 'pending' ? 'pending ' : '' ?>edit requests.</div><?php endif; ?>
    <?php foreach ($editRequests as $r): $p = json_decode($r['changes_json'], true) ?: []; $changes = $p['changes'] ?? []; ?>
      <div class="request req-<?= e($r['status']) ?>">
        <div class="req-head">
          <strong><a href="task.php?id=<?= (int)$r['task_id'] ?>"><?= e($r['task_title']) ?></a></strong>
          <span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
        </div>
        <div class="muted small">Requested by <?= e($r['requester_name']) ?> · <?= e($r['created_at']) ?> · Task is <?= e($r['task_status']) ?></div>
        <ul class="change-list">
          <?php foreach ($changes as $field => $c): ?>
            <li>
              <span class="change-key"><?= e($field) ?></span>
              <span class="change-old"><?= e($c['old'] ?? '') !== '' ? e($c['old']) : '<empty>' ?></span>
              <span class="change-arrow">→</span>
              <span class="change-new"><?= e($c['new'] ?? '') !== '' ? e($c['new']) : '<empty>' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($r['reason']): ?><p class="small"><strong>Reason:</strong> <?= e($r['reason']) ?></p><?php endif; ?>
        <?php if ($r['status'] === 'pending'): ?>
        <div class="form-actions">
          <form method="post" action="review_edit.php">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="decision" value="accepted">
            <button class="btn btn-success btn-small" type="submit">Accept & Update Task</button>
          </form>
          <form method="post" action="review_edit.php">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="decision" value="rejected">
            <button class="btn btn-ghost btn-small" type="submit">Reject</button>
          </form>
        </div>
        <?php else: ?>
        <div class="muted small">Reviewed by <?= e($r['reviewed_by']) ?> · <?= e($r['reviewed_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Completion Requests</h2></div>
    <?php if (!$completeRequests): ?><div class="empty">No <?= $tab === 'pending' ? 'pending ' : '' ?>completion requests.</div><?php endif; ?>
    <?php foreach ($completeRequests as $r): ?>
      <div class="request req-<?= e($r['status']) ?>">
        <div class="req-head">
          <strong><a href="task.php?id=<?= (int)$r['task_id'] ?>"><?= e($r['task_title']) ?></a></strong>
          <span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
        </div>
        <div class="muted small">Requested by <?= e($r['requester_name']) ?> · <?= e($r['created_at']) ?> · Task is <?= e($r['task_status']) ?></div>
        <?php if ($r['status'] === 'pending'): ?>
        <div class="form-actions">
          <form method="post" action="review_complete.php">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="decision" value="accepted">
            <button class="btn btn-success btn-small" type="submit">Verify Completed</button>
          </form>
          <form method="post" action="review_complete.php">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="decision" value="rejected">
            <button class="btn btn-ghost btn-small" type="submit">Reject</button>
          </form>
        </div>
        <?php else: ?>
        <div class="muted small">Reviewed by <?= e($r['reviewed_by']) ?> · <?= e($r['reviewed_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
