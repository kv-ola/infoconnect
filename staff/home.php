<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: /BMirk/auth/login.php');
    exit;
}

$page_title = 'Staff Home';
$staffFirstName = '';
try {
    $staffNameQuery = $databaseConnection->prepare('SELECT first_name FROM staff_profiles WHERE user_id = ? LIMIT 1');
    if (!$staffNameQuery) {
        throw new RuntimeException('Unable to prepare Staff greeting name query.');
    }
    $staffNameId = (int) ($_SESSION['user_id'] ?? 0);
    $staffNameQuery->bind_param('i', $staffNameId);
    $staffNameQuery->execute();
    $staffNameRow = $staffNameQuery->get_result()->fetch_assoc();
    $staffNameQuery->close();
    $staffFirstName = trim((string) ($staffNameRow['first_name'] ?? ''));
} catch (Throwable $exception) {
    error_log('Unable to load Staff greeting name: ' . $exception->getMessage());
}
if ($staffFirstName === '') {
    $staffFirstName = 'Staff';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="home.css?v=30">
</head>
<body>
    <div class="staff-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="staff-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <section class="staff-content" aria-label="Staff home dashboard">
                <header class="home-welcome">
                    <h1><span id="staff-greeting">Good evening</span>, <?php echo htmlspecialchars($staffFirstName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                    <p>Welcome back to InfoConnect!</p>
                </header>
                <section class="staff-guide home-card" aria-labelledby="staff-guide-title">
                    <header class="staff-guide-heading">
                        <span class="staff-guide-main-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 5.5c3.2-1.3 6.2-.8 9 1.3v13c-2.8-2.1-5.8-2.6-9-1.3zM21 5.5c-3.2-1.3-6.2-.8-9 1.3v13c2.8-2.1 5.8-2.6 9-1.3z"/></svg></span>
                        <div><h2 id="staff-guide-title">Staff User Guide</h2><p>Need help using InfoConnect? Choose a guide below.</p></div>
                        <button class="staff-guide-full" id="staffGuideFullButton" type="button" aria-expanded="false" aria-controls="staff-guide-manual"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5.5c3.2-1.3 6.2-.8 9 1.3v13c-2.8-2.1-5.8-2.6-9-1.3zM21 5.5c-3.2-1.3-6.2-.8-9 1.3v13c2.8-2.1 5.8-2.6 9-1.3z"/></svg>View Full Guide <span aria-hidden="true">›</span></button>
                    </header>
                    <div class="staff-guide-cards" id="staff-guide-cards">
                        <article class="staff-guide-card guide-blue"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 5.5c3.2-1.3 6.2-.8 9 1.3v13c-2.8-2.1-5.8-2.6-9-1.3zM21 5.5c-3.2-1.3-6.2-.8-9 1.3v13c2.8-2.1 5.8-2.6 9-1.3z"/></svg></span><h3>Getting Started</h3><p>Learn how to log in, change your password, and navigate the system.</p><button type="button" class="staff-guide-topic-button" data-guide-topic="getting-started">View Guide <span aria-hidden="true">→</span></button></article>
                        <article class="staff-guide-card guide-green"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span><h3>Managing Seniors</h3><p>Learn how to view, add, import, and manage senior records.</p><button type="button" class="staff-guide-topic-button" data-guide-topic="seniors">View Guide <span aria-hidden="true">→</span></button></article>
                        <article class="staff-guide-card guide-red"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3.5h9l4 4V21H6z"/><path d="M14 3.5v5h5M9 13h7m-7 3.5h7"/></svg></span><h3>Posts &amp; Events</h3><p>Learn how to create posts, submit requests, and track their status.</p><button type="button" class="staff-guide-topic-button" data-guide-topic="posts">View Guide <span aria-hidden="true">→</span></button></article>
                        <article class="staff-guide-card guide-purple"><span class="guide-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3.5 5.5h17v12h-10l-5.5 3v-3h-1.5z"/><path d="M7 9h10m-10 4h7"/></svg></span><h3>SMS &amp; Notifications</h3><p>Learn how to send messages to seniors and view updates.</p><button type="button" class="staff-guide-topic-button" data-guide-topic="sms">View Guide <span aria-hidden="true">→</span></button></article>
                    </div>
                    <section class="staff-guide-manual" id="staff-guide-manual" aria-labelledby="staff-guide-manual-title" hidden>
                        <header><div><h3 id="staff-guide-manual-title">Staff System Manual</h3><p>Step-by-step help for common tasks in InfoConnect.</p></div><button type="button" id="staffGuideClose" aria-label="Close guide">Close</button></header>
                        <article data-guide-content="getting-started" hidden><h4>Getting Started</h4><ol><li>Sign in with your staff account ID and password.</li><li>Use the sidebar to open Seniors, Posts, SMS, Monitoring, or Settings.</li><li>Open Settings to update your profile, change your password, or contact the administrator.</li><li>Use the account menu in the top bar to sign out when you finish.</li></ol><p>If you are required to change your password, enter your current password and choose a new one that meets the requirements shown on screen.</p></article>
                        <article data-guide-content="seniors" hidden><h4>Managing Seniors</h4><ol><li>Open <a href="/BMirk/staff/seniors/accounts/seniors_accounts.php">Seniors</a> from the sidebar.</li><li>Search the records before adding a senior to avoid creating a duplicate.</li><li>Use the add or import action to enter new senior records. For imports, use the provided template and check the results for rejected rows.</li><li>Open a record to review or update its information, then save and confirm the updated details.</li></ol><p>Only enter information from authorized records and double-check account IDs before saving changes.</p></article>
                        <article data-guide-content="posts" hidden><h4>Posts &amp; Events</h4><ol><li>Open <a href="/BMirk/staff/posts/posts_list.php">Posts</a> and choose the create option for the item you need.</li><li>Enter a clear title and complete content, then review dates and other required details.</li><li>Submit the post or event for approval when prompted.</li><li>Check the posts list or Events page to follow its status. Use Archive to find older submissions.</li></ol><p>Review the published content for accuracy before submitting it for approval.</p></article>
                        <article data-guide-content="sms" hidden><h4>SMS &amp; Notifications</h4><ol><li>Open <a href="/BMirk/staff/sms/sms.php">SMS</a> from the sidebar.</li><li>Select the intended recipients and enter a short, clear message.</li><li>Review the recipient count and message text before sending.</li><li>Check the on-screen result after sending and correct any invalid contact details through the senior record process.</li></ol><p>Use SMS for relevant service updates and verify the audience before sending a message.</p></article>
                        <article data-guide-content="full" hidden><h4>Using InfoConnect</h4><p>InfoConnect provides staff tools for senior records, public posts, events, SMS, reports, and account settings. Use the sidebar to move between these areas and the header menu for quick actions.</p><h4>Managing Senior Records</h4><ol><li>Open Seniors and search before adding a record.</li><li>Add or import records using the available forms and templates.</li><li>Review each record carefully before saving updates.</li></ol><h4>Posts, Events, and Archive</h4><ol><li>Open Posts to create announcements or submit posts and events.</li><li>Complete all required fields and submit items for approval.</li><li>Track progress from the list and use Archive to review older items.</li></ol><h4>SMS</h4><ol><li>Choose the recipient group and enter the message.</li><li>Confirm the recipients and message before sending.</li><li>Review the sending result shown on screen.</li></ol><h4>Monitoring and Reports</h4><ol><li>Open Monitoring to view activity logs or generate reports.</li><li>Choose the relevant report type and filters, then generate or export the report.</li><li>Use activity logs to review recorded system actions.</li></ol><h4>Profile, Security, and Support</h4><ol><li>Open Settings to update your profile information.</li><li>Use Security to change your password.</li><li>Use Help &amp; Support to contact the administrator about a system issue, account assistance, or data update.</li><li>Sign out from the account menu when you finish.</li></ol></article>
                    </section>
                </section>

            </section>
        </main>
    </div>
    <script>
        (function () {
            var greeting = document.getElementById('staff-greeting');
            if (!greeting) return;
            var hour = new Date().getHours();
            greeting.textContent = hour >= 5 && hour < 12 ? 'Good morning' : (hour >= 12 && hour < 18 ? 'Good afternoon' : 'Good evening');
        }());
        (function () {
            var manual = document.getElementById('staff-guide-manual');
            var fullButton = document.getElementById('staffGuideFullButton');
            var closeButton = document.getElementById('staffGuideClose');
            var manualTitle = document.getElementById('staff-guide-manual-title');
            var sections = manual ? manual.querySelectorAll('[data-guide-content]') : [];
            if (!manual || !fullButton || !closeButton) return;

            function showGuide(topic, title) {
                sections.forEach(function (section) {
                    section.hidden = section.dataset.guideContent !== topic;
                });
                manualTitle.textContent = title;
                manual.hidden = false;
                fullButton.setAttribute('aria-expanded', 'true');
                manual.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            fullButton.addEventListener('click', function () {
                if (manual.hidden) showGuide('full', 'Staff System Manual');
                else {
                    manual.hidden = true;
                    fullButton.setAttribute('aria-expanded', 'false');
                }
            });
            document.querySelectorAll('[data-guide-topic]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var topic = button.dataset.guideTopic;
                    var title = button.closest('.staff-guide-card').querySelector('h3').textContent;
                    showGuide(topic, title + ' Guide');
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
