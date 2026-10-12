<?php
require_once __DIR__ . '/includes/init.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) {
    error_page(404, 'This task does not exist — it may have been deleted by an admin.');
}
$page_title = $task['title'];

$edits = db()->prepare("SELECT * FROM edit_requests WHERE task_id = ? ORDER BY id DESC");
$edits->execute([$id]);
$editRequests = $edits->fetchAll();

$completes = db()->prepare("SELECT * FROM completion_requests WHERE task_id = ? ORDER BY id DESC");
$completes->execute([$id]);
$completeRequests = $completes->fetchAll();

$hasPendingComplete = in_array('pending', array_column($completeRequests, 'status'), true);
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <p class="crumbs"><a href="index.php">Dashboard</a> / Task #<?= (int)$task['id'] ?></p>
    <h1><?= e($task['title']) ?></h1>
  </div>
  <div class="badge-row">
    <?= priority_badge($task['priority']) ?>
    <?= status_badge($task['status']) ?>
  </div>
</div>

<div class="detail-grid">
  <div class="card">
    <div class="card-head"><h2>Details</h2></div>
    <dl class="details">
      <dt>Description</dt>
      <dd><?= nl2br(e($task['description'])) !== '' ? nl2br(e($task['description'])) : '<span class="muted">No description</span>' ?></dd>
      <dt>Deadline</dt>
      <dd><?= e(format_deadline($task)) ?></dd>
      <dt>Assigned To</dt>
      <dd><?= e($task['assigned_to']) ?></dd>
      <dt>Assigned By</dt>
      <dd><?= e($task['assigned_by']) ?></dd>
      <dt>Task Assigned On</dt>
      <dd><?= e($task['assigned_on']) ?></dd>
      <dt>Status</dt>
      <dd><?= status_badge($task['status']) ?></dd>
    </dl>

    <?php if ($task['status'] === 'pending'): ?>
    <div class="card-sep">
      <h3>Request a change</h3>
      <p class="muted small">No login needed. Requests are reviewed and approved by the admin before the task is updated.</p>
      <button type="button" class="btn btn-primary" id="toggleEdit" aria-expanded="false" aria-controls="editFormBox">Edit</button>
      <div class="collapsible" id="editFormBox" hidden>
      <form method="post" action="request_edit.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
        <div class="field">
          <label>Your Name *</label>
          <input type="text" name="requester_name" required value="<?= e($u['full_name'] ?? '') ?>" placeholder="Who is requesting?">
        </div>
        <div class="field">
          <label>New Task Title</label>
          <input type="text" name="title" maxlength="150" value="<?= e($task['title']) ?>">
        </div>
        <div class="field">
          <label>New Description</label>
          <textarea name="description" rows="3"><?= e($task['description']) ?></textarea>
        </div>
        <div class="grid-2">
          <div class="field">
            <label>New Priority</label>
            <select name="priority">
              <option value="high" <?= $task['priority'] === 'high' ? 'selected' : '' ?>>High</option>
              <option value="medium" <?= $task['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
              <option value="low" <?= $task['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
          </div>
          <div class="field">
            <label>New Deadline Type</label>
            <select name="deadline_type" id="deadlineType">
              <option value="date" <?= empty($task['has_time']) ? 'selected' : '' ?>>Full day (date only)</option>
              <option value="datetime" <?= !empty($task['has_time']) ? 'selected' : '' ?>>Date and time</option>
            </select>
          </div>
        </div>
        <div class="grid-2">
          <div class="field">
            <label>New Deadline Date</label>
            <input type="date" name="deadline_date" id="deadlineDate" value="<?= e($task['deadline_date']) ?>">
          </div>
          <div class="field" id="timeField" style="display:none">
            <label>New Deadline Time</label>
            <input type="time" name="deadline_time" id="deadlineTime" value="<?= e($task['deadline_time'] ?? '') ?>">
          </div>
        </div>
        <div class="grid-2">
          <div class="field">
            <label>Reassign To</label>
            <input type="text" name="assigned_to" value="<?= e($task['assigned_to']) ?>">
          </div>
          <div class="field">
            <label>Reason for change</label>
            <input type="text" name="reason" placeholder="Why should this change?">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Request Edit</button>
          <button class="btn btn-ghost" type="button" id="cancelEdit">Cancel</button>
        </div>
      </form>
      </div>
    </div>

    <div class="card-sep">
      <h3>Mark as completed</h3>
      <?php if ($hasPendingComplete): ?>
        <div class="alert alert-info">A completion request is already awaiting admin verification.</div>
      <?php else: ?>
        <p class="muted small">The admin will verify and confirm completion.</p>
        <form method="post" action="request_complete.php" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
          <div class="field">
            <label>Your Name *</label>
            <input type="text" name="requester_name" required value="<?= e($u['full_name'] ?? '') ?>">
          </div>
          <div class="form-actions">
            <button class="btn btn-success" type="submit">Request Completion</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <div class="card-sep admin-zone">
      <h3>Admin Actions</h3>
      <div class="form-actions">
        <?php if ($task['status'] === 'pending'): ?>
        <form method="post" action="task_action.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="complete">
          <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
          <button class="btn btn-success" type="submit">Mark Completed Directly</button>
        </form>
        <?php else: ?>
        <form method="post" action="task_action.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reopen">
          <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
          <button class="btn btn-warn" type="submit">Reopen Task</button>
        </form>
        <?php endif; ?>
        <form method="post" action="task_action.php" onsubmit="return confirm('Delete this task permanently?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
          <button class="btn btn-danger" type="submit">Delete Task</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Change Requests</h2></div>
    <?php if (!$editRequests && !$completeRequests): ?>
      <div class="empty">No requests yet for this task.</div>
    <?php endif; ?>
    <?php foreach ($editRequests as $r): ?>
      <?php $payload = json_decode($r['changes_json'], true) ?: []; $changes = $payload['changes'] ?? []; ?>
      <div class="request req-<?= e($r['status']) ?>">
        <div class="req-head">
          <strong>Edit request</strong>
          <span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
        </div>
        <div class="muted small"><?= e($r['requester_name']) ?> · <?= e($r['created_at']) ?></div>
        <ul class="change-list">
          <?php foreach ($changes as $field => $c): ?>
            <li><span class="change-key"><?= e($field) ?></span>
              <span class="change-old"><?= e($c['old'] ?? '') !== '' ? e($c['old']) : '<empty>' ?></span>
              <span class="change-arrow">→</span>
              <span class="change-new"><?= e($c['new'] ?? '') !== '' ? e($c['new']) : '<empty>' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($r['reason']): ?><p class="small">Reason: <?= e($r['reason']) ?></p><?php endif; ?>
        <?php if ($r['status'] === 'pending' && is_admin()): ?>
        <div class="form-actions">
          <form method="post" action="review_edit.php">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="decision" value="accepted">
            <button class="btn btn-success btn-small" type="submit">Accept & Update</button>
          </form>
          <form method="post" action="review_edit.php">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="decision" value="rejected">
            <button class="btn btn-ghost btn-small" type="submit">Reject</button>
          </form>
        </div>
        <?php elseif ($r['status'] !== 'pending'): ?>
        <div class="muted small">Reviewed by <?= e($r['reviewed_by']) ?> · <?= e($r['reviewed_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php foreach ($completeRequests as $r): ?>
      <div class="request req-<?= e($r['status']) ?>">
        <div class="req-head">
          <strong>Completion request</strong>
          <span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
        </div>
        <div class="muted small"><?= e($r['requester_name']) ?> · <?= e($r['created_at']) ?></div>
        <?php if ($r['status'] === 'pending' && is_admin()): ?>
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
        <?php elseif ($r['status'] !== 'pending'): ?>
        <div class="muted small">Reviewed by <?= e($r['reviewed_by']) ?> · <?= e($r['reviewed_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
