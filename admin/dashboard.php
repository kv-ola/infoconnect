<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$page_title = 'Admin Dashboard';
$dashboardError = '';
$counts = [
	'seniors' => 0,
	'seniors_with_account' => 0,
	'pending_requests' => 0,
	'pending_events' => 0,
	'pending_announcements' => 0,
	'admins' => 0,
	'staff' => 0,
	'personnel' => 0,
	'active_personnel' => 0,
	'inactive_personnel' => 0,
];
$ageGroups = ['60-64' => 0, '65-69' => 0, '70-74' => 0, '75-79' => 0, '80-84' => 0, '85+' => 0];
$sexCounts = ['male' => 0, 'female' => 0];
$barangayCounts = [];
$loginCounts = ['Last 7 Days' => 0, 'Last 30 Days' => 0, 'Over 30 Days' => 0, 'Never Logged In' => 0];

function adminDashboardQuery(mysqli $connection, string $sql): mysqli_result
{
	$result = $connection->query($sql);
	if (!$result) {
		throw new RuntimeException($connection->error);
	}
	return $result;
}

function adminDashboardEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminDashboardBarPercent(int $value, int $maximum): int
{
	return $maximum > 0 ? max(6, (int) round(($value / $maximum) * 100)) : 0;
}

try {
	$seniorSummary = adminDashboardQuery(
		$databaseConnection,
		"SELECT COUNT(*) AS total,
		        SUM(user_id IS NOT NULL) AS with_account,
		        SUM(sex = 'male') AS male,
		        SUM(sex = 'female') AS female,
		        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 60 AND 64 THEN 1 ELSE 0 END) AS age_60_64,
		        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 65 AND 69 THEN 1 ELSE 0 END) AS age_65_69,
		        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 70 AND 74 THEN 1 ELSE 0 END) AS age_70_74,
		        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 75 AND 79 THEN 1 ELSE 0 END) AS age_75_79,
		        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 80 AND 84 THEN 1 ELSE 0 END) AS age_80_84,
		        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 85 THEN 1 ELSE 0 END) AS age_85_plus
		 FROM senior_profiles
		 WHERE status <> 'deceased'"
	);
	$seniorRow = $seniorSummary->fetch_assoc() ?: [];
	$counts['seniors'] = (int) ($seniorRow['total'] ?? 0);
	$counts['seniors_with_account'] = (int) ($seniorRow['with_account'] ?? 0);
	$sexCounts['male'] = (int) ($seniorRow['male'] ?? 0);
	$sexCounts['female'] = (int) ($seniorRow['female'] ?? 0);
	$ageGroups = [
		'60-64' => (int) ($seniorRow['age_60_64'] ?? 0),
		'65-69' => (int) ($seniorRow['age_65_69'] ?? 0),
		'70-74' => (int) ($seniorRow['age_70_74'] ?? 0),
		'75-79' => (int) ($seniorRow['age_75_79'] ?? 0),
		'80-84' => (int) ($seniorRow['age_80_84'] ?? 0),
		'85+' => (int) ($seniorRow['age_85_plus'] ?? 0),
	];

	$personnelResult = adminDashboardQuery(
		$databaseConnection,
		"SELECT role, status, COUNT(*) AS total
		 FROM users
		 WHERE role IN ('admin', 'staff')
		 GROUP BY role, status"
	);
	while ($row = $personnelResult->fetch_assoc()) {
		$total = (int) $row['total'];
		if ($row['role'] === 'admin') {
			$counts['admins'] += $total;
		} else {
			$counts['staff'] += $total;
		}
		$counts[$row['status'] === 'active' ? 'active_personnel' : 'inactive_personnel'] += $total;
	}
	$counts['personnel'] = $counts['admins'] + $counts['staff'];

	$requestSummary = adminDashboardQuery(
		$databaseConnection,
		"SELECT COUNT(*) AS total,
		        SUM(request_type = 'event') AS events,
		        SUM(request_type = 'announcement') AS announcements
		 FROM approval_requests
		 WHERE status = 'pending'"
	);
	$requestRow = $requestSummary->fetch_assoc() ?: [];
	$counts['pending_requests'] = (int) ($requestRow['total'] ?? 0);
	$counts['pending_events'] = (int) ($requestRow['events'] ?? 0);
	$counts['pending_announcements'] = (int) ($requestRow['announcements'] ?? 0);

	$barangayResult = adminDashboardQuery(
		$databaseConnection,
		"SELECT barangay, COUNT(*) AS total
		 FROM senior_profiles
		 WHERE status <> 'deceased'
		 GROUP BY barangay
		 ORDER BY total DESC, barangay ASC"
	);
	while ($row = $barangayResult->fetch_assoc()) {
		$barangayCounts[$row['barangay'] ?: 'Unspecified'] = (int) $row['total'];
	}

	$loginResult = adminDashboardQuery(
		$databaseConnection,
		"SELECT
		    SUM(last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS last_7_days,
		    SUM(last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND last_login < DATE_SUB(NOW(), INTERVAL 7 DAY)) AS last_30_days,
		    SUM(last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)) AS over_30_days,
		    SUM(last_login IS NULL) AS never_logged_in
		 FROM users
		 WHERE role = 'senior'"
	);
	$loginRow = $loginResult->fetch_assoc() ?: [];
	$loginCounts = [
		'Last 7 Days' => (int) ($loginRow['last_7_days'] ?? 0),
		'Last 30 Days' => (int) ($loginRow['last_30_days'] ?? 0),
		'Over 30 Days' => (int) ($loginRow['over_30_days'] ?? 0),
		'Never Logged In' => (int) ($loginRow['never_logged_in'] ?? 0),
	];

} catch (RuntimeException $exception) {
	error_log('Unable to load admin dashboard data: ' . $exception->getMessage());
	$dashboardError = 'Some dashboard data could not be loaded. Please refresh or contact an administrator.';
}

$accountCoverage = $counts['seniors'] > 0
	? round(($counts['seniors_with_account'] / $counts['seniors']) * 100, 1)
	: 0;
$withoutAccount = max(0, $counts['seniors'] - $counts['seniors_with_account']);
$activePersonnelPercent = $counts['personnel'] > 0
	? round(($counts['active_personnel'] / $counts['personnel']) * 100)
	: 0;
$displayBarangays = $barangayCounts;
$ageMaximum = max(1, ...array_values($ageGroups));
$loginMaximum = max(1, ...array_values($loginCounts));
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo adminDashboardEscape($page_title); ?></title>
	<link rel="stylesheet" href="/BMirk/admin/dashboard.css?v=3">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/includes/header.php'; ?>
			<section class="dashboard-content">
				<?php if ($dashboardError !== ''): ?>
					<div class="dashboard-error" role="alert"><?php echo adminDashboardEscape($dashboardError); ?></div>
				<?php endif; ?>

				<div class="dashboard-grid stats-grid">
					<article class="dashboard-card stat-card stat-blue">
						<span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span>
						<div><span class="stat-label">Total Seniors</span><strong class="stat-value"><?php echo number_format($counts['seniors']); ?></strong><span class="stat-note positive">Senior citizen records</span></div>
					</article>
					<article class="dashboard-card stat-card stat-green">
						<span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2z"/></svg></span>
						<div><span class="stat-label">Seniors with Account</span><strong class="stat-value"><?php echo number_format($counts['seniors_with_account']); ?></strong><span class="stat-note positive"><?php echo number_format($accountCoverage, 1); ?>% of seniors</span></div>
					</article>
					<article class="dashboard-card stat-card stat-red">
						<span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2.5h9l5 5v14H6z"/><path d="M14 2.5v5h6M9 12h8m-8 3.5h8m-8 3.5h5"/></svg></span>
						<div><span class="stat-label">Pending Requests</span><strong class="stat-value"><?php echo number_format($counts['pending_requests']); ?></strong><span class="stat-note"><?php echo number_format($counts['pending_events']); ?> Event Requests</span><span class="stat-note"><?php echo number_format($counts['pending_announcements']); ?> Announcement Requests</span></div>
					</article>
					<article class="dashboard-card stat-card stat-purple">
						<span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span>
						<div><span class="stat-label">Total Personnel</span><strong class="stat-value"><?php echo number_format($counts['personnel']); ?></strong><span class="stat-note"><?php echo number_format($counts['admins']); ?> Admin</span><span class="stat-note"><?php echo number_format($counts['staff']); ?> Staff</span></div>
					</article>
				</div>

				<div class="dashboard-grid chart-row">
					<article class="dashboard-card chart-card">
						<h2>Senior Population by Age Group</h2>
						<div class="age-chart">
							<?php foreach ($ageGroups as $group => $value): ?>
								<div class="age-column"><strong><?php echo number_format($value); ?></strong><div class="bar-track"><span class="bar age-bar" style="height: <?php echo adminDashboardBarPercent($value, $ageMaximum); ?>%"></span></div><small><?php echo adminDashboardEscape($group); ?></small></div>
							<?php endforeach; ?>
						</div>
					</article>

					<article class="dashboard-card chart-card">
						<h2>Seniors by Sex</h2>
						<div class="donut-layout">
							<div class="donut sex-donut" style="--male-share: <?php echo $counts['seniors'] > 0 ? round(($sexCounts['male'] / $counts['seniors']) * 100, 1) : 0; ?>%">
								<div class="donut-hole"><strong><?php echo number_format($counts['seniors']); ?></strong><small>Seniors</small></div>
							</div>
							<div class="legend"><div><i class="blue-dot"></i><span>Male</span><strong><?php echo number_format($sexCounts['male']); ?></strong></div><div><i class="pink-dot"></i><span>Female</span><strong><?php echo number_format($sexCounts['female']); ?></strong></div></div>
						</div>
					</article>

					<article class="dashboard-card chart-card barangay-card">
						<h2>Seniors by Barangay</h2>
						<div class="barangay-chart">
							<?php if (empty($displayBarangays)): ?>
								<p class="empty-message">No senior records to show.</p>
							<?php else: ?>
								<?php $barangayMaximum = max(1, ...array_values($displayBarangays)); ?>
								<?php foreach ($displayBarangays as $barangay => $value): ?>
									<div class="barangay-row"><span><?php echo adminDashboardEscape($barangay); ?></span><div class="horizontal-track"><i style="width: <?php echo adminDashboardBarPercent($value, $barangayMaximum); ?>%"></i></div><strong><?php echo number_format($value); ?></strong></div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</article>
				</div>

				<div class="dashboard-grid chart-row lower-chart-row">
					<article class="dashboard-card chart-card">
						<h2>Senior Account Coverage</h2>
						<div class="donut-layout coverage-layout">
							<div class="donut coverage-donut" style="--coverage-share: <?php echo $accountCoverage; ?>%"><div class="donut-hole"><strong><?php echo number_format($accountCoverage, 1); ?>%</strong><small>With Account</small></div></div>
							<div class="legend coverage-legend"><div><i class="green-dot"></i><span>With Account</span><strong><?php echo number_format($counts['seniors_with_account']); ?></strong></div><div><i class="gray-dot"></i><span>Without Account</span><strong><?php echo number_format($withoutAccount); ?></strong></div></div>
						</div>
					</article>

					<article class="dashboard-card chart-card">
						<h2>Senior Account Login Activity</h2>
						<div class="login-chart">
							<?php $loginColors = ['#339cf4', '#36b77d', '#ffc83d', '#f45d68']; ?>
							<?php $loginIndex = 0; foreach ($loginCounts as $label => $value): ?>
								<div class="login-column"><strong><?php echo number_format($value); ?></strong><div class="bar-track"><span class="bar" style="height: <?php echo adminDashboardBarPercent($value, $loginMaximum); ?>%; background: <?php echo $loginColors[$loginIndex++]; ?>"></span></div><small><?php echo adminDashboardEscape($label); ?></small></div>
							<?php endforeach; ?>
						</div>
					</article>

					<article class="dashboard-card chart-card">
						<h2>Personnel Accounts Status</h2>
						<div class="donut-layout coverage-layout">
							<div class="donut personnel-donut" style="--active-share: <?php echo $activePersonnelPercent; ?>%"><div class="donut-hole"><strong><?php echo number_format($counts['personnel']); ?></strong><small>Personnel</small></div></div>
							<div class="legend coverage-legend"><div><i class="green-dot"></i><span>Active</span><strong><?php echo number_format($counts['active_personnel']); ?> (<?php echo $activePersonnelPercent; ?>%)</strong></div><div><i class="red-dot"></i><span>Inactive</span><strong><?php echo number_format($counts['inactive_personnel']); ?> (<?php echo 100 - $activePersonnelPercent; ?>%)</strong></div></div>
						</div>
					</article>
				</div>

			</section>
		</main>
	</div>
</body>
</html>
