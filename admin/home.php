<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /BMirk/auth/login.php');
    exit;
}

$page_title = 'Admin Home';
$adminFirstName = '';
try {
    $adminNameQuery = $databaseConnection->prepare('SELECT first_name FROM admin_profiles WHERE user_id = ? LIMIT 1');
    $adminNameId = (int) ($_SESSION['user_id'] ?? 0);
    $adminNameQuery->bind_param('i', $adminNameId);
    $adminNameQuery->execute();
    $adminNameRow = $adminNameQuery->get_result()->fetch_assoc();
    $adminNameQuery->close();
    $adminFirstName = trim((string) ($adminNameRow['first_name'] ?? ''));
} catch (Throwable $exception) {
    error_log('Unable to load administrator greeting name: ' . $exception->getMessage());
}
if ($adminFirstName === '') {
    $adminFirstName = 'Admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/BMirk/admin/home.css?v=5">
</head>
<body class="admin-home-page">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="admin-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <section class="home-content" aria-label="Admin home dashboard">
                <header class="home-welcome">
                    <h1><span id="admin-greeting">Good evening</span>, <?php echo htmlspecialchars($adminFirstName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                    <p>Welcome back to InfoConnect!</p>
                </header>
                <section class="admin-guide home-card" aria-labelledby="admin-guide-title">
                    <header class="admin-guide-heading">
                        <span class="admin-guide-main-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 5.5c3.2-1.3 6.2-.8 9 1.3v13c-2.8-2.1-5.8-2.6-9-1.3zM21 5.5c-3.2-1.3-6.2-.8-9 1.3v13c2.8-2.1 5.8-2.6 9-1.3z"/></svg></span>
                        <div><h2 id="admin-guide-title">Admin User Guide</h2><p>Quick instructions for common InfoConnect administration tasks.</p></div>
                        <button class="admin-guide-full" id="adminGuideFullButton" type="button" aria-expanded="false" aria-controls="admin-guide-manual">View Full Guide <span aria-hidden="true">→</span></button>
                    </header>
                    <div class="admin-guide-cards">
                        <article class="admin-guide-card guide-blue"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 5.5c3.2-1.3 6.2-.8 9 1.3v13c-2.8-2.1-5.8-2.6-9-1.3zM21 5.5c-3.2-1.3-6.2-.8-9 1.3v13c2.8-2.1 5.8-2.6 9-1.3z"/></svg></span><h3>Getting Started</h3><p>Navigate admin tools and manage your account securely.</p><button type="button" data-admin-guide="start">View Guide <span aria-hidden="true">→</span></button></article>
                        <article class="admin-guide-card guide-green"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span><h3>Records &amp; Personnel</h3><p>Manage senior profiles, administrators, and staff accounts.</p><button type="button" data-admin-guide="records">View Guide <span aria-hidden="true">→</span></button></article>
                        <article class="admin-guide-card guide-red"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 12h7m-7 4h7"/></svg></span><h3>Review Requests</h3><p>Review and decide on senior, event, and announcement requests.</p><button type="button" data-admin-guide="requests">View Guide <span aria-hidden="true">→</span></button></article>
                        <article class="admin-guide-card guide-purple"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V11h4v9m4 0V5h4v15m4 0v-8h4v8M2 21h20"/></svg></span><h3>Monitoring &amp; Reports</h3><p>Review activity logs and create system reports.</p><button type="button" data-admin-guide="monitoring">View Guide <span aria-hidden="true">→</span></button></article>
                    </div>
                    <section class="admin-guide-manual" id="admin-guide-manual" aria-labelledby="admin-guide-manual-title" hidden>
                        <header><div><h3 id="admin-guide-manual-title">Admin System Manual</h3><p>Step-by-step help for common administration tasks.</p></div><button type="button" id="adminGuideClose" aria-label="Close guide">Close</button></header>
                        <article data-admin-guide-content="start" hidden><h4>Getting Started</h4><ol><li>Use the sidebar to open Management, Requests, Monitoring, Assistance, or System settings.</li><li>Use the Quick Actions icon in the header to open frequently used admin pages.</li><li>Open the account menu to view profile settings, change your password, or sign out.</li></ol></article>
                        <article data-admin-guide-content="records" hidden><h4>Records &amp; Personnel</h4><ol><li>Open Management → Seniors to review and manage senior records.</li><li>Open Management → Personnel to add or update administrator and staff accounts.</li><li>Search for an existing record before creating another, and verify profile details before saving.</li></ol></article>
                        <article data-admin-guide-content="requests" hidden><h4>Review Requests</h4><ol><li>Open Requests → Pending Posts to review senior registration, event, and announcement requests.</li><li>Open a request and check the submitted information and supporting details.</li><li>Approve or reject it with clear remarks. Use Requests → History to view completed decisions.</li></ol></article>
                        <article data-admin-guide-content="monitoring" hidden><h4>Monitoring &amp; Reports</h4><ol><li>Open Monitoring → Activity Logs to review recorded system actions.</li><li>Open Monitoring → Reports, choose the report and filters, then generate or export the report.</li><li>Use Assistance to respond to support and password reset requests. Use System → Settings for system configuration and backups.</li></ol></article>
                        <article data-admin-guide-content="full" hidden><h4>Using InfoConnect</h4><p>The admin tools cover senior and personnel management, approval requests, monitoring, assistance, and system settings.</p><h4>Management</h4><ol><li>Search before adding or editing senior and personnel records.</li><li>Review the information carefully before saving account or profile changes.</li></ol><h4>Requests</h4><ol><li>Open pending requests and check their details before deciding.</li><li>Record clear remarks for rejected requests and use History to review decisions.</li></ol><h4>Monitoring &amp; Assistance</h4><ol><li>Use Activity Logs to review system actions and Reports to summarize records.</li><li>Respond to support and password reset requests from Assistance.</li><li>Use System Settings for configuration and backup tasks.</li></ol></article>
                    </section>
                </section>
            </section>
        </main>
    </div>
    <script>
        (function () {
            var manual = document.getElementById('admin-guide-manual');
            var fullButton = document.getElementById('adminGuideFullButton');
            var closeButton = document.getElementById('adminGuideClose');
            var title = document.getElementById('admin-guide-manual-title');
            if (!manual || !fullButton || !closeButton) return;
            var sections = manual.querySelectorAll('[data-admin-guide-content]');
            function showGuide(topic, heading) {
                sections.forEach(function (section) { section.hidden = section.dataset.adminGuideContent !== topic; });
                title.textContent = heading;
                manual.hidden = false;
                fullButton.setAttribute('aria-expanded', 'true');
                manual.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            fullButton.addEventListener('click', function () {
                if (manual.hidden) showGuide('full', 'Admin System Manual');
                else { manual.hidden = true; fullButton.setAttribute('aria-expanded', 'false'); }
            });
            document.querySelectorAll('[data-admin-guide]').forEach(function (button) {
                button.addEventListener('click', function () {
                    showGuide(button.dataset.adminGuide, button.closest('.admin-guide-card').querySelector('h3').textContent + ' Guide');
                });
            });
            closeButton.addEventListener('click', function () {
                manual.hidden = true;
                fullButton.setAttribute('aria-expanded', 'false');
                fullButton.focus();
            });
        }());
    </script>
</body>
</html>
