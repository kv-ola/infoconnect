<?php
require_once __DIR__ . '/config/database.php';

// Resume a role-specific session when someone returns to the system root.
foreach (['admin' => 'BMirk_Admin', 'staff' => 'BMirk_Staff', 'senior' => 'BMirk_Senior'] as $roleName => $cookieName) {
    if (isset($_COOKIE[$cookieName])) {
        session_name($cookieName);
        break;
    }
}
require_once __DIR__ . '/config/session.php';

if (isset($_SESSION['user_id'])) {
    $role = (string) ($_SESSION['role'] ?? '');
    $destinations = ['admin' => '/BMirk/admin/home.php', 'staff' => '/BMirk/staff/home.php', 'senior' => '/BMirk/senior/home.php'];
    if (isset($destinations[$role])) {
        moveSessionToRole($role);
        header('Location: ' . $destinations[$role]);
        exit;
    }
}

function portalEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$today = new DateTimeImmutable('today');
$monthStart = $today->format('Y-m-d');
$monthEnd = $today->modify('first day of next month')->format('Y-m-d');
$events = [];
$announcements = [];
$portalDataError = false;

try {
    $eventStatement = $databaseConnection->prepare(
        "SELECT title, event_date, start_time, end_time, location, description, status
         FROM events
         WHERE status IN ('approved', 'published', 'completed') AND event_date >= ? AND event_date < ?
         ORDER BY event_date, start_time, id"
    );
    $eventStatement->bind_param('ss', $monthStart, $monthEnd);
    $eventStatement->execute();
    $eventResult = $eventStatement->get_result();
    while ($row = $eventResult->fetch_assoc()) {
        $events[] = $row;
    }
    $eventStatement->close();

    $announcementResult = $databaseConnection->query(
        "SELECT title, content, COALESCE(published_at, created_at) AS display_date
         FROM announcements
         WHERE status IN ('approved', 'published')
           AND (published_at IS NULL OR published_at <= NOW())
           AND (expires_at IS NULL OR expires_at >= NOW())
         ORDER BY COALESCE(published_at, created_at) DESC
         LIMIT 4"
    );
    while ($row = $announcementResult->fetch_assoc()) {
        $announcements[] = $row;
    }
} catch (Throwable $exception) {
    error_log('Unable to load public portal information: ' . $exception->getMessage());
    $portalDataError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0758bd">
    <meta name="description" content="InfoConnect connects Mandaluyong City senior citizens with OSCA events, announcements, and community updates.">
    <title>InfoConnect | OSCA Mandaluyong City</title>
    <link rel="stylesheet" href="/BMirk/index.css?v=20261011-footer-logo-asset">
</head>
<body>
    <main>
        <section class="hero" id="home" aria-labelledby="hero-title">
            <div class="hero-inner">
                <div class="hero-brand">
                    <img src="/BMirk/auth/assets/images/osca_logo.jpg" alt="Mandaluyong City seal">
                    <span class="hero-divider" aria-hidden="true"></span>
                    <div class="hero-copy">
                        <p class="hero-city">Mandaluyong City</p>
                        <h1 id="hero-title">Office for Senior Citizens Affairs</h1>
                        <p class="hero-description">Connecting senior citizens to community programs, events,<br class="desktop-break"> and important announcements.</p>
                    </div>
                </div>
                <a class="hero-login" id="login-open" href="/BMirk/auth/login.php" aria-label="Log in to InfoConnect">
                    <span>Login</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                </a>
            </div>
            <a class="scroll-cue" href="#events" aria-label="Scroll to events and announcements"><b aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></b></a>
            <svg class="hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"><path class="wave-shadow" d="M0 32C170 104 300 100 470 70s240-48 390-22 285 53 580-22v94H0Z"/><path class="wave-fill" d="M0 56C170 114 310 105 485 78s247-48 390-24 290 50 565-18v84H0Z"/></svg>
        </section>

        <div class="public-content" id="events">
            <section class="panel events-panel" aria-labelledby="events-title">
                <header class="panel-heading">
                    <div class="heading-title">
                        <svg viewBox="0 0 32 32" aria-hidden="true"><rect x="4" y="7" width="24" height="21" rx="3"/><path d="M10 3v8M22 3v8M4 13h24M10 18h2m7 0h2M10 23h2m7 0h2"/></svg>
                        <div><h2 id="events-title">Events This Month</h2><p><?php echo portalEscape($today->format('F Y')); ?></p></div>
                    </div>
                </header>
                <?php if ($portalDataError): ?>
                    <p class="empty-state" role="status">Events are temporarily unavailable.</p>
                <?php elseif (!$events): ?>
                    <p class="empty-state">There are no events scheduled for this month.</p>
                <?php else: ?>
                    <div class="event-list">
                        <?php foreach ($events as $event): $eventDate = new DateTimeImmutable($event['event_date']); ?>
                            <article class="event-item">
                                <time class="event-date" datetime="<?php echo portalEscape($eventDate->format('Y-m-d')); ?>"><span><?php echo portalEscape(strtoupper($eventDate->format('M'))); ?></span><strong><?php echo portalEscape($eventDate->format('d')); ?></strong><small><?php echo portalEscape($eventDate->format('Y')); ?></small></time>
                                <div class="event-copy">
                                    <h3><?php echo portalEscape($event['title']); ?></h3>
                                    <p><span aria-hidden="true">&#x25F7;</span> <?php echo !empty($event['start_time']) ? portalEscape(date('g:i A', strtotime($event['start_time'])) . (!empty($event['end_time']) ? ' – ' . date('g:i A', strtotime($event['end_time'])) : '')) : 'Time to be announced'; ?></p>
                                    <p><span aria-hidden="true">&#x25C9;</span> Venue: <?php echo portalEscape($event['location'] ?: 'To be announced'); ?></p>
                                </div>
                                <?php if ($event['status'] === 'completed'): ?>
                                    <span class="event-register event-completed" aria-label="Event completed">Completed</span>
                                <?php else: ?>
                                    <a class="event-register" href="/BMirk/auth/login.php?portal=senior">Register <span aria-hidden="true">&#x2192;</span></a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel announcements-panel" id="announcements" aria-labelledby="announcements-title">
                <header class="panel-heading">
                    <div class="heading-title">
                        <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M4 14v5h5l13 7V7L9 14H4Zm5 5 3 8h5l-4-7M25 12l4-3m-4 14 4 3M26 16h4"/></svg>
                        <div><h2 id="announcements-title">Latest Announcements</h2><p>Important updates from OSCA</p></div>
                    </div>
                </header>
                <?php if ($portalDataError): ?>
                    <p class="empty-state" role="status">Announcements are temporarily unavailable.</p>
                <?php elseif (!$announcements): ?>
                    <p class="empty-state">There are no current announcements.</p>
                <?php else: ?>
                    <div class="announcement-list">
                        <?php foreach ($announcements as $announcement): ?>
                            <article class="announcement-item">
                                <span class="announcement-icon" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M4 14v5h5l13 7V7L9 14H4Zm5 5 3 8h5l-4-7M25 12l4-3m-4 14 4 3M26 16h4"/></svg></span>
                                <div class="announcement-copy">
                                    <time class="announcement-date" datetime="<?php echo portalEscape(date('Y-m-d', strtotime($announcement['display_date']))); ?>"><?php echo portalEscape(date('M d, Y', strtotime($announcement['display_date']))); ?></time>
                                    <h3><?php echo portalEscape($announcement['title']); ?></h3>
                                    <p><?php echo nl2br(portalEscape($announcement['content'])); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="site-footer" id="contact">
        <div class="footer-main">
            <section class="footer-identity" aria-label="InfoConnect">
                <div class="footer-brand"><img src="/BMirk/auth/assets/images/mandaluyong_seal_footer.png" alt="Mandaluyong City seal"><div><strong>INFOCONNECT</strong><span>Mandaluyong City</span><small>Office for Senior Citizens Affairs</small></div></div>
                <p class="footer-summary">Public information and community updates for senior citizens.</p>
            </section>
            <section class="footer-contact" aria-labelledby="footer-contact-title">
                <h2 id="footer-contact-title">Contact Information</h2>
                <p><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span> Acacia Lane Extension, Barangay Addition Hills, Mandaluyong City</p>
                <p><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6.6 3.5h3l1.5 4-2 1.8a16 16 0 0 0 5.6 5.6l1.8-2 4 1.5v3c0 1.1-.9 2-2 2C10.4 19.4 4.6 13.6 4.6 5.5c0-1.1.9-2 2-2Z"/></svg></span> <a href="tel:87219925">8721-99-25</a></p>
                <p><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span> <a href="mailto:oscamandaluyong@gmail.com">oscamandaluyong@gmail.com</a></p>
                <p><span class="contact-icon contact-at" aria-hidden="true">@</span> <a href="https://www.facebook.com/OSCAMandaluyong" target="_blank" rel="noopener noreferrer">OSCA Mandaluyong</a></p>
            </section>
            <section class="footer-about" id="about-osca" aria-labelledby="footer-about-title">
                <h2 id="footer-about-title">About InfoConnect</h2>
                <p>InfoConnect is a web-based information system developed for the Office for Senior Citizens Affairs (OSCA) of Mandaluyong City. It provides a centralized platform where senior citizens can access approved events, important announcements, and other relevant information.</p>
            </section>
        </div>
        <div class="footer-bottom"><span>&copy; 2026 InfoConnect · OSCA Mandaluyong City</span><span>Public information and community updates</span></div>
    </footer>

    <div class="drawer-backdrop" id="login-backdrop" hidden></div>
    <aside class="login-drawer" id="login-drawer" role="dialog" aria-modal="true" aria-labelledby="login-drawer-title" aria-hidden="true" inert>
        <div class="drawer-heading"><h2 id="login-drawer-title">Sign in to InfoConnect</h2><button class="drawer-close" id="login-close" type="button" aria-label="Close login">&#x2715;</button></div>
        <iframe class="login-frame" id="login-frame" src="/BMirk/auth/login.php?embed=1" title="InfoConnect login" loading="lazy"></iframe>
    </aside>
    <script>
        (() => {
            const openButton = document.getElementById('login-open');
            const drawer = document.getElementById('login-drawer');
            const backdrop = document.getElementById('login-backdrop');
            const closeButton = document.getElementById('login-close');
            const frame = document.getElementById('login-frame');
            function openLogin() {
                drawer.inert = false;
                drawer.setAttribute('aria-hidden', 'false');
                drawer.classList.add('is-open');
                backdrop.hidden = false;
                document.body.classList.add('drawer-open');
                window.setTimeout(() => frame.focus(), 280);
            }
            function closeLogin() {
                drawer.classList.remove('is-open');
                drawer.inert = true;
                drawer.setAttribute('aria-hidden', 'true');
                backdrop.hidden = true;
                document.body.classList.remove('drawer-open');
                openButton.focus();
            }
            openButton.addEventListener('click', event => { event.preventDefault(); openLogin(); });
            closeButton.addEventListener('click', closeLogin);
            backdrop.addEventListener('click', closeLogin);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && drawer.classList.contains('is-open')) closeLogin();
            });
            const loginUrl = new URL(window.location.href);
            if (loginUrl.searchParams.get('login') === '1') {
                const frameUrl = new URL('/BMirk/auth/login.php', window.location.origin);
                frameUrl.searchParams.set('embed', '1');
                const formMode = loginUrl.searchParams.get('form');
                const portal = loginUrl.searchParams.get('portal');
                if (['forgot', 'forgot-reset'].includes(formMode)) frameUrl.searchParams.set('form', formMode);
                if (['admin', 'staff', 'senior'].includes(portal)) frameUrl.searchParams.set('portal', portal);
                frame.src = frameUrl.toString();
                openLogin();
                ['login', 'form', 'portal'].forEach(key => loginUrl.searchParams.delete(key));
                window.history.replaceState({}, '', loginUrl.pathname + loginUrl.search + loginUrl.hash);
            }
        })();
    </script>
</body>
</html>

