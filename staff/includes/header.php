<?php
require_once __DIR__ . '/../../config/database.php';

$staffHeaderNotifications = [];
$staffHeaderNotificationCount = 0;
$staffHeaderNotificationError = false;
$staffHeaderUserId = (int) ($_SESSION['user_id'] ?? 0);
if (empty($_SESSION['staff_notifications_token'])) {
    $_SESSION['staff_notifications_token'] = bin2hex(random_bytes(32));
}

try {
    $databaseConnection->query(
        "CREATE TABLE IF NOT EXISTS staff_notification_reads (
            staff_user_id INT(10) UNSIGNED NOT NULL,
            notification_key VARCHAR(100) NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (staff_user_id, notification_key),
            KEY idx_staff_notification_reads_read_at (staff_user_id, read_at),
            CONSTRAINT fk_staff_notification_reads_user FOREIGN KEY (staff_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $approvalQuery = $databaseConnection->prepare(
        "SELECT ar.id, ar.request_type, ar.status, ar.remarks, ar.reviewed_at,
                CASE ar.request_type
                    WHEN 'event' THEN COALESCE(NULLIF(events.title, ''), CONCAT('Event request #', ar.reference_id))
                    WHEN 'announcement' THEN COALESCE(NULLIF(announcements.title, ''), CONCAT('Post request #', ar.reference_id))
                    WHEN 'senior' THEN COALESCE(NULLIF(TRIM(CONCAT_WS(' ', seniors.first_name, seniors.last_name)), ''), CONCAT('Senior request #', ar.reference_id))
                    ELSE CONCAT('Request #', ar.reference_id)
                END AS subject
         FROM approval_requests ar
         LEFT JOIN events ON ar.request_type = 'event' AND events.id = ar.reference_id
         LEFT JOIN announcements ON ar.request_type = 'announcement' AND announcements.id = ar.reference_id
         LEFT JOIN senior_profiles seniors ON ar.request_type = 'senior' AND seniors.id = ar.reference_id
         WHERE ar.requested_by = ? AND ar.status IN ('approved', 'rejected')
         ORDER BY COALESCE(ar.reviewed_at, ar.updated_at) DESC, ar.id DESC"
    );
    $approvalQuery->bind_param('i', $staffHeaderUserId);
    $approvalQuery->execute();
    $approvalResult = $approvalQuery->get_result();
    while ($row = $approvalResult->fetch_assoc()) {
        $type = (string) $row['request_type'];
        $status = (string) $row['status'];
        $target = $type === 'senior' ? '/BMirk/staff/seniors/accounts/seniors_accounts.php'
                : ($status === 'rejected' && in_array($type, ['event', 'announcement'], true)
                ? '/BMirk/staff/posts/posts_archive.php'
                : (in_array($type, ['event', 'announcement'], true)
                    ? '/BMirk/staff/posts/posts_history.php'
                    : '/BMirk/staff/posts/posts_list.php'));
        $staffHeaderNotifications[] = [
            'notification_type' => $type,
            'kind' => 'approval',
            'title' => ucfirst($type === 'announcement' ? 'post' : $type) . ' ' . $status,
            'subject' => $row['subject'],
            'description' => trim((string) ($row['remarks'] ?? '')),
            'created_at' => $row['reviewed_at'] ?: date('Y-m-d H:i:s'),
            'target_url' => $target,
            'notification_key' => 'approval:' . (int) $row['id'],
        ];
    }
    $approvalQuery->close();

    $supportQuery = $databaseConnection->prepare(
        "SELECT id, subject, status, handled_at, updated_at
         FROM support_requests
         WHERE requested_by = ? AND status <> 'open' AND handled_at IS NOT NULL
         ORDER BY handled_at DESC, id DESC"
    );
    $supportQuery->bind_param('i', $staffHeaderUserId);
    $supportQuery->execute();
    $supportResult = $supportQuery->get_result();
    while ($row = $supportResult->fetch_assoc()) {
        $statusLabel = $row['status'] === 'in_progress' ? 'In progress' : 'Resolved';
        $staffHeaderNotifications[] = [
            'notification_type' => 'support',
            'kind' => 'support',
            'title' => 'Help & support request updated',
            'subject' => $row['subject'],
            'description' => 'Status: ' . $statusLabel,
            'created_at' => $row['handled_at'] ?: $row['updated_at'],
            'target_url' => '/BMirk/staff/settings/settings.php?tab=support',
            'notification_key' => 'support:' . (int) $row['id'] . ':' . preg_replace('/[^0-9]/', '', (string) ($row['handled_at'] ?: $row['updated_at'])),
        ];
    }
    $supportQuery->close();

    usort($staffHeaderNotifications, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
    $readQuery = $databaseConnection->prepare('SELECT notification_key FROM staff_notification_reads WHERE staff_user_id = ?');
    $readQuery->bind_param('i', $staffHeaderUserId);
    $readQuery->execute();
    $readResult = $readQuery->get_result();
    $readKeys = [];
    while ($readRow = $readResult->fetch_assoc()) $readKeys[$readRow['notification_key']] = true;
    $readQuery->close();

    $staffToday = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $staffNewSince = (new DateTimeImmutable('-24 hours', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
    foreach ($staffHeaderNotifications as &$notification) {
        $notification['is_read'] = isset($readKeys[$notification['notification_key']]);
        $notification['day'] = substr((string) $notification['created_at'], 0, 10);
        $notification['is_new'] = (string) $notification['created_at'] >= $staffNewSince;
        if (!$notification['is_read']) $staffHeaderNotificationCount++;
    }
    unset($notification);
} catch (Throwable $exception) {
    error_log('Unable to load staff header notifications: ' . $exception->getMessage());
    $staffHeaderNotificationError = true;
}

function staffHeaderNotificationEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staffHeaderNotificationLabel(array $notification): string
{
    if ($notification['kind'] === 'support') return 'Help & support';
    return [
        'senior' => 'Senior update',
        'event' => 'Event approval',
        'announcement' => 'Post approval',
    ][$notification['notification_type']] ?? 'Request update';
}

$staffHeaderName = '';
try {
    $staffHeaderNameQuery = $databaseConnection->prepare(
        'SELECT first_name FROM staff_profiles WHERE user_id = ? LIMIT 1'
    );
    $staffHeaderNameQuery->bind_param('i', $staffHeaderUserId);
    $staffHeaderNameQuery->execute();
    $staffHeaderNameRow = $staffHeaderNameQuery->get_result()->fetch_assoc();
    $staffHeaderNameQuery->close();
    $staffHeaderName = trim((string) ($staffHeaderNameRow['first_name'] ?? ''));
} catch (Throwable $exception) {
    error_log('Unable to load staff header name: ' . $exception->getMessage());
}
if ($staffHeaderName === '') {
    $staffHeaderName = trim((string) ($staffFirstName ?? ''));
}
if ($staffHeaderName === '') {
    $staffHeaderName = trim((string) ($_SESSION['account_id'] ?? 'Staff'));
}
if ($staffHeaderName === '') {
    $staffHeaderName = 'Staff';
}
$staffHeaderAccountId = (string) ($_SESSION['account_id'] ?? '');
?>
<link rel="stylesheet" href="/BMirk/staff/includes/assets/header.css?v=10">
<header class="staff-header" aria-label="InfoConnect staff toolbar">
    <a class="staff-header-brand" href="/BMirk/staff/home.php" aria-label="InfoConnect staff home">
        <img src="/BMirk/auth/assets/images/infoconnect-mark-white.png" alt="">
        <span class="staff-header-divider" aria-hidden="true"></span>
        <span class="staff-header-wordmark"><strong>INFO<span>CONNECT</span></strong><small>Office for Senior Citizens Affairs</small><small>Mandaluyong City</small></span>
    </a>
    <div class="staff-header-tools">
        <div class="staff-header-date" aria-live="polite">
            <span><strong id="staff-date"><?php echo date('F j, Y'); ?></strong><small><span id="staff-weekday"><?php echo date('l'); ?></span>, <span id="staff-time"><?php echo date('h:i:s A'); ?></span></small></span>
        </div>
        <span class="staff-header-separator" aria-hidden="true"></span>
        <div class="staff-header-popover-container staff-header-quick-container">
            <button class="staff-header-quick" type="button" aria-label="Quick actions" aria-haspopup="true" aria-expanded="false" aria-controls="staff-quick-actions-menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <nav class="staff-header-popover staff-header-quick-menu" id="staff-quick-actions-menu" aria-label="Quick actions" hidden>
                <strong>Quick Actions</strong>
                <a href="/BMirk/staff/seniors/accounts/seniors_accounts.php"><span class="header-quick-icon quick-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span><span>Manage Seniors</span><svg class="header-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
                <a href="/BMirk/staff/posts/posts_create.php"><span class="header-quick-icon quick-green"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.5h9l4 4V21H6z"/><path d="M14 3.5v5h5M9 13h7m-7 3.5h7M12.5 12v5m-2.5-2.5h5"/></svg></span><span>Create a Post</span><svg class="header-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
                <a href="/BMirk/staff/sms/sms.php"><span class="header-quick-icon quick-purple"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 5.5h17v12h-10l-5.5 3v-3h-1.5z"/><path d="M7 9h10m-10 4h7"/></svg></span><span>Send SMS</span><svg class="header-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
                <a href="/BMirk/staff/monitoring/reports.php"><span class="header-quick-icon quick-red"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V11h4v9m4 0V5h4v15m4 0v-8h4v8M2 21h20"/></svg></span><span>Generate Report</span><svg class="header-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
            </nav>
        </div>
        <div class="staff-header-popover-container">
            <button class="staff-header-notification" type="button" aria-label="<?php echo $staffHeaderNotificationCount > 0 ? 'Notifications, ' . number_format($staffHeaderNotificationCount) . ' unread' : 'Notifications'; ?>" aria-haspopup="true" aria-expanded="false" aria-controls="staff-notifications-menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <?php if ($staffHeaderNotificationCount > 0): ?><span class="staff-header-notification-count"><?php echo $staffHeaderNotificationCount > 99 ? '99+' : number_format($staffHeaderNotificationCount); ?></span><?php endif; ?>
            </button>
            <div class="staff-header-popover staff-header-notifications-menu" id="staff-notifications-menu" aria-label="Notifications" data-read-endpoint="/BMirk/staff/includes/notifications_read.php" data-csrf="<?php echo staffHeaderNotificationEscape($_SESSION['staff_notifications_token']); ?>" data-today="<?php echo $staffToday ?? date('Y-m-d'); ?>" hidden>
                <?php if ($staffHeaderNotificationError): ?><p role="alert">Notifications are temporarily unavailable.</p><?php else: ?>
                    <div class="staff-header-notifications-heading"><strong>Notifications</strong><div class="staff-header-notifications-actions"><span><?php echo number_format($staffHeaderNotificationCount); ?> unread</span><button type="button" class="staff-header-mark-all-read" <?php echo $staffHeaderNotificationCount === 0 ? 'hidden' : ''; ?>>Mark all as read</button></div></div>
                    <div class="staff-header-notification-filters" role="tablist" aria-label="Filter notifications">
                        <button type="button" role="tab" aria-selected="true" data-notification-filter="new">New</button><button type="button" role="tab" aria-selected="false" data-notification-filter="today">Today</button><button type="button" role="tab" aria-selected="false" data-notification-filter="earlier">Earlier</button><button type="button" role="tab" aria-selected="false" data-notification-filter="unread">Unread</button><button type="button" role="tab" aria-selected="false" data-notification-filter="all">All</button>
                    </div>
                    <p class="staff-header-notification-empty" role="status" hidden>No notifications in this section.</p>
                    <div class="staff-header-notification-list">
                    <?php foreach ($staffHeaderNotifications as $notification): ?>
                        <a class="staff-header-notification-item<?php echo $notification['is_read'] ? ' is-read' : ' is-unread'; ?>" href="<?php echo staffHeaderNotificationEscape($notification['target_url']); ?>" data-notification-key="<?php echo staffHeaderNotificationEscape($notification['notification_key']); ?>" data-new="<?php echo $notification['is_new'] ? '1' : '0'; ?>" data-day="<?php echo staffHeaderNotificationEscape($notification['day']); ?>" data-unread="<?php echo $notification['is_read'] ? '0' : '1'; ?>">
                            <strong><?php echo staffHeaderNotificationEscape($notification['title']); ?></strong><span><?php echo staffHeaderNotificationEscape(staffHeaderNotificationLabel($notification)); ?> &middot; <?php echo staffHeaderNotificationEscape($notification['subject']); ?></span>
                            <?php if ($notification['description'] !== ''): ?><span class="staff-header-notification-description"><?php echo staffHeaderNotificationEscape($notification['description']); ?></span><?php endif; ?><small><?php echo staffHeaderNotificationEscape(date('M j, Y h:i A', strtotime($notification['created_at']))); ?></small>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="staff-header-popover-container">
            <button class="staff-header-account" type="button" aria-label="Staff account menu" aria-haspopup="true" aria-expanded="false" aria-controls="staff-account-menu">
                <span class="staff-header-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c.5-4 3.4-6 8-6s7.5 2 8 6"/></svg></span>
                <span class="staff-header-account-copy"><strong><?php echo htmlspecialchars($staffHeaderName, ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($staffHeaderAccountId, ENT_QUOTES, 'UTF-8'); ?></small></span>
                <svg class="staff-header-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </button>
            <nav class="staff-header-popover staff-header-account-menu" id="staff-account-menu" aria-label="Staff account links" hidden>
                <a href="/BMirk/staff/settings/settings.php?tab=personal">My Profile</a>
                <a href="/BMirk/staff/settings/settings.php?tab=security">Change Password</a>
                <a href="/BMirk/auth/logout.php?portal=staff">Logout</a>
            </nav>
        </div>
    </div>
</header>
<script>
(function () {
    var popoverButtons = document.querySelectorAll('.staff-header-quick, .staff-header-notification, .staff-header-account');
    var notificationMenu = document.getElementById('staff-notifications-menu');

    function filterNotifications(filter) {
        if (!notificationMenu) return;
        var today = notificationMenu.dataset.today;
        var items = notificationMenu.querySelectorAll('.staff-header-notification-item');
        var visibleCount = 0;
        items.forEach(function (item) {
            var show = filter === 'all'
                || (filter === 'new' && item.dataset.new === '1')
                || (filter === 'today' && item.dataset.day === today)
                || (filter === 'earlier' && item.dataset.day < today)
                || (filter === 'unread' && item.dataset.unread === '1');
            item.hidden = !show;
            if (show) visibleCount++;
        });
        var empty = notificationMenu.querySelector('.staff-header-notification-empty');
        if (empty) empty.hidden = visibleCount > 0;
        notificationMenu.querySelectorAll('[data-notification-filter]').forEach(function (tab) {
            tab.setAttribute('aria-selected', String(tab.dataset.notificationFilter === filter));
        });
    }

    function markNotificationsRead(items) {
        if (!notificationMenu || !items.length) return;
        var unreadItems = items.filter(function (item) { return item.dataset.unread === '1'; });
        var keys = unreadItems.map(function (item) { return item.dataset.notificationKey; });
        if (!keys.length) return;
        unreadItems.forEach(function (item) {
            item.dataset.unread = '0';
            item.classList.remove('is-unread');
            item.classList.add('is-read');
        });
        var remaining = notificationMenu.querySelectorAll('.staff-header-notification-item[data-unread="1"]').length;
        var badge = document.querySelector('.staff-header-notification-count');
        if (remaining === 0 && badge) badge.remove();
        else if (badge) badge.textContent = remaining > 99 ? '99+' : String(remaining);
        var bell = document.querySelector('.staff-header-notification');
        if (bell) bell.setAttribute('aria-label', remaining > 0 ? 'Notifications, ' + remaining + ' unread' : 'Notifications');
        var unreadLabel = notificationMenu.querySelector('.staff-header-notifications-actions span');
        if (unreadLabel) unreadLabel.textContent = remaining + ' unread';
        var markAllButton = notificationMenu.querySelector('.staff-header-mark-all-read');
        if (markAllButton) markAllButton.hidden = remaining === 0;
        var activeTab = notificationMenu.querySelector('[data-notification-filter][aria-selected="true"]');
        filterNotifications(activeTab ? activeTab.dataset.notificationFilter : 'new');
        fetch(notificationMenu.dataset.readEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ csrf_token: notificationMenu.dataset.csrf, keys: keys }),
            keepalive: true
        }).catch(function () {});
    }

    function closeHeaderPopovers(exceptButton) {
        popoverButtons.forEach(function (button) {
            if (button !== exceptButton) {
                button.setAttribute('aria-expanded', 'false');
                var popover = document.getElementById(button.getAttribute('aria-controls'));
                if (popover) popover.hidden = true;
            }
        });
    }

    popoverButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var popover = document.getElementById(button.getAttribute('aria-controls'));
            var willOpen = button.getAttribute('aria-expanded') !== 'true';
            closeHeaderPopovers(button);
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (popover) popover.hidden = !willOpen;
        });
    });

    if (notificationMenu) {
        notificationMenu.querySelectorAll('.staff-header-notification-item').forEach(function (item) {
            item.addEventListener('click', function () { markNotificationsRead([this]); });
        });
        notificationMenu.querySelectorAll('[data-notification-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () { filterNotifications(this.dataset.notificationFilter); });
        });
        var markAllButton = notificationMenu.querySelector('.staff-header-mark-all-read');
        if (markAllButton) markAllButton.addEventListener('click', function () {
            markNotificationsRead(Array.from(notificationMenu.querySelectorAll('.staff-header-notification-item[data-unread="1"]')));
        });
        filterNotifications('new');
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.staff-header-popover-container')) {
            closeHeaderPopovers(null);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeHeaderPopovers(null);
    });

    function updateStaffHeader() {
        var now = new Date();
        var dateEl = document.getElementById('staff-date');
        var weekdayEl = document.getElementById('staff-weekday');
        var timeEl = document.getElementById('staff-time');
        if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        if (weekdayEl) weekdayEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long' });
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }

    updateStaffHeader();
    window.setInterval(updateStaffHeader, 1000);
}());
</script>
<script src="/BMirk/admin/assets/password-visibility.js?v=3" defer></script>
<script src="/BMirk/assets/unsaved_changes.js?v=1" defer></script>
