<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$requestOrigin = $_GET['from'] ?? '';
$seniorPages = ['senior.php', 'senior_view.php'];
$userPages = ['users.php', 'users_add.php', 'users_edit.php'];
$requestPages = ['requests.php', 'requests_review.php', 'history.php', 'history_review.php'];
$monitoringPages = ['activity_logs.php', 'reports.php'];
$assistancePages = ['support_requests.php', 'password_reset_requests.php', 'assistance_history.php'];
$systemPages = ['settings.php'];
$managementOpen = in_array($currentPage, array_merge($seniorPages, $userPages), true);
$requestsOpen = in_array($currentPage, $requestPages, true);
$monitoringOpen = in_array($currentPage, $monitoringPages, true);
$assistanceOpen = in_array($currentPage, $assistancePages, true);
$supportRequestsActive = $currentPage === 'support_requests.php';
$passwordResetRequestsActive = $currentPage === 'password_reset_requests.php';
$assistanceHistoryActive = $currentPage === 'assistance_history.php';
$systemOpen = in_array($currentPage, $systemPages, true);
$seniorActive = in_array($currentPage, $seniorPages, true);
$personnelActive = in_array($currentPage, $userPages, true);
$pendingPostsActive = $currentPage === 'requests.php'
    || ($currentPage === 'requests_review.php' && $requestOrigin === '');
$historyActive = in_array($currentPage, ['history.php', 'history_review.php'], true)
    || ($currentPage === 'requests_review.php' && $requestOrigin === 'history');
?>

<link rel="stylesheet" href="/BMirk/admin/includes/assets/sidebar.css?v=11">

<aside class="admin-sidebar" id="shared-admin-sidebar">
    <div class="sidebar-brand">InfoConnect</div>

    <nav class="sidebar-nav" aria-label="Admin navigation">
        <div class="nav-links">
            <a href="/BMirk/admin/home.php" class="sidebar-link <?php echo ($currentPage === 'home.php') ? 'active' : ''; ?>"<?php echo ($currentPage === 'home.php') ? ' aria-current="page"' : ''; ?>><svg class="sidebar-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="m2.5 10.8 9.5-8 9.5 8"/><path d="M4.5 9.7V21h15V9.7M9 21v-6.5h6V21"/><path d="M7.5 11.5h1m7 0h1"/></svg><span>Home</span></a>
            <a href="/BMirk/admin/dashboard.php" class="sidebar-link sub-link <?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>"<?php echo ($currentPage === 'dashboard.php') ? ' aria-current="page"' : ''; ?>><svg class="sidebar-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20.5h18"/><path d="M5 18V11h3v7m4 0V4h3v14m4 0v-9h3v9"/><path d="M6.5 8.5h.1m7.9-7h.1m5.9 5h.1"/><path d="M4 21V9.5M11 21V2.5M18 21V7.5"/></svg><span>Dashboard</span></a>
        </div>

        <div class="nav-group<?php echo $managementOpen ? ' open' : ''; ?>">
            <button class="nav-button" type="button" aria-expanded="<?php echo $managementOpen ? 'true' : 'false'; ?>"><svg class="sidebar-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8.5" cy="7.5" r="3"/><path d="M2.5 20c.2-3.8 2.2-6 6-6s5.8 2.2 6 6z"/><path d="M16 5.5a2.7 2.7 0 0 1 0 5.2m1.2 3.1c2.7.4 4.1 2.2 4.3 5.2h-5"/><path d="M6.5 17h4"/></svg><span>Management</span></button>
            <div class="submenu">
                <div class="submenu-inner">
                    <a href="/BMirk/admin/management/senior.php" class="<?php echo $seniorActive ? 'active' : ''; ?>"<?php echo $seniorActive ? ' aria-current="page"' : ''; ?>>Seniors</a>
                    <a href="/BMirk/admin/management/users.php" class="<?php echo $personnelActive ? 'active' : ''; ?>"<?php echo $personnelActive ? ' aria-current="page"' : ''; ?>>Personnel</a>
                </div>
            </div>
        </div>

        <div class="nav-group<?php echo $requestsOpen ? ' open' : ''; ?>">
            <button class="nav-button" type="button" aria-expanded="<?php echo $requestsOpen ? 'true' : 'false'; ?>"><svg class="sidebar-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.5h10l3 3V21H4V3.5z"/><path d="M16.5 3.5v4h3.5M8 11h8m-8 3.5h8m-8 3.5h4"/><path d="m7.2 7.5 1 1 1.8-2"/></svg><span>Requests</span></button>
            <div class="submenu">
                <div class="submenu-inner">
                    <a href="/BMirk/admin/requests/requests.php" class="<?php echo $pendingPostsActive ? 'active' : ''; ?>"<?php echo $pendingPostsActive ? ' aria-current="page"' : ''; ?>>Pending Posts</a>
                    <a href="/BMirk/admin/requests/history.php" class="<?php echo $historyActive ? 'active' : ''; ?>"<?php echo $historyActive ? ' aria-current="page"' : ''; ?>>History</a>
                </div>
            </div>
        </div>

        <div class="nav-group<?php echo $monitoringOpen ? ' open' : ''; ?>">
            <button class="nav-button" type="button" aria-expanded="<?php echo $monitoringOpen ? 'true' : 'false'; ?>"><svg class="sidebar-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="3" width="19" height="14" rx="2"/><path d="M7 21h10m-5-4v4"/><path d="m5.5 13 3.2-3.2 2.7 2.1 4.6-5"/><circle cx="5.5" cy="13" r=".7"/><circle cx="8.7" cy="9.8" r=".7"/><circle cx="11.4" cy="11.9" r=".7"/><circle cx="16" cy="6.9" r=".7"/></svg><span>Monitoring</span></button>
            <div class="submenu">
                <div class="submenu-inner">
                    <a href="/BMirk/admin/monitoring/activity_logs.php" class="<?php echo $currentPage === 'activity_logs.php' ? 'active' : ''; ?>"<?php echo $currentPage === 'activity_logs.php' ? ' aria-current="page"' : ''; ?>>Activity Logs</a>
                    <a href="/BMirk/admin/monitoring/reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>"<?php echo $currentPage === 'reports.php' ? ' aria-current="page"' : ''; ?>>Reports</a>
                </div>
            </div>
        </div>

        <div class="nav-group<?php echo $assistanceOpen ? ' open' : ''; ?>">
            <button class="nav-button" type="button" aria-expanded="<?php echo $assistanceOpen ? 'true' : 'false'; ?>"><svg class="sidebar-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.4 1.6c-1.1 1.2-2 1.5-2 3.4"/><path d="M12 17.5h.01"/></svg><span>Assistance</span></button>
            <div class="submenu">
                <div class="submenu-inner">
                    <a href="/BMirk/admin/assistance/support_requests.php" class="<?php echo $supportRequestsActive ? 'active' : ''; ?>"<?php echo $supportRequestsActive ? ' aria-current="page"' : ''; ?>>Help &amp; Support</a>
                    <a href="/BMirk/admin/assistance/password_reset_requests.php" class="<?php echo $passwordResetRequestsActive ? 'active' : ''; ?>"<?php echo $passwordResetRequestsActive ? ' aria-current="page"' : ''; ?>>Password Reset</a>
                    <a href="/BMirk/admin/assistance/assistance_history.php" class="<?php echo $assistanceHistoryActive ? 'active' : ''; ?>"<?php echo $assistanceHistoryActive ? ' aria-current="page"' : ''; ?>>History</a>
                </div>
            </div>
        </div>

        <div class="nav-group<?php echo $systemOpen ? ' open' : ''; ?>">
            <button class="nav-button" type="button" aria-expanded="<?php echo $systemOpen ? 'true' : 'false'; ?>"><svg class="sidebar-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="m9.8 2.8.5-1h3.4l.5 1 1.1 1.5 1.8.2 1.2-.3 2.4 2.4-.3 1.2.2 1.8 1.5 1.1 1 .5v3.4l-1 .5-1.5 1.1-.2 1.8.3 1.2-2.4 2.4-1.2-.3-1.8.2-1.1 1.5-.5 1h-3.4l-.5-1-1.1-1.5-1.8-.2-1.2.3-2.4-2.4.3-1.2-.2-1.8-1.5-1.1-1-.5v-3.4l1-.5 1.5-1.1.2-1.8-.3-1.2 2.4-2.4 1.2.3 1.8-.2z"/><circle cx="12" cy="12" r="4"/><path d="M12 6.7v1m0 8.6v1m5.3-5.3h-1M7.7 12h-1"/></svg><span>System</span></button>
            <div class="submenu">
                <div class="submenu-inner">
                    <a href="/BMirk/admin/system/settings.php" class="<?php echo $systemOpen ? 'active' : ''; ?>"<?php echo $systemOpen ? ' aria-current="page"' : ''; ?>>Settings</a>
                </div>
            </div>
        </div>

    </nav>
</aside>

<script>
    document.querySelectorAll('.nav-button').forEach(function (button) {
        button.addEventListener('click', function () {
            const group = this.closest('.nav-group');
            const willOpen = !group.classList.contains('open');

            document.querySelectorAll('.nav-group').forEach(function (item) {
                item.classList.remove('open');
                const itemButton = item.querySelector(':scope > .nav-button');
                if (itemButton) itemButton.setAttribute('aria-expanded', 'false');
            });

            if (willOpen) {
                group.classList.add('open');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });
</script>
