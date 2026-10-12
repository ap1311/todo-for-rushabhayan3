<?php
require_once __DIR__ . '/includes/init.php';
if (!current_user()) {
    header('Location: login.php');
    exit;
}
$page_title = 'My Requests';

$name = current_user()['full_name'];

$edits = db()->prepare("SELECT er.*, t.title AS task_title FROM edit_requests er JOIN tasks t ON t.id = er.task_id WHERE er.requester_name = ? ORDER BY er.id DESC");
$edits->execute([$name]);
$editRequests = $edits->fetchAll();

$comps = db()->prepare("SELECT cr.*, t.title AS task_title FROM completion_requests cr JOIN tasks t ON t.id = cr.task_id WHERE cr.requester_name = ? ORDER BY cr.id DESC");
$comps->execute([$name]);
$completeRequests = $comps->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>My Requests</h1>
    <p class="muted">Status of the edit and completion requests you submitted as <strong><?= e($name) ?></strong>.</p>
  </div>
</div>

<?php if (!$editRequests && !$completeRequests): ?>
  <div class="card"><div class="empty">You haven't submitted any requests yet. Open a task to request a change or completion.</div></div>
<?php endif; ?>

<div class="detail-grid">
  <div class="card">
    <div class="card-head"><h2>Edit Requests</h2></div>
    <?php if (!$editRequests): ?><div class="empty">None.</div><?php endif; ?>
    <?php foreach ($editRequests as $r): $p = json_decode($r['changes_json'], true) ?: []; ?>
      <div class="request req-<?= e($r['status']) ?>">
        <div class="req-head">
          <strong><a href="task.php?id=<?= (int)$r['task_id'] ?>"><?= e($r['task_title']) ?></a></strong>
          <span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
        </div>
        <div class="muted small"><?= e($r['created_at']) ?></div>
        <ul class="change-list">
          <?php foreach (($p['changes'] ?? []) as $field => $c): ?>
            <li><span class="change-key"><?= e($field) ?></span>
              <span class="change-old"><?= e($c['old'] ?? '') !== '' ? e($c['old']) : '<empty>' ?></span>
              <span class="change-arrow">→</span>
              <span class="change-new"><?= e($c['new'] ?? '') !== '' ? e($c['new']) : '<empty>' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($r['status'] !== 'pending' && $r['reviewed_by']): ?>
          <div class="muted small">Reviewed by <?= e($r['reviewed_by']) ?> · <?= e($r['reviewed_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Completion Requests</h2></div>
    <?php if (!$completeRequests): ?><div class="empty">None.</div><?php endif; ?>
    <?php foreach ($completeRequests as $r): ?>
      <div class="request req-<?= e($r['status']) ?>">
        <div class="req-head">
          <strong><a href="task.php?id=<?= (int)$r['task_id'] ?>"><?= e($r['task_title']) ?></a></strong>
          <span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
        </div>
        <div class="muted small"><?= e($r['created_at']) ?></div>
        <?php if ($r['status'] !== 'pending' && $r['reviewed_by']): ?>
          <div class="muted small">Reviewed by <?= e($r['reviewed_by']) ?> · <?= e($r['reviewed_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
