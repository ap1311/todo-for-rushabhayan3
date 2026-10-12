<?php
require_once __DIR__ . '/includes/init.php';
$page_title = 'Dashboard';

$filters = [
    'q'           => trim_param($_GET, 'q'),
    'status'      => str_param($_GET, 'status'),
    'priority'    => str_param($_GET, 'priority'),
    'assigned_to' => str_param($_GET, 'assigned_to'),
    'assigned_by' => str_param($_GET, 'assigned_by'),
    'from'        => str_param($_GET, 'from'),
    'to'          => str_param($_GET, 'to'),
    'sort'        => str_param($_GET, 'sort', 'deadline'),
];

$where = [];
$params = [];
if ($filters['q'] !== '') {
    $where[] = '(t.title LIKE :q OR t.description LIKE :q OR t.assigned_to LIKE :q OR t.assigned_by LIKE :q)';
    $params['q'] = '%' . $filters['q'] . '%';
}
if (in_array($filters['status'], ['pending', 'completed'], true)) {
    $where[] = 't.status = :status';
    $params['status'] = $filters['status'];
}
if (in_array($filters['priority'], ['high', 'medium', 'low'], true)) {
    $where[] = 't.priority = :priority';
    $params['priority'] = $filters['priority'];
}
if ($filters['assigned_to'] !== '') {
    $where[] = 't.assigned_to = :assigned_to';
    $params['assigned_to'] = $filters['assigned_to'];
}
if ($filters['assigned_by'] !== '') {
    $where[] = 't.assigned_by = :assigned_by';
    $params['assigned_by'] = $filters['assigned_by'];
}
if ($filters['from'] !== '') {
    $where[] = 't.deadline_date >= :from';
    $params['from'] = $filters['from'];
}
if ($filters['to'] !== '') {
    $where[] = 't.deadline_date <= :to';
    $params['to'] = $filters['to'];
}

$sorts = [
    'deadline'  => 't.deadline_date ASC, COALESCE(t.deadline_time, "23:59") ASC',
    'deadline_desc' => 't.deadline_date DESC',
    'priority'  => 'CASE t.priority WHEN "high" THEN 0 WHEN "medium" THEN 1 ELSE 2 END ASC',
    'recent'    => 't.assigned_on DESC, t.id DESC',
    'title'     => 't.title ASC',
];
$order = $sorts[$filters['sort']] ?? $sorts['deadline'];

$sql = 'SELECT t.*
        FROM tasks t';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $order;

$stmt = db()->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$people = db()->query("SELECT DISTINCT assigned_to AS name FROM tasks UNION SELECT DISTINCT assigned_by FROM tasks ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$stats = db()->query("SELECT
    COUNT(*) total,
    SUM(status = 'pending') pending,
    SUM(status = 'completed') done,
    SUM(status = 'pending' AND (deadline_date < date('now') OR (deadline_date = date('now') AND has_time = 1 AND deadline_time < time('now')))) overdue
    FROM tasks")->fetch();
$pendingEdits = (int)db()->query("SELECT COUNT(*) FROM edit_requests WHERE status='pending'")->fetchColumn();
$pendingCompletes = (int)db()->query("SELECT COUNT(*) FROM completion_requests WHERE status='pending'")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Task Dashboard</h1>
    <p class="muted">Everyone can view tasks and request changes. Only the admin approves updates.</p>
  </div>
  <a class="btn btn-primary" href="new_task.php">+ New Task</a>
</div>

<div class="stats">
  <div class="stat"><span class="stat-num"><?= (int)$stats['total'] ?></span><span class="stat-label">Total Tasks</span></div>
  <div class="stat"><span class="stat-num"><?= (int)$stats['pending'] ?></span><span class="stat-label">Pending</span></div>
  <div class="stat"><span class="stat-num"><?= (int)$stats['done'] ?></span><span class="stat-label">Completed</span></div>
  <div class="stat stat-danger"><span class="stat-num"><?= (int)$stats['overdue'] ?></span><span class="stat-label">Overdue</span></div>
  <?php if (is_admin()): ?>
  <div class="stat stat-warn"><span class="stat-num"><?= $pendingEdits + $pendingCompletes ?></span><span class="stat-label">Requests Awaiting You</span></div>
  <?php endif; ?>
</div>

<form class="filters" id="filterForm" method="get" action="index.php">
  <div class="filter-top">
    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search tasks, people, descriptions…">
    <a class="btn btn-ghost" href="index.php">Reset</a>
  </div>
  <div class="filter-grid">
    <select name="status">
      <option value="">All Statuses</option>
      <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
    </select>
    <select name="priority">
      <option value="">All Priorities</option>
      <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>High</option>
      <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
      <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
    </select>
    <select name="assigned_to">
      <option value="">Assigned to: anyone</option>
      <?php foreach ($people as $p): ?>
        <option value="<?= e($p) ?>" <?= $filters['assigned_to'] === $p ? 'selected' : '' ?>>Assigned to: <?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="assigned_by">
      <option value="">Assigned by: anyone</option>
      <?php foreach ($people as $p): ?>
        <option value="<?= e($p) ?>" <?= $filters['assigned_by'] === $p ? 'selected' : '' ?>>Assigned by: <?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort">
      <option value="deadline" <?= $filters['sort'] === 'deadline' ? 'selected' : '' ?>>Deadline (soonest)</option>
      <option value="deadline_desc" <?= $filters['sort'] === 'deadline_desc' ? 'selected' : '' ?>>Deadline (latest)</option>
      <option value="priority" <?= $filters['sort'] === 'priority' ? 'selected' : '' ?>>Priority</option>
      <option value="recent" <?= $filters['sort'] === 'recent' ? 'selected' : '' ?>>Recently assigned</option>
      <option value="title" <?= $filters['sort'] === 'title' ? 'selected' : '' ?>>Title A–Z</option>
    </select>
  </div>
  <details class="filter-range" <?= ($filters['from'] !== '' || $filters['to'] !== '') ? 'open' : '' ?>>
    <summary>Deadline range</summary>
    <div class="range-row">
      <label class="range-label">From <input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
      <label class="range-label">To <input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
    </div>
  </details>
</form>

<div class="card">
  <div class="card-head">
    <h2>Tasks (<?= count($tasks) ?>)</h2>
  </div>
  <?php if (!$tasks): ?>
    <div class="empty">No tasks match your filters.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Task</th>
          <th>Priority</th>
          <th>Deadline</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tasks as $t): $overdue = $t['status'] === 'pending' && (strtotime($t['deadline_date'] . ' ' . ($t['deadline_time'] ?? '23:59')) < time()); $done = $t['status'] === 'completed'; ?>
        <tr class="<?= $overdue ? 'row-overdue' : '' ?>">
          <td>
            <span class="dot <?= $done ? 'dot-done' : 'dot-pending' ?>" title="<?= $done ? 'Completed' : 'Pending' ?>"></span>
            <a class="task-title" href="task.php?id=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
          </td>
          <td data-label="Priority"><?= priority_badge($t['priority']) ?></td>
          <td data-label="Deadline"><?= e(format_deadline($t)) ?><?php if ($overdue): ?><div class="overdue-tag">Overdue</div><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
