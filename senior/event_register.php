<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/activity_log.php';
requireLogin();
require_once __DIR__ . '/includes/portal_helpers.php';
$senior = requireSeniorPortalUser();
$today = date('Y-m-d');
$error = '';
$eventId = filter_var($_POST['event_id'] ?? $_GET['event_id'] ?? null, FILTER_VALIDATE_INT);
if (!$eventId || $eventId < 1) {
	http_response_code(404);
	exit('Event not found.');
}
if (empty($_SESSION['senior_event_register_csrf'])) {
	$_SESSION['senior_event_register_csrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
	if (!hash_equals($_SESSION['senior_event_register_csrf'], $token)) {
		$error = 'This page expired. Refresh and review the notice again.';
	} else {
		try {
			$databaseConnection->begin_transaction();
			$eventLock = $databaseConnection->prepare("SELECT id, event_date FROM events WHERE id = ? AND status IN ('approved', 'published') FOR UPDATE");
			$eventLock->bind_param('i', $eventId);
			$eventLock->execute();
			$eventRecord = $eventLock->get_result()->fetch_assoc();
			$eventLock->close();
			if (!$eventRecord || $eventRecord['event_date'] < $today) {
				throw new DomainException('This event is no longer open for registration.');
			}

			$existingQuery = $databaseConnection->prepare('SELECT id, status FROM event_registrations WHERE event_id = ? AND senior_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
			$existingQuery->bind_param('ii', $eventId, $senior['profile_id']);
			$existingQuery->execute();
			$existing = $existingQuery->get_result()->fetch_assoc();
			$existingQuery->close();
			if ($existing && in_array($existing['status'], ['pending', 'approved'], true)) {
				throw new DomainException('You are already registered for this event.');
			}
			if ($existing) {
				$registrationId = (int) $existing['id'];
				$update = $databaseConnection->prepare("UPDATE event_registrations SET status = 'approved', registered_at = CURRENT_TIMESTAMP, remarks = NULL WHERE id = ?");
				$update->bind_param('i', $registrationId);
				$update->execute();
				$update->close();
			} else {
				$insert = $databaseConnection->prepare("INSERT INTO event_registrations (event_id, senior_id, status) VALUES (?, ?, 'approved')");
				$insert->bind_param('ii', $eventId, $senior['profile_id']);
				$insert->execute();
				$registrationId = (int) $databaseConnection->insert_id;
				$insert->close();
			}
			writeActivityLog($databaseConnection, $senior['user_id'], 'register', 'Events', 'Registered for event #' . $eventId . '.', $eventId);
			$databaseConnection->commit();
			$_SESSION['senior_event_register_csrf'] = bin2hex(random_bytes(32));
			header('Location: /BMirk/senior/event_notice.php?registration_id=' . $registrationId, true, 303);
			exit;
		} catch (Throwable $exception) {
			$databaseConnection->rollback();
			if ($exception instanceof DomainException) {
				$error = $exception->getMessage();
			} else {
				error_log('Senior event registration failed: ' . $exception->getMessage());
				$error = 'Unable to complete your registration. Please try again.';
			}
		}
	}
}

$eventQuery = $databaseConnection->prepare("SELECT id, title, description, event_date, start_time, end_time, location FROM events WHERE id = ? AND status IN ('approved', 'published') AND event_date >= ? LIMIT 1");
$eventQuery->bind_param('is', $eventId, $today);
$eventQuery->execute();
$event = $eventQuery->get_result()->fetch_assoc();
$eventQuery->close();
if (!$event) {
	http_response_code(404);
	exit('This event is no longer open for registration.');
}
$existingQuery = $databaseConnection->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND senior_id = ? AND status IN ('pending', 'approved') ORDER BY id DESC LIMIT 1");
$existingQuery->bind_param('ii', $eventId, $senior['profile_id']);
$existingQuery->execute();
$existingRegistration = $existingQuery->get_result()->fetch_assoc();
$existingQuery->close();
$timeText = date('g:i A', strtotime($event['start_time']));
if ($event['end_time']) { $timeText .= ' - ' . date('g:i A', strtotime($event['end_time'])); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Registration Notice | InfoConnect</title>
	<link rel="stylesheet" href="/BMirk/senior/assets/event_notice.css?v=1">
</head>
<body>
	<main class="notice-sheet">
		<header class="notice-header"><div class="notice-brand">InfoConnect</div><p>Office for Senior Citizens Affairs</p><h1>Event Registration Notice</h1></header>
		<p class="notice-intro">Review this notice before confirming your registration.</p>
		<?php if ($error !== ''): ?><p class="notice-message notice-error" role="alert"><?php echo seniorEscape($error); ?></p><?php endif; ?>
		<table class="notice-details"><tbody>
			<tr><th>Senior</th><td><?php echo seniorEscape($senior['name']); ?></td></tr>
			<tr><th>Senior ID</th><td><?php echo seniorEscape($senior['profile']['senior_citizen_id']); ?></td></tr>
			<tr><th>Event</th><td><?php echo seniorEscape($event['title']); ?></td></tr>
			<tr><th>Date</th><td><?php echo seniorEscape(date('F j, Y', strtotime($event['event_date']))); ?></td></tr>
			<tr><th>Time</th><td><?php echo seniorEscape($timeText); ?></td></tr>
			<tr><th>Venue</th><td><?php echo seniorEscape($event['location']); ?></td></tr>
		</tbody></table>
		<?php if ($event['description']): ?><p class="notice-message"><?php echo nl2br(seniorEscape($event['description'])); ?></p><?php endif; ?>
		<?php if ($existingRegistration): ?>
			<p class="notice-message">You are already registered for this event.</p>
			<div class="notice-controls"><a href="/BMirk/senior/events.php">Back to Events</a></div>
		<?php else: ?>
			<p class="notice-message">Click Register below to confirm your place. Your registration will be completed immediately.</p>
			<form class="notice-controls" method="post" action="/BMirk/senior/event_register.php">
				<input type="hidden" name="csrf_token" value="<?php echo seniorEscape($_SESSION['senior_event_register_csrf']); ?>">
				<input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>">
				<a href="/BMirk/senior/events.php">Back to Events</a>
				<button type="submit">Register</button>
			</form>
		<?php endif; ?>
	</main>
<script src="/BMirk/assets/unsaved_changes.js?v=1" defer></script>
</body>
</html>
