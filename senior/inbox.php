<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/includes/portal_helpers.php';
$senior = requireSeniorPortalUser();
$userId = $senior['user_id'];
$notice = '';
$noticeError = false;
if (empty($_SESSION['senior_inbox_csrf'])) {
	$_SESSION['senior_inbox_csrf'] = bin2hex(random_bytes(32));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
	$notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
	if (!hash_equals($_SESSION['senior_inbox_csrf'], $token) || !$notificationId || $notificationId < 1) {
		$notice = 'This request expired. Refresh the page and try again.';
		$noticeError = true;
	} else {
		$statement = $databaseConnection->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ? AND is_read = 0');
		$statement->bind_param('ii', $notificationId, $userId);
		$statement->execute();
		$statement->close();
		$notice = 'Notification marked as read.';
		$_SESSION['senior_inbox_csrf'] = bin2hex(random_bytes(32));
	}
}
$notifications = [];
$statement = $databaseConnection->prepare('SELECT id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$statement->bind_param('i', $userId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) { $notifications[] = $row; }
$statement->close();
seniorPortalPageStart('Inbox', $senior['name'], 'Messages and updates for your account.');
if ($notice !== ''): ?><div class="portal-notice<?php echo $noticeError ? ' error' : ''; ?>" role="status"><?php echo seniorEscape($notice); ?></div><?php endif; ?>
<section class="portal-card" aria-label="Notifications">
	<?php if (!$notifications): ?>
		<div class="portal-empty"><strong>Your inbox is empty</strong>You do not have any notifications yet.</div>
	<?php else: ?>
		<div class="portal-list">
			<?php foreach ($notifications as $notification): ?>
				<article class="portal-row">
					<div><h3><?php echo seniorEscape($notification['title']); ?> <?php if (!$notification['is_read']): ?><span class="portal-badge">New</span><?php endif; ?></h3><p><?php echo nl2br(seniorEscape($notification['message'])); ?></p><span class="portal-date"><?php echo seniorEscape(date('M j, Y g:i A', strtotime($notification['created_at']))); ?></span></div>
					<?php if (!$notification['is_read']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo seniorEscape($_SESSION['senior_inbox_csrf']); ?>"><input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>"><button class="portal-button" type="submit">Mark as read</button></form><?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
<?php seniorPortalPageEnd(); ?>
