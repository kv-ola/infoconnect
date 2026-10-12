<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'senior') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$page_title = 'Home';
$seniorId = (int) ($_SESSION['user_id'] ?? 0);
$seniorFirstName = trim((string) ($_SESSION['account_id'] ?? 'Senior'));
$upcomingEvents = [];
$announcements = [];
$homeError = '';
$today = new DateTimeImmutable('today');
$monthInput = $_GET['month'] ?? $today->format('Y-m');
$monthDate = is_string($monthInput) ? DateTimeImmutable::createFromFormat('!Y-m', $monthInput) : false;
if (!$monthDate || $monthDate->format('Y-m') !== $monthInput || (int) $monthDate->format('Y') < 2000 || (int) $monthDate->format('Y') > 2100) {
	$monthDate = $today->modify('first day of this month');
}
$monthDate = $monthDate->modify('first day of this month');
$monthKey = $monthDate->format('Y-m');
$monthStart = $monthDate->format('Y-m-01');
$monthEnd = $monthDate->modify('first day of next month')->format('Y-m-d');
$selectedInput = $_GET['day'] ?? '';
$selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', is_string($selectedInput) ? $selectedInput : '');
if (!$selectedDate || $selectedDate->format('Y-m') !== $monthKey || $selectedDate->format('Y-m-d') !== $selectedInput) {
	$selectedDate = $monthKey === $today->format('Y-m') ? $today : $monthDate;
}
$selectedDay = $selectedDate->format('Y-m-d');
$previousMonth = $monthDate->modify('-1 month')->format('Y-m');
$nextMonth = $monthDate->modify('+1 month')->format('Y-m');
$calendarStart = $monthDate->modify('-' . (int) $monthDate->format('w') . ' days');
$calendarEnd = $monthDate->modify('last day of this month');
$calendarCells = (int) ceil(((int) $monthDate->format('w') + (int) $calendarEnd->format('j')) / 7) * 7;

function seniorHomeEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

try {
	$profileStatement = $databaseConnection->prepare(
		'SELECT id, first_name FROM senior_profiles WHERE user_id = ? LIMIT 1'
	);
	$profileStatement->bind_param('i', $seniorId);
	$profileStatement->execute();
	$seniorProfile = $profileStatement->get_result()->fetch_assoc();
	$profileStatement->close();
	$seniorProfileId = (int) ($seniorProfile['id'] ?? 0);
	if (!$seniorProfileId) {
		throw new RuntimeException('The senior profile linked to this account could not be found.');
	}
	if (!empty($seniorProfile['first_name'])) {
		$seniorFirstName = $seniorProfile['first_name'];
	}

	$eventStatement = $databaseConnection->prepare(
		"SELECT e.id, e.title, e.event_date, e.start_time, e.end_time, e.location,
		        (SELECT er.status FROM event_registrations er
		         WHERE er.event_id = e.id AND er.senior_id = ?
		         ORDER BY er.id DESC LIMIT 1) AS registration_status
		 FROM events e
		 WHERE e.status IN ('approved', 'published', 'completed')
		   AND e.event_date >= ? AND e.event_date < ?
		 ORDER BY e.event_date, e.start_time, e.id"
	);
	$eventStatement->bind_param('iss', $seniorProfileId, $monthStart, $monthEnd);
	$eventStatement->execute();
	$eventResult = $eventStatement->get_result();
	while ($row = $eventResult->fetch_assoc()) {
		$upcomingEvents[] = $row;
	}
	$eventStatement->close();

	$announcementResult = $databaseConnection->query(
		"SELECT title, content, COALESCE(published_at, created_at) AS display_date
		 FROM announcements
		 WHERE status IN ('approved', 'published')
		   AND (published_at IS NULL OR published_at <= NOW())
		   AND (expires_at IS NULL OR expires_at >= NOW())
		 ORDER BY COALESCE(published_at, created_at) DESC
		 LIMIT 3"
	);
	while ($row = $announcementResult->fetch_assoc()) {
		$announcements[] = $row;
	}
} catch (Throwable $exception) {
	error_log('Unable to load senior home page data: ' . $exception->getMessage());
	$homeError = 'Some home page information could not be loaded. Please refresh the page later.';
}

$eventsByDay = [];
foreach ($upcomingEvents as $event) {
	$eventsByDay[$event['event_date']][] = $event;
}
$selectedEvents = $eventsByDay[$selectedDay] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo seniorHomeEscape($page_title); ?> | InfoConnect</title>
	<link rel="stylesheet" href="/BMirk/admin/home.css?v=2">
	<link rel="stylesheet" href="assets/home.css?v=4">
	<link rel="stylesheet" href="assets/portal.css?v=2">
</head>
<body class="admin-home-page senior-home-page">
	<div class="admin-layout senior-portal-layout">
		<?php include __DIR__ . '/includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/includes/header.php'; ?>
			<section class="home-content senior-home-content" aria-label="Senior home page">
				<header class="home-welcome">
					<h1><span id="senior-home-greeting">Good evening</span>, <?php echo seniorHomeEscape($seniorFirstName); ?>!</h1>
					<p>Welcome back to InfoConnect!</p>
				</header>
				<?php if ($homeError !== ''): ?><div class="home-error" role="alert"><?php echo seniorHomeEscape($homeError); ?></div><?php endif; ?>

				<section class="home-card quick-card senior-quick-actions-panel" aria-labelledby="senior-quick-actions-heading">
					<div class="card-heading">
						<div><h2 id="senior-quick-actions-heading">Quick Actions</h2><p>Go directly to the services you use most.</p></div>
					</div>
					<nav class="quick-actions" aria-label="Quick actions">
						<a class="quick-action quick-blue" href="/BMirk/senior/events.php">
							<span class="quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/><path d="M8 14h3m2 0h3m-8 3h3"/></svg></span>
							<span class="quick-copy"><strong>Browse Events</strong><small>View upcoming community events</small></span>
							<svg class="quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
						</a>
						<a class="quick-action quick-yellow" href="/BMirk/senior/inbox.php">
							<span class="quick-icon" aria-hidden="true"><svg class="quick-inbox-icon" viewBox="0 0 24 24"><rect x="3" y="4.5" width="18" height="15" rx="2.2"/><path d="m4 6 8 6.2L20 6M4 18l5.5-4.5M20 18l-5.5-4.5"/></svg></span>
							<span class="quick-copy"><strong>Open Inbox</strong><small>Read announcements and updates</small></span>
							<svg class="quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
						</a>
						<a class="quick-action quick-red" href="/BMirk/senior/history.php">
							<span class="quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5m4-1v5l3.5 2"/></svg></span>
							<span class="quick-copy"><strong>View History</strong><small>Check your past registrations</small></span>
							<svg class="quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
						</a>
						<a class="quick-action quick-purple" href="/BMirk/senior/settings.php#my-profile">
							<span class="quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c.5-4 3.4-6 8-6s7.5 2 8 6"/></svg></span>
							<span class="quick-copy"><strong>My Profile</strong><small>Review your account information</small></span>
							<svg class="quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
						</a>
					</nav>
				</section>

				<section class="home-card senior-events-panel" aria-labelledby="upcoming-events-heading">
					<header class="senior-panel-heading">
						<div><h1 id="upcoming-events-heading">Upcoming Events</h1><p>View and register to upcoming events.</p></div>
						<nav class="senior-calendar-controls" aria-label="Calendar month">
							<a class="senior-month-arrow" href="?month=<?php echo seniorHomeEscape($previousMonth); ?>" aria-label="Previous month">&#x2039;</a>
							<span class="senior-month-label"><?php echo seniorHomeEscape($monthDate->format('F Y')); ?></span>
							<a class="senior-month-arrow" href="?month=<?php echo seniorHomeEscape($nextMonth); ?>" aria-label="Next month">&#x203A;</a>
						</nav>
						<a class="senior-primary-link" href="/BMirk/senior/events.php">View All Events</a>
					</header>
					<div class="senior-calendar-wrap">
						<div class="senior-calendar" aria-label="<?php echo seniorHomeEscape($monthDate->format('F Y')); ?> event calendar">
							<div class="senior-weekday-row" aria-hidden="true"><?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?><span><?php echo $weekday; ?></span><?php endforeach; ?></div>
							<div class="senior-calendar-days">
								<?php for ($cell = 0; $cell < $calendarCells; $cell++):
									$cellDate = $calendarStart->modify('+' . $cell . ' days');
									$cellKey = $cellDate->format('Y-m-d');
									$isCurrentMonth = $cellDate->format('Y-m') === $monthKey;
									$isSelected = $cellKey === $selectedDay;
									$dayEvents = $eventsByDay[$cellKey] ?? [];
									$hasEvents = !empty($dayEvents);
								?>
								<div class="senior-calendar-cell">
									<a class="senior-calendar-day<?php echo $isCurrentMonth ? '' : ' outside-month'; ?><?php echo $isSelected ? ' selected' : ''; ?>" href="?month=<?php echo seniorHomeEscape($cellDate->format('Y-m')); ?>&amp;day=<?php echo seniorHomeEscape($cellKey); ?>" aria-label="<?php echo seniorHomeEscape($cellDate->format('F j, Y')); ?><?php echo $hasEvents ? ', events scheduled' : ''; ?>"<?php echo $isSelected ? ' aria-current="date"' : ''; ?>>
										<span><?php echo seniorHomeEscape($cellDate->format('j')); ?></span><?php if ($hasEvents): ?><i aria-hidden="true"></i><?php endif; ?>
									</a>
									<?php if ($isSelected && $hasEvents): ?>
										<div class="senior-calendar-popover" role="status" aria-label="Events on <?php echo seniorHomeEscape($selectedDate->format('F j, Y')); ?>">
											<h2>Events on <?php echo seniorHomeEscape($selectedDate->format('F j')); ?></h2>
											<?php foreach ($dayEvents as $event): ?>
												<article class="senior-calendar-event">
												<strong><?php echo seniorHomeEscape($event['title']); ?></strong>
												<span><?php if (!empty($event['start_time'])): ?><?php echo seniorHomeEscape(date('g:i A', strtotime($event['start_time']))); ?><?php if (!empty($event['end_time'])): ?>–<?php echo seniorHomeEscape(date('g:i A', strtotime($event['end_time']))); ?><?php endif; ?><?php endif; ?><?php if (!empty($event['location'])): ?><?php if (!empty($event['start_time'])): ?> · <?php endif; ?><?php echo seniorHomeEscape($event['location']); ?><?php endif; ?></span>
													<?php if ($event['status'] === 'completed'): ?><small class="senior-registration-state completed">Completed</small><?php elseif (in_array($event['registration_status'], ['pending', 'approved'], true)): ?><small class="senior-registration-state"><?php echo $event['registration_status'] === 'approved' ? 'Registered' : 'Pending'; ?></small><?php elseif ($event['event_date'] >= $today->format('Y-m-d')): ?><a class="senior-primary-link" href="/BMirk/senior/event_register.php?event_id=<?php echo (int) $event['id']; ?>">Register</a><?php else: ?><small class="senior-registration-state closed">Registration closed</small><?php endif; ?>
											</article>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endfor; ?>
						</div>
					</div>
					</div>
				</section>

				<section class="home-card senior-announcements-panel" id="latest-announcements" aria-labelledby="latest-announcements-heading">
					<header class="senior-panel-heading senior-announcements-heading">
						<div><h2 id="latest-announcements-heading">Latest Announcements</h2><p>Stay updated with the latest announcements from OSCA.</p></div>
						<a class="senior-secondary-link" href="/BMirk/senior/inbox.php">View All Announcements</a>
					</header>
					<?php if (!$announcements): ?>
						<p class="senior-empty-state">There are no current announcements.</p>
					<?php else: ?>
						<div class="senior-announcement-list">
							<?php foreach ($announcements as $announcement): ?>
								<article class="senior-announcement-item">
									<div class="senior-announcement-date"><strong><?php echo seniorHomeEscape(date('M j', strtotime($announcement['display_date']))); ?></strong><span><?php echo seniorHomeEscape(date('Y', strtotime($announcement['display_date']))); ?></span></div>
									<div class="senior-announcement-copy"><h3><?php echo seniorHomeEscape($announcement['title']); ?></h3><p><?php echo nl2br(seniorHomeEscape($announcement['content'])); ?></p></div>
									<a class="senior-announcement-open" href="/BMirk/senior/inbox.php" aria-label="Open announcement: <?php echo seniorHomeEscape($announcement['title']); ?>">&#x203A;</a>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			</section>
		</main>
	</div>
	<script>
		(function () {
			var greeting = document.getElementById('senior-home-greeting');
			if (!greeting) return;
			var hour = new Date().getHours();
			greeting.textContent = hour >= 5 && hour < 12 ? 'Good morning' : (hour >= 12 && hour < 18 ? 'Good afternoon' : 'Good evening');
		}());
	</script>
	<script src="/BMirk/assets/unsaved_changes.js?v=1" defer></script>
</body>
</html>
