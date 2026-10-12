<?php
require_once __DIR__ . '/includes/init.php';
verify_csrf();

$taskId = (int)($_POST['task_id'] ?? 0);
$requester = trim($_POST['requester_name'] ?? '');

$task = db()->prepare("SELECT * FROM tasks WHERE id = ?");
$task->execute([$taskId]);
$task = $task->fetch();

if (!$task) {
    flash('Task not found.', 'error');
    header('Location: index.php');
    exit;
}
if ($task['status'] === 'completed') {
    flash('Task is already completed.', 'info');
    header('Location: task.php?id=' . $taskId);
    exit;
}
if ($requester === '') {
    flash('Your name is required.', 'error');
    header('Location: task.php?id=' . $taskId);
    exit;
}

$exists = db()->prepare("SELECT COUNT(*) FROM completion_requests WHERE task_id = ? AND status = 'pending'");
$exists->execute([$taskId]);
if ((int)$exists->fetchColumn() > 0) {
    flash('A completion request is already pending for this task.', 'info');
    header('Location: task.php?id=' . $taskId);
    exit;
}

$stmt = db()->prepare("INSERT INTO completion_requests (task_id, requester_name) VALUES (?,?)");
$stmt->execute([$taskId, $requester]);

flash('Completion request sent. Waiting for admin verification.');
header('Location: task.php?id=' . $taskId);
exit;
