<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: /BMirk/auth/login.php');
    exit;
}

$page_title = 'Staff Dashboard';
$dashboardError = '';
$metrics = ['seniors' => 0, 'events' => 0, 'announcements' => 0, 'sms_sent' => 0];
$ageGroups = ['60-69' => 0, '70-79' => 0, '80-89' => 0, '90+' => 0];
$ageGroupTotal = 0;
$ageColors = ['#147dff', '#12b981', '#f4b323', '#f16b70'];
$ageGradient = 'conic-gradient(#e5edf5 0 100%)';
$sexCounts = ['Female' => 0, 'Male' => 0];
$sexGradient = 'conic-gradient(#147dff 0 50%, #f16b70 50% 100%)';
$trendPeriod = isset($_GET['trend_period']) && is_string($_GET['trend_period']) && in_array($_GET['trend_period'], ['monthly', 'quarterly', 'yearly'], true)
    ? $_GET['trend_period']
    : 'monthly';
$seniorTrend = [];
$trendLabels = [];
$trendMaximum = 1;
$barangayCounts = [];
$barangayMaximum = 1;
$eventRegistrationRows = [];
$registrationMaximum = 1;
$recentSeniors = [];

function escapeStaffDashboard($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staffDashboardBarPercent(int $value, int $maximum): int
{
    return $maximum > 0 && $value > 0 ? max(6, (int) round(($value / $maximum) * 100)) : 0;
}

try {
    $metricResult = $databaseConnection->query(
        "SELECT
            (SELECT COUNT(*) FROM senior_profiles WHERE status <> 'deceased') AS seniors,
            (SELECT COUNT(*) FROM events) AS events,
            (SELECT COUNT(*) FROM announcements) AS announcements,
            (SELECT COUNT(*) FROM sms_recipients WHERE delivery_status IN ('sent', 'delivered')) AS sms_sent"
    );
    $metricRow = $metricResult->fetch_assoc() ?: [];
    foreach (array_keys($metrics) as $metric) {
        $metrics[$metric] = (int) ($metricRow[$metric] ?? 0);
    }

    $ageResult = $databaseConnection->query(
        "SELECT
            SUM(TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 60 AND 69) AS age_60_69,
            SUM(TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 70 AND 79) AS age_70_79,
            SUM(TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 80 AND 89) AS age_80_89,
            SUM(TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 90) AS age_90_plus
         FROM senior_profiles
         WHERE status <> 'deceased'"
    );
    $ageRow = $ageResult->fetch_assoc() ?: [];
    $ageGroups = [
        '60-69' => (int) ($ageRow['age_60_69'] ?? 0),
        '70-79' => (int) ($ageRow['age_70_79'] ?? 0),
        '80-89' => (int) ($ageRow['age_80_89'] ?? 0),
        '90+' => (int) ($ageRow['age_90_plus'] ?? 0),
    ];
    $ageGroupTotal = array_sum($ageGroups);
    $ageStops = [];
    $ageOffset = 0.0;
    foreach ($ageGroups as $index => $count) {
        $nextOffset = $ageGroupTotal > 0 ? $ageOffset + ($count / $ageGroupTotal * 100) : 0;
        $ageStops[] = $ageColors[array_search($index, array_keys($ageGroups), true)] . ' ' . $ageOffset . '% ' . $nextOffset . '%';
        $ageOffset = $nextOffset;
    }
    if ($ageGroupTotal > 0) {
        $ageGradient = 'conic-gradient(' . implode(', ', $ageStops) . ')';
    }

    $sexResult = $databaseConnection->query(
        "SELECT sex, COUNT(*) AS total FROM senior_profiles
         WHERE status <> 'deceased' GROUP BY sex"
    );
    while ($row = $sexResult->fetch_assoc()) {
        $sexLabel = ucfirst((string) $row['sex']);
        if (isset($sexCounts[$sexLabel])) $sexCounts[$sexLabel] = (int) $row['total'];
    }
    $sexTotal = array_sum($sexCounts);
    $femaleStop = $sexTotal > 0 ? ($sexCounts['Female'] / $sexTotal * 100) : 50;
    $sexGradient = 'conic-gradient(#147dff 0 ' . $femaleStop . '%, #f16b70 ' . $femaleStop . '% 100%)';

    $currentMonth = (int) date('n');
    $currentYear = (int) date('Y');
    if ($trendPeriod === 'quarterly') {
        $trendBucketExpression = 'QUARTER(created_at)';
        $trendBuckets = range(1, (int) ceil($currentMonth / 3));
        $trendLabels = array_map(static fn(int $quarter): string => 'Q' . $quarter, $trendBuckets);
        $trendStart = date('Y-01-01 00:00:00');
        $trendEnd = date('Y-01-01 00:00:00', strtotime('+1 year'));
        $trendDescription = 'Total senior records added per quarter this year';
    } elseif ($trendPeriod === 'yearly') {
        $trendBucketExpression = 'YEAR(created_at)';
        $trendBuckets = range($currentYear - 4, $currentYear);
        $trendLabels = array_map('strval', $trendBuckets);
        $trendStart = date('Y-01-01 00:00:00', strtotime('-4 years'));
        $trendEnd = date('Y-01-01 00:00:00', strtotime('+1 year'));
        $trendDescription = 'Total senior records added per year for the last five years';
    } else {
        $trendBucketExpression = 'MONTH(created_at)';
        $trendBuckets = range(1, $currentMonth);
        $trendLabels = array_map(static fn(int $month): string => date('M', mktime(0, 0, 0, $month, 1)), $trendBuckets);
        $trendStart = date('Y-01-01 00:00:00');
        $trendEnd = date('Y-m-01 00:00:00', strtotime('first day of next month'));
        $trendDescription = 'Total senior records added per month this year';
    }
    $seniorTrend = array_fill_keys($trendBuckets, 0);
    $trendStatement = $databaseConnection->prepare(
        "SELECT {$trendBucketExpression} AS trend_bucket, COUNT(*) AS total
         FROM senior_profiles WHERE created_at >= ? AND created_at < ?
         GROUP BY trend_bucket ORDER BY trend_bucket"
    );
    $trendStatement->bind_param('ss', $trendStart, $trendEnd);
    $trendStatement->execute();
    $trendResult = $trendStatement->get_result();
    while ($row = $trendResult->fetch_assoc()) {
        $bucket = (int) $row['trend_bucket'];
        if (isset($seniorTrend[$bucket])) $seniorTrend[$bucket] = (int) $row['total'];
    }
    $trendStatement->close();
    $trendMaximum = max(1, ...array_values($seniorTrend ?: [0]));

    $barangayResult = $databaseConnection->query(
        "SELECT barangay, COUNT(*) AS total
         FROM senior_profiles
         WHERE status <> 'deceased'
         GROUP BY barangay
         ORDER BY total DESC, barangay ASC
         LIMIT 5"
    );
    while ($row = $barangayResult->fetch_assoc()) {
        $barangayCounts[$row['barangay'] ?: 'Unspecified'] = (int) $row['total'];
    }
    $barangayMaximum = max(1, ...array_values($barangayCounts ?: [0]));

    $registrationResult = $databaseConnection->query(
        "SELECT e.title, e.event_date, COUNT(er.id) AS registrations
         FROM events e
         LEFT JOIN event_registrations er
           ON er.event_id = e.id AND er.status IN ('pending', 'approved')
         WHERE e.status IN ('approved', 'published', 'completed')
         GROUP BY e.id, e.title, e.event_date
         ORDER BY registrations DESC, e.event_date DESC, e.id DESC
         LIMIT 5"
    );
    while ($row = $registrationResult->fetch_assoc()) $eventRegistrationRows[] = $row;
    $registrationMaximum = max(1, ...array_map(static fn(array $row): int => (int) $row['registrations'], $eventRegistrationRows ?: [['registrations' => 0]]));

    $seniorResult = $databaseConnection->query(
        'SELECT senior_citizen_id, first_name, middle_name, last_name, suffix, barangay, created_at
         FROM senior_profiles
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY created_at DESC, id DESC'
    );
    while ($row = $seniorResult->fetch_assoc()) {
        $recentSeniors[] = $row;
    }

    $trendPoints = [];
    $trendLastIndex = max(1, count($seniorTrend) - 1);
    $trendAxisMaximum = max(50, (int) (ceil($trendMaximum / 50) * 50));
    foreach (array_values($seniorTrend) as $index => $count) {
        $trendPoints[] = [
            'x' => 46 + ($index / $trendLastIndex * 574),
            'y' => 175 - ((int) $count / $trendAxisMaximum * 150),
            'count' => (int) $count,
            'label' => (string) $trendLabels[$index],
        ];
    }
    $trendLine = implode(' ', array_map(static fn(array $point): string => number_format($point['x'], 1, '.', '') . ',' . number_format($point['y'], 1, '.', ''), $trendPoints));
    $trendArea = 'M 46 175 L ' . str_replace(',', ' ', $trendLine) . ' L 620 175 Z';

} catch (Throwable $exception) {
    error_log('Unable to load staff dashboard: ' . $exception->getMessage());
    $dashboardError = 'Some dashboard information could not be loaded. Refresh the page or contact an administrator.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeStaffDashboard($page_title); ?></title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="dashboard.css?v=19">

    <link rel="stylesheet" href="assets/table.css?v=17">
</head>
<body class="staff-dashboard-body">
    <div class="staff-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="staff-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <section class="staff-content staff-dashboard-page">
                <header class="staff-dashboard-heading">
                    <h1>Dashboard</h1>
                    <p>Overview of senior records, posts, and event activities.</p>
                </header>
                <?php if ($dashboardError !== ''): ?>
                    <div class="dashboard-alert" role="alert"><?php echo escapeStaffDashboard($dashboardError); ?></div>
                <?php endif; ?>

                <div class="staff-dashboard-stats">
                    <article class="dashboard-stat stat-blue"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span><div class="stat-content"><span>Total Seniors</span><strong><?php echo number_format($metrics['seniors']); ?></strong><small>Registered senior records</small></div></article>
                    <article class="dashboard-stat stat-green"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3m-3 3h8"/></svg></span><div class="stat-content"><span>Total Events</span><strong><?php echo number_format($metrics['events']); ?></strong><small>Events in the system</small></div></article>
                    <article class="dashboard-stat stat-amber"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7m-7 4h7"/></svg></span><div class="stat-content"><span>Total Announcements</span><strong><?php echo number_format($metrics['announcements']); ?></strong><small>Announcements in the system</small></div></article>
                    <article class="dashboard-stat stat-red"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H9l-5 3z"/><path d="m7 9 5 3 5-3"/></svg></span><div class="stat-content"><span>SMS Sent</span><strong><?php echo number_format($metrics['sms_sent']); ?></strong><small>Sent or delivered messages</small></div></article>
                </div>

                <div class="staff-dashboard-chart-row dashboard-chart-primary">
                    <section class="dashboard-card chart-card trend-card">
                        <header class="dashboard-card-heading"><div><h2>Senior Records Trend</h2><small><?php echo escapeStaffDashboard($trendDescription ?? 'Senior record additions'); ?></small></div>
                            <form class="trend-filter-form" method="get" action="">
                                <label class="visually-hidden" for="trendPeriod">Trend interval</label>
                                <select id="trendPeriod" name="trend_period" onchange="this.form.submit()">
                                    <option value="monthly" <?php echo $trendPeriod === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="quarterly" <?php echo $trendPeriod === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                    <option value="yearly" <?php echo $trendPeriod === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                </select>
                            </form>
                        </header>
                        <div class="trend-chart-wrap">
                            <svg class="trend-chart" viewBox="0 0 650 220" role="img" aria-label="<?php echo escapeStaffDashboard(ucfirst($trendPeriod) . ' senior record additions'); ?>">
                                <?php for ($tick = 0; $tick <= 4; $tick++): $tickValue = (int) round($trendAxisMaximum * (4 - $tick) / 4); $tickY = 25 + ($tick * 37.5); ?>
                                    <line x1="46" y1="<?php echo $tickY; ?>" x2="620" y2="<?php echo $tickY; ?>" class="trend-gridline" />
                                    <text x="35" y="<?php echo $tickY + 4; ?>" class="trend-axis-label" text-anchor="end"><?php echo number_format($tickValue); ?></text>
                                <?php endfor; ?>
                                <path d="<?php echo escapeStaffDashboard($trendArea); ?>" class="trend-area" />
                                <polyline points="<?php echo escapeStaffDashboard($trendLine); ?>" class="trend-line" />
                                <?php foreach ($trendPoints as $point): ?>
                                    <circle cx="<?php echo number_format($point['x'], 1, '.', ''); ?>" cy="<?php echo number_format($point['y'], 1, '.', ''); ?>" r="4.5" class="trend-point"><title><?php echo escapeStaffDashboard($point['label'] . ': ' . number_format($point['count']) . ' seniors'); ?></title></circle>
                                    <text x="<?php echo number_format($point['x'], 1, '.', ''); ?>" y="203" class="trend-month-label" text-anchor="middle"><?php echo escapeStaffDashboard($point['label']); ?></text>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                    </section>
                    <section class="dashboard-card chart-card age-card">
                        <header class="dashboard-card-heading"><div><h2>Seniors by Age Group</h2><small>Distribution by age range</small></div></header>
                        <div class="age-chart-layout">
                            <div class="age-donut" style="background: <?php echo escapeStaffDashboard($ageGradient); ?>"><div class="age-donut-hole"><strong><?php echo number_format($ageGroupTotal); ?></strong><small>Total Seniors</small></div></div>
                            <div class="age-legend">
                                <?php foreach ($ageGroups as $index => $count): ?>
                                    <?php $colorIndex = array_search($index, array_keys($ageGroups), true); ?>
                                    <div><i style="background: <?php echo $ageColors[$colorIndex]; ?>"></i><span><?php echo escapeStaffDashboard($index === '90+' ? '90 and above' : str_replace('-', ' - ', $index)); ?></span><strong><?php echo number_format($count); ?></strong></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="staff-dashboard-chart-row dashboard-chart-secondary">
                    <section class="dashboard-card chart-card sex-card">
                        <header class="dashboard-card-heading"><div><h2>Seniors by Sex</h2><small>Distribution of senior citizens by sex</small></div></header>
                        <div class="age-chart-layout sex-chart-layout">
                            <div class="age-donut sex-donut" style="background: <?php echo escapeStaffDashboard($sexGradient); ?>"><div class="age-donut-hole"><strong><?php echo number_format(array_sum($sexCounts)); ?></strong><small>Total Seniors</small></div></div>
                            <div class="age-legend sex-legend">
                                <?php foreach ($sexCounts as $sex => $count): ?>
                                    <div><i class="sex-color-<?php echo strtolower($sex); ?>"></i><span><?php echo escapeStaffDashboard($sex); ?></span><strong><?php echo number_format($count); ?></strong></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                    <section class="dashboard-card chart-card barangay-card">
                        <header class="dashboard-card-heading"><div><h2>Seniors by Barangay</h2><small>Top 5 barangays by senior records</small></div></header>
                        <?php if ($barangayCounts): ?><div class="horizontal-bar-list" role="img" aria-label="Top five barangays by senior record count">
                            <?php foreach ($barangayCounts as $barangay => $count): ?><div class="horizontal-bar-row"><span title="<?php echo escapeStaffDashboard($barangay); ?>"><?php echo escapeStaffDashboard($barangay); ?></span><div class="horizontal-bar-track"><i style="width: <?php echo staffDashboardBarPercent($count, $barangayMaximum); ?>%"></i></div><strong><?php echo number_format($count); ?></strong></div><?php endforeach; ?>
                        </div><?php else: ?><p class="dashboard-empty">No senior records to show.</p><?php endif; ?>
                    </section>
                    <section class="dashboard-card chart-card registrations-card">
                        <header class="dashboard-card-heading"><div><h2>Event Registrations</h2><small>Registrations for approved events</small></div></header>
                        <?php if ($eventRegistrationRows): ?><div class="registration-chart" role="img" aria-label="Event registration totals for the top five events">
                            <?php foreach ($eventRegistrationRows as $eventRegistration): ?>
                                <?php $eventTitle = (string) $eventRegistration['title']; $eventShortTitle = function_exists('mb_strlen') && mb_strlen($eventTitle) > 14 ? mb_substr($eventTitle, 0, 12) . '…' : (strlen($eventTitle) > 14 ? substr($eventTitle, 0, 12) . '...' : $eventTitle); ?>
                                <div class="registration-column"><strong><?php echo number_format((int) $eventRegistration['registrations']); ?></strong><div class="registration-track"><i style="height: <?php echo staffDashboardBarPercent((int) $eventRegistration['registrations'], $registrationMaximum); ?>%"></i></div><small title="<?php echo escapeStaffDashboard($eventTitle); ?>"><?php echo escapeStaffDashboard($eventShortTitle); ?></small></div>
                            <?php endforeach; ?>
                        </div><?php else: ?><p class="dashboard-empty">No event registrations to show.</p><?php endif; ?>
                    </section>
                </div>
                <div class="staff-dashboard-table-row">
                    <section class="dashboard-card table-card">
                        <header class="dashboard-card-heading"><div><h2>Recently Added Seniors</h2><small>Records added in the past 7 days</small></div><a href="/BMirk/staff/seniors/accounts/seniors_accounts.php">View All</a></header>
                        <div class="table-scroll"><table><thead><tr><th>#</th><th>Senior ID</th><th>Name</th><th>Barangay</th><th>Date Added</th></tr></thead><tbody>
                            <?php if (!$recentSeniors): ?>
                                <tr><td colspan="5" class="empty-cell">No seniors were added in the past 7 days.</td></tr>
                            <?php else: foreach ($recentSeniors as $index => $senior): ?>
                                <?php $seniorName = trim($senior['first_name'] . ' ' . ($senior['middle_name'] ? $senior['middle_name'] . ' ' : '') . $senior['last_name'] . ($senior['suffix'] ? ' ' . $senior['suffix'] : '')); ?>
                                <tr><td><?php echo $index + 1; ?></td><td><?php echo escapeStaffDashboard($senior['senior_citizen_id']); ?></td><td><?php echo escapeStaffDashboard($seniorName); ?></td><td><?php echo escapeStaffDashboard($senior['barangay']); ?></td><td><?php echo escapeStaffDashboard(date('M j, Y g:i A', strtotime($senior['created_at']))); ?></td></tr>
                            <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </section>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
