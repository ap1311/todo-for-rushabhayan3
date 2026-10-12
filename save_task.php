<?php
require_once __DIR__ . '/includes/init.php';
verify_csrf();

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority = in_array($_POST['priority'] ?? '', ['high', 'medium', 'low'], true) ? $_POST['priority'] : 'medium';
$deadlineType = $_POST['deadline_type'] ?? 'date';
$deadlineDate = $_POST['deadline_date'] ?? '';
$deadlineTime = trim($_POST['deadline_time'] ?? '');
$assignedTo = trim($_POST['assigned_to'] ?? '');
$assignedBy = trim($_POST['assigned_by'] ?? '');

$errors = [];
if ($title === '') $errors[] = 'Task title is required.';
if ($deadlineDate === '' || !strtotime($deadlineDate)) $errors[] = 'A valid deadline date is required.';
if ($deadlineType === 'datetime') {
    if ($deadlineTime === '') $errors[] = 'Deadline time is required for date-and-time type.';
} else {
    $deadlineTime = '';
}
if ($assignedTo === '') $errors[] = 'Assigned To is required.';
if ($assignedBy === '') $errors[] = 'Assigned By is required.';

if ($errors) {
    $_SESSION['task_errors'] = $errors;
    header('Location: new_task.php');
    exit;
}

$stmt = db()->prepare("INSERT INTO tasks (title, description, priority, deadline_date, deadline_time, has_time, assigned_to, assigned_by, assigned_on)
                       VALUES (?,?,?,?,?,?,?,?,date('now'))");
$stmt->execute([
    $title, $description, $priority, $deadlineDate,
    $deadlineType === 'datetime' ? $deadlineTime : null,
    $deadlineType === 'datetime' ? 1 : 0,
    $assignedTo, $assignedBy,
]);

flash('Task "' . $title . '" created successfully.');
header('Location: index.php');
exit;
