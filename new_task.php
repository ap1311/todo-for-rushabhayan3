<?php
require_once __DIR__ . '/includes/init.php';
$page_title = 'New Task';
require __DIR__ . '/includes/header.php';

$people = db()->query("SELECT DISTINCT name FROM (
    SELECT assigned_to AS name FROM tasks
    UNION SELECT assigned_by FROM tasks
    UNION SELECT full_name AS name FROM users
) ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-head">
  <div>
    <h1>Create a Task</h1>
    <p class="muted">Anyone can create a task — no login required.</p>
  </div>
</div>

<?php $errors = $_SESSION['task_errors'] ?? []; unset($_SESSION['task_errors']); ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="card form-card">
  <form method="post" action="save_task.php" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label>Task Title *</label>
      <input type="text" name="title" required maxlength="150" value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. Prepare Q4 report">
    </div>
    <div class="field">
      <label>Task Description</label>
      <textarea name="description" rows="4" placeholder="Details, scope, acceptance criteria…"><?= e($_POST['description'] ?? '') ?></textarea>
    </div>
    <div class="grid-2">
      <div class="field">
        <label>Priority *</label>
        <select name="priority" required>
          <option value="high" <?= ($_POST['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
          <option value="medium" selected>Medium</option>
          <option value="low" <?= ($_POST['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
        </select>
      </div>
      <div class="field">
        <label>Deadline Type *</label>
        <select name="deadline_type" id="deadlineType">
          <option value="date" selected>Full day (date only)</option>
          <option value="datetime" <?= ($_POST['deadline_type'] ?? '') === 'datetime' ? 'selected' : '' ?>>Date and time</option>
        </select>
      </div>
    </div>
    <div class="grid-2">
      <div class="field">
        <label>Deadline Date *</label>
        <input type="date" name="deadline_date" id="deadlineDate" required value="<?= e($_POST['deadline_date'] ?? '') ?>">
      </div>
      <div class="field" id="timeField" style="display:none">
        <label>Deadline Time</label>
        <input type="time" name="deadline_time" id="deadlineTime" value="<?= e($_POST['deadline_time'] ?? '') ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="field">
        <label>Assigned To *</label>
        <input type="text" name="assigned_to" list="people" required value="<?= e($_POST['assigned_to'] ?? '') ?>" placeholder="Person name">
        <datalist id="people"><?php foreach ($people as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="field">
        <label>Assigned By *</label>
        <input type="text" name="assigned_by" list="people" required value="<?= e($_POST['assigned_by'] ?? ($u['full_name'] ?? '')) ?>" placeholder="Your name">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Create Task</button>
      <a class="btn btn-ghost" href="index.php">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
