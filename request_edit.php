<?php
require_once __DIR__ . '/includes/init.php';
verify_csrf();

$taskId = (int)($_POST['task_id'] ?? 0);
$requester = trim($_POST['requester_name'] ?? '');
$reason = trim($_POST['reason'] ?? '');

$task = db()->prepare("SELECT * FROM tasks WHERE id = ?");
$task->execute([$taskId]);
$task = $task->fetch();

if (!$task || $task['status'] !== 'pending') {
    flash('Task not found or already completed.', 'error');
    header('Location: index.php');
    exit;
}
if ($requester === '') {
    flash('Your name is required for a request.', 'error');
    header('Location: task.php?id=' . $taskId);
    exit;
}

$deadlineType = $_POST['deadline_type'] ?? 'date';
$newDate = ($_POST['deadline_date'] ?? '') ?: $task['deadline_date'];
$newTime = $deadlineType === 'datetime' ? (($_POST['deadline_time'] ?? '') ?: null) : null;

$old = [
    'title'        => $task['title'],
    'description'  => $task['description'],
    'priority'     => ucfirst($task['priority']),
    'deadline'     => format_deadline($task),
    'assigned_to'  => $task['assigned_to'],
];
$new = [
    'title'        => trim($_POST['title'] ?? $task['title']),
    'description'  => trim($_POST['description'] ?? $task['description']),
    'priority'     => ucfirst($_POST['priority'] ?? $task['priority']),
    'deadline'     => format_deadline([
        'deadline_date' => $newDate,
        'deadline_time' => $newTime,
        'has_time'      => $newTime ? 1 : 0,
    ]),
    'assigned_to'  => trim($_POST['assigned_to'] ?? $task['assigned_to']),
];

$changes = [];
foreach ($new as $key => $val) {
    if ((string)$val !== (string)$old[$key]) {
        $changes[$key] = ['old' => $old[$key], 'new' => $val];
    }
}

if (!$changes) {
    flash('Nothing changed — no edit request was created.', 'info');
    header('Location: task.php?id=' . $taskId);
    exit;
}

// Store actionable fields alongside the readable diff
$payload = [
    'changes' => $changes,
    'apply'   => [
        'title'        => $new['title'],
        'description'  => $new['description'],
        'priority'     => strtolower($_POST['priority'] ?? $task['priority']),
        'deadline_date'=> $newDate,
        'deadline_time'=> $newTime,
        'has_time'     => $newTime ? 1 : 0,
        'assigned_to'  => $new['assigned_to'],
    ],
];

$stmt = db()->prepare("INSERT INTO edit_requests (task_id, requester_name, changes_json, reason) VALUES (?,?,?,?)");
$stmt->execute([$taskId, $requester, json_encode($payload), $reason]);

flash('Edit request submitted. The admin will review it.');
header('Location: task.php?id=' . $taskId);
exit;
