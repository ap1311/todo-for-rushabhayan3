<?php
require_once __DIR__ . '/includes/init.php';
require_admin();
verify_csrf();

$requestId = (int)($_POST['request_id'] ?? 0);
$decision = ($_POST['decision'] ?? '') === 'accepted' ? 'accepted' : 'rejected';

$stmt = db()->prepare("SELECT * FROM completion_requests WHERE id = ?");
$stmt->execute([$requestId]);
$req = $stmt->fetch();

if (!$req || $req['status'] !== 'pending') {
    flash('Request not found or already handled.', 'error');
    header('Location: admin.php');
    exit;
}

$user = current_user();
if ($decision === 'accepted') {
    db()->prepare("UPDATE tasks SET status='completed' WHERE id=?")->execute([$req['task_id']]);
    db()->prepare("UPDATE completion_requests SET status='superseded', reviewed_by=?, reviewed_at=datetime('now') WHERE task_id=? AND status='pending' AND id<>?")
        ->execute([$user['full_name'], $req['task_id'], $requestId]);
    db()->prepare("UPDATE edit_requests SET status='superseded', reviewed_by=?, reviewed_at=datetime('now') WHERE task_id=? AND status='pending'")
        ->execute([$user['full_name'], $req['task_id']]);
}

db()->prepare("UPDATE completion_requests SET status=?, reviewed_by=?, reviewed_at=datetime('now') WHERE id=?")
    ->execute([$decision, $user['full_name'], $requestId]);

flash('Completion request ' . $decision . ($decision === 'accepted' ? ' — task marked completed.' : '.'), $decision === 'accepted' ? 'success' : 'info');
header('Location: admin.php');
exit;
