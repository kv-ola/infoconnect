<?php
global $databaseConnection;
$seniorHeaderName = trim((string) ($seniorFirstName ?? $_SESSION['account_id'] ?? 'Senior'));
if ($seniorHeaderName === '') {
	$seniorHeaderName = 'Senior';
}
$seniorHeaderAccountId = (string) ($_SESSION['account_id'] ?? '');
$seniorHeaderNotifications = [];
$seniorHeaderUnreadCount = 0;
$seniorHeaderInboxError = false;
try {
	$seniorHeaderUserId = (int) ($_SESSION['user_id'] ?? 0);
	$seniorNotificationStatement = $databaseConnection->prepare(
		'SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5'
	);
	$seniorNotificationStatement->bind_param('i', $seniorHeaderUserId);
	$seniorNotificationStatement->execute();
	$seniorNotificationResult = $seniorNotificationStatement->get_result();
	while ($seniorNotification = $seniorNotificationResult->fetch_assoc()) {
		$seniorHeaderNotifications[] = $seniorNotification;
	}
	$seniorNotificationStatement->close();
	$seniorUnreadStatement = $databaseConnection->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0');
	$seniorUnreadStatement->bind_param('i', $seniorHeaderUserId);
	$seniorUnreadStatement->execute();
	$seniorHeaderUnreadCount = (int) ($seniorUnreadStatement->get_result()->fetch_assoc()['total'] ?? 0);
	$seniorUnreadStatement->close();
} catch (Throwable $exception) {
	error_log('Unable to load senior header inbox: ' . $exception->getMessage());
	$seniorHeaderInboxError = true;
}
?>
<link rel="stylesheet" href="/BMirk/staff/includes/assets/header.css?v=8">
<link rel="stylesheet" href="/BMirk/senior/assets/header.css?v=2">

<header class="staff-header" aria-label="InfoConnect senior toolbar">
	<a class="staff-header-brand" href="/BMirk/senior/home.php" aria-label="InfoConnect senior home">
		<img src="/BMirk/auth/assets/images/infoconnect-mark-white.png" alt="">
		<span class="staff-header-divider" aria-hidden="true"></span>
		<span class="staff-header-wordmark"><strong>INFO<span>CONNECT</span></strong><small>Office for Senior Citizens Affairs</small><small>Mandaluyong City</small></span>
	</a>
	<div class="staff-header-tools">
		<div class="staff-header-date" aria-live="polite">
			<span><strong id="senior-date"><?php echo date('F j, Y'); ?></strong><small><span id="senior-weekday"><?php echo date('l'); ?></span>, <span id="senior-time"><?php echo date('h:i:s A'); ?></span></small></span>
		</div>
		<span class="staff-header-separator" aria-hidden="true"></span>
		<div class="staff-header-popover-container senior-inbox-container">
			<button class="staff-header-notification senior-inbox-toggle" type="button" aria-label="Open inbox<?php echo $seniorHeaderUnreadCount > 0 ? ', ' . number_format($seniorHeaderUnreadCount) . ' unread' : ''; ?>" title="Open inbox" aria-haspopup="true" aria-expanded="false" aria-controls="senior-inbox-menu">
				<svg class="senior-inbox-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4.5" width="18" height="15" rx="2.2"/><path d="m4 6 8 6.2L20 6M4 18l5.5-4.5M20 18l-5.5-4.5"/></svg>
				<?php if ($seniorHeaderUnreadCount > 0): ?><span class="senior-inbox-badge"><?php echo $seniorHeaderUnreadCount > 99 ? '99+' : $seniorHeaderUnreadCount; ?></span><?php endif; ?>
			</button>
			<div class="staff-header-popover staff-header-notifications-menu senior-inbox-menu" id="senior-inbox-menu" aria-label="Recent inbox notifications" hidden>
				<div class="senior-inbox-menu-heading"><strong>Inbox</strong><span><?php echo $seniorHeaderUnreadCount; ?> unread</span></div>
				<?php if ($seniorHeaderInboxError): ?>
					<p class="senior-inbox-empty" role="status">Inbox is temporarily unavailable.</p>
				<?php elseif (!$seniorHeaderNotifications): ?>
					<p class="senior-inbox-empty" role="status">Your inbox is empty.</p>
				<?php else: ?>
					<div class="senior-inbox-items">
						<?php foreach ($seniorHeaderNotifications as $notification): ?>
							<a class="senior-inbox-item<?php echo empty($notification['is_read']) ? ' unread' : ''; ?>" href="/BMirk/senior/inbox.php">
								<strong><?php echo htmlspecialchars((string) $notification['title'], ENT_QUOTES, 'UTF-8'); ?><?php if (empty($notification['is_read'])): ?><span>New</span><?php endif; ?></strong>
								<small><?php echo htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8'); ?></small>
								<time><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($notification['created_at'])), ENT_QUOTES, 'UTF-8'); ?></time>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<a class="senior-inbox-view-all" href="/BMirk/senior/inbox.php">View all inbox messages</a>
			</div>
		</div>
		<div class="staff-header-popover-container">
			<button class="staff-header-account" type="button" aria-label="Senior account menu" aria-haspopup="true" aria-expanded="false" aria-controls="senior-account-menu">
				<span class="staff-header-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c.5-4 3.4-6 8-6s7.5 2 8 6"/></svg></span>
				<span class="staff-header-account-copy"><strong><?php echo htmlspecialchars($seniorHeaderName, ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($seniorHeaderAccountId, ENT_QUOTES, 'UTF-8'); ?></small></span>
				<svg class="staff-header-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
			</button>
			<nav class="staff-header-popover staff-header-account-menu" id="senior-account-menu" aria-label="Senior account links" hidden>
				<a href="/BMirk/senior/settings.php#my-profile">My Profile</a>
				<a href="/BMirk/senior/settings.php#change-password">Change Password</a>
				<a href="/BMirk/auth/logout.php?portal=senior">Logout</a>
			</nav>
		</div>
	</div>
</header>

<script>
	(function () {
		function updateSeniorHeaderClock() {
			const now = new Date();
			const dateElement = document.getElementById('senior-date');
			const weekdayElement = document.getElementById('senior-weekday');
			const timeElement = document.getElementById('senior-time');
			if (dateElement) dateElement.textContent = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
			if (weekdayElement) weekdayElement.textContent = now.toLocaleDateString('en-US', { weekday: 'long' });
			if (timeElement) timeElement.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
		}
		updateSeniorHeaderClock();
		window.setInterval(updateSeniorHeaderClock, 1000);
	})();
</script>
<script>
	(function () {
		var inboxButton = document.querySelector('.senior-inbox-toggle');
		var inboxMenu = document.getElementById('senior-inbox-menu');
		var accountButton = document.querySelector('.staff-header-account[aria-controls="senior-account-menu"]');
		var accountMenu = document.getElementById('senior-account-menu');
		function closeMenu(button, menu) {
			if (!button || !menu) return;
			button.setAttribute('aria-expanded', 'false');
			menu.hidden = true;
		}
		function toggleMenu(button, menu, otherButton, otherMenu) {
			if (!button || !menu) return;
			var willOpen = button.getAttribute('aria-expanded') !== 'true';
			closeMenu(otherButton, otherMenu);
			button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			menu.hidden = !willOpen;
		}
		if (inboxButton && inboxMenu) inboxButton.addEventListener('click', function () { toggleMenu(inboxButton, inboxMenu, accountButton, accountMenu); });
		if (accountButton && accountMenu) accountButton.addEventListener('click', function () { toggleMenu(accountButton, accountMenu, inboxButton, inboxMenu); });
		document.addEventListener('click', function (event) {
			if (!event.target.closest('.staff-header-popover-container')) {
				closeMenu(inboxButton, inboxMenu);
				closeMenu(accountButton, accountMenu);
			}
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeMenu(inboxButton, inboxMenu);
				closeMenu(accountButton, accountMenu);
			}
		});
	})();
</script>
