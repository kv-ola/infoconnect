<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/includes/portal_helpers.php';
$senior = requireSeniorPortalUser();
$history = [];
$query = $databaseConnection->prepare('SELECT e.title, e.event_date, e.location, er.status AS registration_status, er.registered_at, ea.attendance_status
	FROM event_registrations er JOIN events e ON e.id = er.event_id
	LEFT JOIN event_attendance ea ON ea.registration_id = er.id AND ea.senior_id = er.senior_id
	WHERE er.senior_id = ? ORDER BY e.event_date DESC, er.registered_at DESC');
$query->bind_param('i', $senior['profile_id']);
$query->execute();
$result = $query->get_result();
while ($row = $result->fetch_assoc()) { $history[] = $row; }
$query->close();
$attendedCount = count(array_filter($history, static fn($item) => $item['attendance_status'] === 'present'));
seniorPortalPageStart('History', $senior['name'], 'Review your event registration and attendance history.');
?>
<div class="portal-form-grid">
	<div class="portal-card"><h3>Event requests</h3><p class="portal-count"><?php echo count($history); ?></p></div>
	<div class="portal-card"><h3>Events attended</h3><p class="portal-count"><?php echo $attendedCount; ?></p></div>
</div>
<section class="portal-card" aria-label="Event history">
	<?php if (!$history): ?><div class="portal-empty"><strong>No event history yet</strong>Your registrations and attendance will appear here.</div>
	<?php else: ?><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Event</th><th>Date</th><th>Venue</th><th>Registration</th><th>Attendance</th></tr></thead><tbody>
		<?php foreach ($history as $item): ?><tr><td><?php echo seniorEscape($item['title']); ?></td><td><?php echo seniorEscape(date('M j, Y', strtotime($item['event_date']))); ?></td><td><?php echo seniorEscape($item['location']); ?></td><td><span class="portal-badge <?php echo seniorEscape($item['registration_status']); ?>"><?php echo $item['registration_status'] === 'approved' ? 'Registered' : seniorEscape($item['registration_status']); ?></span></td><td><?php if ($item['attendance_status']): ?><span class="portal-badge <?php echo seniorEscape($item['attendance_status']); ?>"><?php echo seniorEscape($item['attendance_status']); ?></span><?php else: ?>&mdash;<?php endif; ?></td></tr><?php endforeach; ?>
	</tbody></table></div><?php endif; ?>
</section>
<?php seniorPortalPageEnd(); ?>
