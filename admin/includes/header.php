<?php
require_once __DIR__ . '/../../config/activity_log.php';

$headerNotifications = [];
$headerNotificationCount = 0;
$headerNotificationError = false;
$headerAdminId = (int) ($_SESSION['user_id'] ?? 0);
if (empty($_SESSION['admin_notifications_token'])) {
    $_SESSION['admin_notifications_token'] = bin2hex(random_bytes(32));
}

try {
    $databaseConnection->query(
        "CREATE TABLE IF NOT EXISTS admin_notification_reads (
            admin_user_id INT(10) UNSIGNED NOT NULL,
            notification_key VARCHAR(100) NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (admin_user_id, notification_key),
            KEY idx_admin_notification_reads_read_at (admin_user_id, read_at),
            CONSTRAINT fk_admin_notification_reads_user FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $requestResult = $databaseConnection->query(
        "SELECT notification_type, request_id, title, submitted_by, created_at, target_url, notification_key
         FROM (
            SELECT
                ar.request_type AS notification_type,
                ar.id AS request_id,
                CASE ar.request_type
                    WHEN 'senior' THEN COALESCE(NULLIF(TRIM(CONCAT_WS(' ', seniors.first_name, seniors.last_name)), ''), CONCAT('Request #', ar.reference_id))
                    WHEN 'event' THEN COALESCE(NULLIF(events.title, ''), CONCAT('Request #', ar.reference_id))
                    WHEN 'announcement' THEN COALESCE(NULLIF(announcements.title, ''), CONCAT('Request #', ar.reference_id))
                    WHEN 'password_reset' THEN COALESCE(reset_user.account_id, CONCAT('Request #', ar.reference_id))
                    ELSE CONCAT('Request #', ar.reference_id)
                END AS title,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', requester_staff.first_name, NULLIF(requester_staff.middle_name, ''), requester_staff.last_name, NULLIF(requester_staff.suffix, ''))), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', requester_admin.first_name, NULLIF(requester_admin.middle_name, ''), requester_admin.last_name, NULLIF(requester_admin.suffix, ''))), ''),
                    requester.account_id,
                    'Unknown'
                ) AS submitted_by,
                ar.created_at,
                CASE
                    WHEN ar.request_type = 'password_reset' THEN CONCAT('/BMirk/admin/requests/requests_review.php?id=', ar.id, '&from=password_reset')
                    WHEN ar.request_type IN ('event', 'announcement') THEN CONCAT('/BMirk/admin/requests/requests_review.php?id=', ar.id)
                    ELSE CONCAT('/BMirk/admin/requests/requests_review.php?id=', ar.id, '&from=senior')
                END AS target_url,
                CONCAT('approval:', ar.id) AS notification_key
            FROM approval_requests ar
            LEFT JOIN events ON ar.request_type = 'event' AND events.id = ar.reference_id
            LEFT JOIN announcements ON ar.request_type = 'announcement' AND announcements.id = ar.reference_id
            LEFT JOIN senior_profiles seniors ON ar.request_type = 'senior' AND seniors.id = ar.reference_id
            LEFT JOIN users requester ON requester.id = ar.requested_by
            LEFT JOIN staff_profiles requester_staff ON requester_staff.user_id = requester.id
            LEFT JOIN admin_profiles requester_admin ON requester_admin.user_id = requester.id
            LEFT JOIN users reset_user ON ar.request_type = 'password_reset' AND reset_user.id = ar.reference_id
            WHERE ar.request_type <> 'senior'

            UNION ALL

            SELECT
                'support' AS notification_type,
                sr.id AS request_id,
                sr.subject AS title,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', requester_staff.first_name, NULLIF(requester_staff.middle_name, ''), requester_staff.last_name, NULLIF(requester_staff.suffix, ''))), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', requester_admin.first_name, NULLIF(requester_admin.middle_name, ''), requester_admin.last_name, NULLIF(requester_admin.suffix, ''))), ''),
                    requester.account_id,
                    'Unknown'
                ) AS submitted_by,
                sr.created_at,
                '/BMirk/admin/assistance/support_requests.php' AS target_url,
                CONCAT('support:', sr.id) AS notification_key
            FROM support_requests sr
            LEFT JOIN users requester ON requester.id = sr.requested_by
            LEFT JOIN staff_profiles requester_staff ON requester_staff.user_id = requester.id
            LEFT JOIN admin_profiles requester_admin ON requester_admin.user_id = requester.id
         ) AS request_notifications
         ORDER BY created_at DESC, request_id DESC"
    );
    while ($notification = $requestResult->fetch_assoc()) {
        $notification['kind'] = 'request';
        $notification['actor'] = $notification['submitted_by'];
        $headerNotifications[] = $notification;
    }

    $personnelNames = [];
    $personnelResult = $databaseConnection->query(
        "SELECT u.id,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', sp.first_name, NULLIF(sp.middle_name, ''), sp.last_name, NULLIF(sp.suffix, ''))), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', ap.first_name, NULLIF(ap.middle_name, ''), ap.last_name, NULLIF(ap.suffix, ''))), ''),
                    u.account_id,
                    'Unknown'
                ) AS actor_name
         FROM users u
         LEFT JOIN staff_profiles sp ON sp.user_id = u.id
         LEFT JOIN admin_profiles ap ON ap.user_id = u.id
         WHERE u.role IN ('staff', 'admin')"
    );
    while ($personnel = $personnelResult->fetch_assoc()) {
        $personnelNames[(int) $personnel['id']] = $personnel['actor_name'];
    }

    $databaseActivities = [];
    $activityResult = $databaseConnection->query(
        "SELECT al.id, al.user_id, al.action, al.module, al.reference_id, al.description, al.created_at,
                COALESCE(u.account_id, 'System') AS account_id
         FROM activity_logs al
         LEFT JOIN users u ON u.id = al.user_id
         WHERE al.module = 'Seniors' AND al.action IN ('create', 'import', 'update')
         ORDER BY al.created_at DESC"
    );
    while ($activity = $activityResult->fetch_assoc()) {
        $activity['actor_name'] = $personnelNames[(int) ($activity['user_id'] ?? 0)] ?? $activity['account_id'];
        $databaseActivities[] = $activity;
    }
    $fileActivities = activityLogReadFiles();
    foreach ($fileActivities as $activity) {
        if (($activity['module'] ?? '') !== 'Seniors'
            || !in_array($activity['action'] ?? '', ['create', 'import', 'update'], true)) {
            continue;
        }
        $activity['id'] = $activity['archive_id'] ?? null;
        $activity['account_id'] = $activity['account_id'] ?: 'Unknown';
        $activity['actor_name'] = $personnelNames[(int) ($activity['user_id'] ?? 0)] ?? $activity['account_id'];
        $databaseActivities[] = $activity;
    }

    $seenActivityKeys = [];
    foreach ($databaseActivities as $activity) {
        $activityKey = !empty($activity['id'])
            ? 'activity:' . (int) $activity['id']
            : 'activity:' . hash('sha256', implode('|', [$activity['created_at'], $activity['user_id'] ?? '', $activity['action'], $activity['module'], $activity['description'] ?? '']));
        if (isset($seenActivityKeys[$activityKey])) {
            continue;
        }
        $seenActivityKeys[$activityKey] = true;
        $label = $activity['action'] === 'import' ? 'Senior records imported'
            : ($activity['action'] === 'create' ? 'Senior added' : 'Senior record updated');
        $headerNotifications[] = [
            'notification_type' => 'activity',
            'request_id' => (int) ($activity['reference_id'] ?? 0),
            'title' => $label,
            'submitted_by' => $activity['actor_name'] ?? 'Unknown',
            'actor' => $activity['actor_name'] ?? 'Unknown',
            'created_at' => $activity['created_at'],
            'target_url' => '/BMirk/admin/management/senior_view.php?id=' . (int) ($activity['reference_id'] ?? 0),
            'notification_key' => $activityKey,
            'kind' => 'activity',
            'description' => preg_replace(
                '/^Submitted senior record (.+) for approval\.$/',
                'Added senior record $1.',
                (string) ($activity['description'] ?? '')
            ),
        ];
    }

    usort($headerNotifications, static function (array $left, array $right): int {
        return strcmp((string) $right['created_at'], (string) $left['created_at']);
    });

    $readStatement = $databaseConnection->prepare('SELECT notification_key FROM admin_notification_reads WHERE admin_user_id = ?');
    $readStatement->bind_param('i', $headerAdminId);
    $readStatement->execute();
    $readResult = $readStatement->get_result();
    $readKeys = [];
    while ($readRow = $readResult->fetch_assoc()) {
        $readKeys[$readRow['notification_key']] = true;
    }
    $readStatement->close();

    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $newSince = (new DateTimeImmutable('-24 hours', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
    foreach ($headerNotifications as &$notification) {
        if (!isset($notification['notification_key'])) {
            $notification['notification_key'] = $notification['kind'] . ':' . $notification['request_id'];
        }
        $notification['is_read'] = isset($readKeys[$notification['notification_key']]);
        $notification['day'] = substr((string) $notification['created_at'], 0, 10);
        $notification['is_new'] = (string) $notification['created_at'] >= $newSince;
        if (!$notification['is_read']) {
            $headerNotificationCount++;
        }
    }
    unset($notification);
} catch (Throwable $exception) {
    error_log('Unable to load admin header notifications: ' . $exception->getMessage());
    $headerNotificationError = true;
}

function adminHeaderNotificationEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminHeaderNotificationLabel(array $notification): string
{
    if ($notification['kind'] === 'activity') {
        return 'Activity';
    }
    return [
        'senior' => 'Senior registration request',
        'event' => 'Event approval request',
        'announcement' => 'Post approval request',
        'password_reset' => 'Password reset request',
        'support' => 'Help & support request',
    ][$notification['notification_type']] ?? 'Request';
}
?>
<link rel="stylesheet" href="/BMirk/admin/includes/assets/header.css?v=7">
<link rel="stylesheet" href="/BMirk/admin/includes/assets/panels.css?v=1">
<link rel="stylesheet" href="/BMirk/admin/includes/assets/theme.css?v=1">
<header class="admin-header" aria-label="InfoConnect administrator toolbar">
    <a class="admin-header-brand" href="/BMirk/admin/home.php" aria-label="InfoConnect admin home">
        <img src="/BMirk/auth/assets/images/infoconnect-mark-white.png" alt="">
        <span class="admin-header-divider" aria-hidden="true"></span>
        <span class="admin-header-wordmark"><strong>INFO<span>CONNECT</span></strong><small>Office for Senior Citizens Affairs</small><small>Mandaluyong City</small></span>
    </a>
    <div class="admin-header-tools">
        <div class="admin-header-date" aria-live="polite">
            <span><strong id="admin-date"><?php echo date('F j, Y'); ?></strong><small><span id="admin-weekday"><?php echo date('l'); ?></span>, <span id="admin-time"><?php echo date('h:i:s A'); ?></span></small></span>
        </div>
        <span class="admin-header-separator" aria-hidden="true"></span>
        <div class="admin-header-popover-container">
            <button class="admin-header-quick" type="button" aria-label="Quick actions" aria-haspopup="true" aria-expanded="false" aria-controls="admin-quick-actions-menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <nav class="admin-header-popover admin-header-quick-menu" id="admin-quick-actions-menu" aria-label="Quick actions" hidden>
                <strong>Quick Actions</strong>
                <a href="/BMirk/admin/management/senior.php"><span class="admin-quick-icon quick-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span><span>View Seniors</span><svg class="admin-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
                <a href="/BMirk/admin/management/users.php"><span class="admin-quick-icon quick-yellow"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2z"/></svg></span><span>Manage Personnel</span><svg class="admin-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
                <a href="/BMirk/admin/requests/requests.php"><span class="admin-quick-icon quick-red"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 12h7m-7 4h7"/></svg></span><span>Review Requests</span><svg class="admin-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
                <a href="/BMirk/admin/assistance/support_requests.php"><span class="admin-quick-icon quick-purple"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v11H9l-5 3v-14Z"/><path d="M8 9h8m-8 4h5"/></svg></span><span>Help &amp; Support</span><svg class="admin-quick-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
            </nav>
        </div>
        <div class="admin-header-popover-container">
            <button class="admin-header-notification" type="button" aria-label="<?php echo $headerNotificationCount > 0 ? 'Notifications, ' . number_format($headerNotificationCount) . ' unread' : 'Notifications'; ?>" aria-haspopup="true" aria-expanded="false" aria-controls="admin-notifications-menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <?php if ($headerNotificationCount > 0): ?><span class="admin-header-notification-count"><?php echo $headerNotificationCount > 99 ? '99+' : number_format($headerNotificationCount); ?></span><?php endif; ?>
            </button>
            <div class="admin-header-popover admin-header-notifications-menu" id="admin-notifications-menu" aria-label="Notifications" data-read-endpoint="/BMirk/admin/includes/notifications_read.php" data-csrf="<?php echo adminHeaderNotificationEscape($_SESSION['admin_notifications_token']); ?>" data-today="<?php echo date('Y-m-d'); ?>" hidden>
                <?php if ($headerNotificationError): ?>
                    <p role="alert">Notifications are temporarily unavailable.</p>
                <?php else: ?>
                    <div class="admin-header-notifications-heading">
                        <strong>Notifications</strong>
                        <div class="admin-header-notifications-actions">
                            <span><?php echo number_format($headerNotificationCount); ?> unread</span>
                            <button type="button" class="admin-header-mark-all-read" <?php echo $headerNotificationCount === 0 ? 'hidden' : ''; ?>>Mark all as read</button>
                        </div>
                    </div>
                    <div class="admin-header-notification-filters" role="tablist" aria-label="Filter notifications">
                        <button type="button" role="tab" aria-selected="true" data-notification-filter="new">New</button>
                        <button type="button" role="tab" aria-selected="false" data-notification-filter="today">Today</button>
                        <button type="button" role="tab" aria-selected="false" data-notification-filter="earlier">Earlier</button>
                        <button type="button" role="tab" aria-selected="false" data-notification-filter="unread">Unread</button>
                        <button type="button" role="tab" aria-selected="false" data-notification-filter="all">All</button>
                    </div>
                    <p class="admin-header-notification-empty" role="status" hidden>No notifications in this section.</p>
                    <div class="admin-header-notification-list">
                    <?php foreach ($headerNotifications as $notification): ?>
                        <a class="admin-header-notification-item<?php echo $notification['is_read'] ? ' is-read' : ' is-unread'; ?>" href="<?php echo adminHeaderNotificationEscape($notification['target_url']); ?>" data-notification-key="<?php echo adminHeaderNotificationEscape($notification['notification_key']); ?>" data-new="<?php echo $notification['is_new'] ? '1' : '0'; ?>" data-day="<?php echo adminHeaderNotificationEscape($notification['day']); ?>" data-unread="<?php echo $notification['is_read'] ? '0' : '1'; ?>">
                            <strong><?php echo adminHeaderNotificationEscape($notification['title']); ?></strong>
                            <span><?php echo adminHeaderNotificationEscape(adminHeaderNotificationLabel($notification)); ?> &middot; <?php echo adminHeaderNotificationEscape($notification['actor']); ?></span>
                            <?php if (!empty($notification['description'])): ?><span class="admin-header-notification-description"><?php echo adminHeaderNotificationEscape($notification['description']); ?></span><?php endif; ?>
                            <small><?php echo adminHeaderNotificationEscape(date('M j, Y h:i A', strtotime($notification['created_at']))); ?></small>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="admin-header-popover-container">
            <button class="admin-header-account" type="button" aria-label="Administrator account menu" aria-haspopup="true" aria-expanded="false" aria-controls="admin-account-menu">
                <span class="admin-header-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c.5-4 3.4-6 8-6s7.5 2 8 6"/></svg></span>
                <span class="admin-header-account-copy"><strong>Admin</strong><small><?php echo htmlspecialchars((string) ($_SESSION['account_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small></span>
                <svg class="admin-header-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </button>
            <nav class="admin-header-popover admin-header-account-menu" id="admin-account-menu" aria-label="Administrator account links" hidden>
                <a href="/BMirk/admin/system/settings.php?tab=personal#personal-information">My Profile</a>
                <a href="/BMirk/admin/system/settings.php?tab=personal#change-password">Change Password</a>
                <a href="/BMirk/auth/logout.php?portal=admin">Logout</a>
            </nav>
        </div>
    </div>
</header>
<script>
(function () {
    var popoverButtons = document.querySelectorAll('.admin-header-quick, .admin-header-notification, .admin-header-account');
    var notificationMenu = document.getElementById('admin-notifications-menu');

    function filterNotifications(filter) {
        if (!notificationMenu) return;
        var today = notificationMenu.dataset.today;
        var items = notificationMenu.querySelectorAll('.admin-header-notification-item');
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
        var empty = notificationMenu.querySelector('.admin-header-notification-empty');
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
        var remainingUnread = notificationMenu.querySelectorAll('.admin-header-notification-item[data-unread="1"]').length;
        var badge = document.querySelector('.admin-header-notification-count');
        if (remainingUnread === 0 && badge) badge.remove();
        else if (badge) badge.textContent = remainingUnread > 99 ? '99+' : String(remainingUnread);
        var bell = document.querySelector('.admin-header-notification');
        if (bell) bell.setAttribute('aria-label', remainingUnread > 0 ? 'Notifications, ' + remainingUnread + ' unread' : 'Notifications');
        var unreadLabel = notificationMenu.querySelector('.admin-header-notifications-heading span');
        if (unreadLabel) unreadLabel.textContent = remainingUnread + ' unread';
        var markAllButton = notificationMenu.querySelector('.admin-header-mark-all-read');
        if (markAllButton) markAllButton.hidden = remainingUnread === 0;
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
        notificationMenu.querySelectorAll('.admin-header-notification-item').forEach(function (item) {
            item.addEventListener('click', function () {
                markNotificationsRead([this]);
            });
        });
        notificationMenu.querySelectorAll('[data-notification-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                filterNotifications(this.dataset.notificationFilter);
            });
        });
        var markAllButton = notificationMenu.querySelector('.admin-header-mark-all-read');
        if (markAllButton) {
            markAllButton.addEventListener('click', function () {
                markNotificationsRead(Array.from(notificationMenu.querySelectorAll('.admin-header-notification-item[data-unread="1"]')));
            });
        }
        filterNotifications('new');
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.admin-header-popover-container')) {
            closeHeaderPopovers(null);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeHeaderPopovers(null);
        }
    });

    function updateAdminHeader() {
        var now = new Date();
        var greeting = now.getHours() >= 5 && now.getHours() < 12 ? 'Good morning' : (now.getHours() >= 12 && now.getHours() < 18 ? 'Good afternoon' : 'Good evening');
        var greetingEl = document.getElementById('admin-greeting');
        var dateEl = document.getElementById('admin-date');
        var weekdayEl = document.getElementById('admin-weekday');
        var timeEl = document.getElementById('admin-time');
        if (greetingEl) greetingEl.textContent = greeting;
        if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        if (weekdayEl) weekdayEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long' });
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    updateAdminHeader();
    window.setInterval(updateAdminHeader, 1000);
}());
</script>
<script src="/BMirk/admin/assets/password-visibility.js?v=3" defer></script>
<script src="/BMirk/assets/unsaved_changes.js?v=1" defer></script>
