<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/includes/portal_helpers.php';
$senior = requireSeniorPortalUser();
$registrationId = filter_var($_GET['registration_id'] ?? null, FILTER_VALIDATE_INT);
if (!$registrationId || $registrationId < 1) {
	http_response_code(404);
	exit('Registration notice not found.');
}
$query = $databaseConnection->prepare("SELECT er.id, er.status, er.registered_at, er.permit_number, e.title, e.event_date, e.start_time, e.end_time, e.location, sp.senior_citizen_id
	FROM event_registrations er
	JOIN events e ON e.id = er.event_id
	JOIN senior_profiles sp ON sp.id = er.senior_id
	WHERE er.id = ? AND er.senior_id = ? AND er.status = 'approved' LIMIT 1");
$query->bind_param('ii', $registrationId, $senior['profile_id']);
$query->execute();
$notice = $query->get_result()->fetch_assoc();
$query->close();
if (!$notice) {
	http_response_code(404);
	exit('Registration notice not found.');
}
$timeText = date('g:i A', strtotime($notice['start_time']));
if ($notice['end_time']) { $timeText .= ' - ' . date('g:i A', strtotime($notice['end_time'])); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Event Registration Notice | InfoConnect</title>
	<link rel="stylesheet" href="/BMirk/senior/assets/event_notice.css?v=1">
</head>
<body>
	<main class="notice-sheet">
		<header class="notice-header"><div class="notice-brand">InfoConnect</div><p>Office for Senior Citizens Affairs</p><h1>Event Registration Notice</h1></header>
		<p class="notice-intro">Please keep this notice and present it to OSCA staff if requested.</p>
		<table class="notice-details"><tbody>
			<tr><th>Senior</th><td><?php echo seniorEscape($senior['name']); ?></td></tr>
			<tr><th>Senior ID</th><td><?php echo seniorEscape($notice['senior_citizen_id']); ?></td></tr>
			<tr><th>Event</th><td><?php echo seniorEscape($notice['title']); ?></td></tr>
			<tr><th>Date</th><td><?php echo seniorEscape(date('F j, Y', strtotime($notice['event_date']))); ?></td></tr>
			<tr><th>Time</th><td><?php echo seniorEscape($timeText); ?></td></tr>
			<tr><th>Venue</th><td><?php echo seniorEscape($notice['location']); ?></td></tr>
			<tr><th>Registration status</th><td><strong>Registered</strong></td></tr>
			<?php if (!empty($notice['permit_number'])): ?><tr><th>Permit number</th><td><?php echo seniorEscape($notice['permit_number']); ?></td></tr><?php endif; ?>
			<tr><th>Request submitted</th><td><?php echo seniorEscape(date('F j, Y g:i A', strtotime($notice['registered_at']))); ?></td></tr>
		</tbody></table>
		<p class="notice-message">Your event registration is confirmed. Please arrive at the venue on time.</p>
		<footer class="notice-footer"><span>InfoConnect - Senior Citizen Services</span><span>Registration #<?php echo (int) $notice['id']; ?></span></footer>
		<div class="notice-controls"><button type="button" onclick="window.print()">Print Notice</button><a href="/BMirk/senior/events.php">Back to Events</a></div>
	</main>
</body>
</html>
