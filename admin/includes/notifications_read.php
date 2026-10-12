<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || ($_SESSION['role'] ?? '') !== 'admin'
    || empty($_SESSION['user_id'])) {
	http_response_code(403);
	echo json_encode(['ok' => false]);
	exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$token = is_array($payload) ? ($payload['csrf_token'] ?? '') : '';
$keys = is_array($payload) ? ($payload['keys'] ?? []) : [];
if (!is_string($token)
    || empty($_SESSION['admin_notifications_token'])
    || !hash_equals($_SESSION['admin_notifications_token'], $token)
    || !is_array($keys)) {
	http_response_code(400);
	echo json_encode(['ok' => false]);
	exit;
}

$insert = $databaseConnection->prepare(
	'INSERT IGNORE INTO admin_notification_reads (admin_user_id, notification_key, read_at) VALUES (?, ?, NOW())'
);
$adminId = (int) $_SESSION['user_id'];
$key = '';
$insert->bind_param('is', $adminId, $key);
foreach (array_slice($keys, 0, 500) as $submittedKey) {
	if (!is_string($submittedKey) || strlen($submittedKey) > 100
		|| !preg_match('/^(approval|support|activity):[a-zA-Z0-9:_-]+$/', $submittedKey)) {
		continue;
	}
	$key = $submittedKey;
	$insert->execute();
}
$insert->close();

echo json_encode(['ok' => true]);
