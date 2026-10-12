<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/includes/portal_helpers.php';
$senior = requireSeniorPortalUser();
$today = date('Y-m-d');
$events = [];
$query = $databaseConnection->prepare("SELECT e.id, e.title, e.description, e.event_date, e.start_time, e.end_time, e.location,
	(SELECT er.status FROM event_registrations er WHERE er.event_id = e.id AND er.senior_id = ? ORDER BY er.id DESC LIMIT 1) AS registration_status
	FROM events e WHERE e.status IN ('approved', 'published') AND e.event_date >= ? ORDER BY e.event_date, e.start_time, e.id");
$query->bind_param('is', $senior['profile_id'], $today);
$query->execute();
$result = $query->get_result();
while ($row = $result->fetch_assoc()) { $events[] = $row; }
$query->close();
seniorPortalPageStart('Events', $senior['name'], 'Browse upcoming events and review the notice before registering.');
?>
<section class="portal-events-section" aria-label="Upcoming events">
	<?php if (!$events): ?><div class="portal-empty"><strong>No upcoming events</strong>New events will appear here when they are published.</div>
	<?php else: ?><div class="portal-event-grid">
		<?php foreach ($events as $event): ?>
			<article class="portal-row portal-event-card">
				<div><h3><?php echo seniorEscape($event['title']); ?></h3><p><strong><?php echo seniorEscape(date('F j, Y', strtotime($event['event_date']))); ?></strong> · <?php echo seniorEscape(date('g:i A', strtotime($event['start_time']))); ?><?php if ($event['end_time']): ?>–<?php echo seniorEscape(date('g:i A', strtotime($event['end_time']))); ?><?php endif; ?></p><p><?php echo seniorEscape($event['location']); ?></p><?php if ($event['description']): ?><p><?php echo nl2br(seniorEscape($event['description'])); ?></p><?php endif; ?></div>
				<?php if (in_array($event['registration_status'], ['pending', 'approved'], true)): ?><span class="portal-badge approved">Registered</span>
				<?php else: ?><a class="portal-button" href="/BMirk/senior/event_register.php?event_id=<?php echo (int) $event['id']; ?>">Register</a><?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div><?php endif; ?>
</section>
<?php seniorPortalPageEnd(); ?>
