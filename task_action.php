<?php
require_once __DIR__ . '/includes/init.php';
require_admin();
verify_csrf();

$action = $_POST['action'] ?? '';
$taskId = (int)($_POST['task_id'] ?? 0);
$user = current_user();

$task = db()->prepare("SELECT * FROM tasks WHERE id = ?");
$task->execute([$taskId]);
$task = $task->fetch();

if (!$task) {
    flash('Task not found.', 'error');
    header('Location: index.php');
    exit;
}

if ($action === 'delete') {
    db()->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    flash('Task deleted.', 'info');
    header('Location: index.php');
} elseif ($action === 'complete') {
    db()->prepare("UPDATE tasks SET status='completed' WHERE id=?")->execute([$taskId]);
    db()->prepare("UPDATE completion_requests SET status='superseded', reviewed_by=?, reviewed_at=datetime('now') WHERE task_id=? AND status='pending'")
        ->execute([$user['full_name'], $taskId]);
    db()->prepare("UPDATE edit_requests SET status='superseded', reviewed_by=?, reviewed_at=datetime('now') WHERE task_id=? AND status='pending'")
        ->execute([$user['full_name'], $taskId]);
    flash('Task marked as completed.');
    header('Location: task.php?id=' . $taskId);
} elseif ($action === 'reopen') {
    db()->prepare("UPDATE tasks SET status='pending' WHERE id=?")->execute([$taskId]);
    db()->prepare("UPDATE completion_requests SET status='superseded', reviewed_by=?, reviewed_at=datetime('now') WHERE task_id=? AND status='accepted'")
        ->execute([$user['full_name'], $taskId]);
    flash('Task reopened.', 'info');
    header('Location: task.php?id=' . $taskId);
} else {
    header('Location: index.php');
}
exit;
