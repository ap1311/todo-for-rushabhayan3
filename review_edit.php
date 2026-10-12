<?php
require_once __DIR__ . '/includes/init.php';
require_admin();
verify_csrf();

$requestId = (int)($_POST['request_id'] ?? 0);
$decision = ($_POST['decision'] ?? '') === 'accepted' ? 'accepted' : 'rejected';

$stmt = db()->prepare("SELECT * FROM edit_requests WHERE id = ?");
$stmt->execute([$requestId]);
$req = $stmt->fetch();

if (!$req || $req['status'] !== 'pending') {
    flash('Request not found or already handled.', 'error');
    header('Location: admin.php');
    exit;
}

$task = db()->prepare("SELECT status FROM tasks WHERE id = ?");
$task->execute([$req['task_id']]);
$task = $task->fetch();

if ($task && $task['status'] === 'completed') {
    flash('This task is already completed, so its pending edit requests were closed.', 'info');
    db()->prepare("UPDATE edit_requests SET status='superseded', reviewed_by=?, reviewed_at=datetime('now') WHERE task_id=? AND status='pending'")
        ->execute([current_user()['full_name'], $req['task_id']]);
    header('Location: admin.php');
    exit;
}

$user = current_user();
if ($decision === 'accepted') {
    $payload = json_decode($req['changes_json'], true) ?: [];
    $apply = $payload['apply'] ?? null;
    if ($apply) {
        db()->prepare("UPDATE tasks SET title=?, description=?, priority=?, deadline_date=?, deadline_time=?, has_time=?, assigned_to=? WHERE id=?")
            ->execute([
                $apply['title'], $apply['description'], $apply['priority'],
                $apply['deadline_date'], $apply['deadline_time'], (int)$apply['has_time'],
                $apply['assigned_to'], $req['task_id'],
            ]);
    }
    // Any other pending edit requests for this task are superseded
    db()->prepare("UPDATE edit_requests SET status='superseded', reviewed_by=?, reviewed_at=datetime('now') WHERE task_id=? AND status='pending' AND id<>?")
        ->execute([$user['full_name'], $req['task_id'], $requestId]);
}

db()->prepare("UPDATE edit_requests SET status=?, reviewed_by=?, reviewed_at=datetime('now') WHERE id=?")
    ->execute([$decision, $user['full_name'], $requestId]);

flash('Edit request ' . $decision . ($decision === 'accepted' ? ' — task updated.' : '.'), $decision === 'accepted' ? 'success' : 'info');
header('Location: admin.php');
exit;
