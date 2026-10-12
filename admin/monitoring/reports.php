<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/activity_log.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$page_title = 'Reports';
$pageSize = 10;
$pageError = '';
$reportTypes = [
	'senior' => ['name' => 'Senior Citizen Report', 'summary' => 'Senior Citizen Monthly Report'],
	'personnel' => ['name' => 'Personnel Report', 'summary' => 'Personnel Monthly Report'],
];
$reportType = is_string($_GET['type'] ?? null) && isset($reportTypes[$_GET['type']]) ? $_GET['type'] : 'senior';
$month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT);
$month = $month !== false && $month >= 0 && $month <= 12 ? $month : (int) date('n');
$year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
$currentYear = (int) date('Y');
$year = $year !== false && $year >= 2000 && $year <= $currentYear ? $year : $currentYear;
$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$day = filter_var($_GET['day'] ?? 0, FILTER_VALIDATE_INT);
$day = $day !== false && $day >= 0 && $day <= 31 ? $day : 0;
$barangay = trim(is_string($_GET['barangay'] ?? null) ? $_GET['barangay'] : '');
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;
$periodStart = sprintf('%04d-%02d-01 00:00:00', $year, $month === 0 ? 1 : $month);
$periodEnd = $month === 0
	? sprintf('%04d-01-01 00:00:00', $year + 1)
	: (new DateTimeImmutable($periodStart))->modify('first day of next month')->format('Y-m-d H:i:s');
$periodLabel = $month === 0 ? 'All Months ' . $year : date('F Y', strtotime($periodStart));
if ($day > 0) {
	$periodLabel = $month === 0 ? 'Day ' . $day . ', All Months ' . $year : date('F', strtotime($periodStart)) . ' ' . $day . ', ' . $year;
}
$barangayOptions = [];
if ($reportType === 'senior') {
	$barangayResult = $databaseConnection->query("SELECT DISTINCT barangay FROM senior_profiles WHERE TRIM(COALESCE(barangay, '')) <> '' ORDER BY barangay");
	while ($barangayRow = $barangayResult->fetch_assoc()) {
		$barangayOptions[] = (string) $barangayRow['barangay'];
	}
	if ($barangay !== '' && !in_array($barangay, $barangayOptions, true)) {
		$barangay = '';
		$pageError = 'Choose a valid barangay filter.';
	}
}
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$reportRows = [];
$reportTotal = 0;
$reportHeaders = [];
$reportStats = [];
$personnelMetrics = ['active' => 0, 'inactive' => 0, 'admin' => 0, 'staff' => 0];
$reportTables = [];
$sexCounts = ['male' => 0, 'female' => 0];
$accountCounts = ['with_account' => 0, 'without_account' => 0];
$ageGroups = [];
$newSeniorCount = 0;
$pendingRequirements = 0;

function reportsEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function reportsPercentage(int $part, int $total): string
{
	return $total > 0 ? number_format(($part / $total) * 100, 1) . '%' : '0%';
}

function reportsRunQuery(mysqli $connection, string $sql, string $start, string $end, string $search, int $day, int $limit = 0, int $offset = 0): array
{
	if ($limit > 0) {
		$sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
	}
	$statement = $connection->prepare($sql);
	$statement->bind_param('ssssii', $start, $end, $search, $search, $day, $day);
	$statement->execute();
	$result = $statement->get_result();
	$rows = [];
	while ($row = $result->fetch_assoc()) {
		$rows[] = $row;
	}
	$statement->close();
	return $rows;
}

function reportsRunSeniorQuery(mysqli $connection, string $sql, string $start, string $end, string $search, string $barangay, int $day, int $limit = 0, int $offset = 0): array
{
	if ($limit > 0) {
		$sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
	}
	$statement = $connection->prepare($sql);
	$statement->bind_param('ssssssii', $start, $end, $search, $search, $barangay, $barangay, $day, $day);
	$statement->execute();
	$result = $statement->get_result();
	$rows = [];
	while ($row = $result->fetch_assoc()) {
		$rows[] = $row;
	}
	$statement->close();
	return $rows;
}

function reportsSqlParts(string $dateColumn, string $searchColumns): string
{
	return "WHERE {$dateColumn} >= ? AND {$dateColumn} < ?
		AND (? = '' OR CONCAT_WS(' ', {$searchColumns}) LIKE CONCAT('%', ?, '%'))
		AND (? = 0 OR DAY({$dateColumn}) = ?)";
}

$reportQueries = [
	'personnel' => [
		'date' => 'u.created_at',
		'columns' => 'u.account_id, u.role, u.status, ap.employee_id, ap.first_name, ap.last_name, sp.employee_id, sp.first_name, sp.last_name, u.created_at',
		'from' => "FROM users u LEFT JOIN admin_profiles ap ON ap.user_id = u.id LEFT JOIN staff_profiles sp ON sp.user_id = u.id",
		'select' => 'u.account_id AS account_id, u.role AS role, u.status AS status, COALESCE(ap.employee_id, sp.employee_id) AS employee_id, COALESCE(ap.first_name, sp.first_name) AS first_name, COALESCE(ap.last_name, sp.last_name) AS last_name, u.created_at AS date_added',
		'filter' => "u.role IN ('admin', 'staff')",
		'order' => 'u.created_at DESC, u.id DESC',
		'headers' => ['Account ID', 'Role', 'Status', 'Employee ID', 'First Name', 'Last Name', 'Date Added'],
	],
];

function reportsPrepareGenericSql(array $config, string $wantCount): string
{
	$countExpression = $config['count'] ?? 'COUNT(*)';
	$sql = $wantCount === 'count' ? 'SELECT ' . $countExpression . ' AS total ' : 'SELECT ' . $config['select'] . ' ';
	$sql .= $config['from'] . ' ';
	if (isset($config['joins'])) {
		$sql .= $config['joins'] . ' ';
	}
	$sql .= reportsSqlParts($config['date'], $config['columns']);
	$sql .= ' AND ' . $config['filter'] . ' ';
	if ($wantCount !== 'count' && isset($config['group'])) {
		$sql .= $config['group'] . ' ';
	}
	if ($wantCount !== 'count') {
		$sql .= 'ORDER BY ' . $config['order'];
	}
	return $sql;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['export']) && $_GET['export'] === 'excel' && $pageError === '') {
	try {
		$headers = [];
		$exportRows = [];
		if ($reportType === 'senior') {
			$headers = ['Senior ID', 'Full Name', 'Age', 'Sex', 'Barangay', 'Status', 'Account Status', 'Date Added'];
			$sql = "SELECT sp.senior_citizen_id AS senior_id,
					TRIM(CONCAT_WS(' ', sp.first_name, sp.middle_name, sp.last_name, sp.suffix)) AS full_name,
					TIMESTAMPDIFF(YEAR, sp.birth_date, CURDATE()) AS age, sp.sex AS sex, sp.barangay AS barangay,
					CASE WHEN COALESCE(sr.completed_requirements, 0) < 2 THEN 'pending' ELSE sp.status END AS status,
					COALESCE(u.status, 'No Account') AS account_status, sp.created_at AS date_added
					FROM senior_profiles sp
					LEFT JOIN users u ON u.id = sp.user_id
					LEFT JOIN (
						SELECT senior_id, COUNT(DISTINCT requirement_type) AS completed_requirements
						FROM senior_requirements
						WHERE requirement_type IN ('birth_certificate', 'valid_id') AND status <> 'missing'
						GROUP BY senior_id
					) sr ON sr.senior_id = sp.id
					WHERE sp.created_at >= ? AND sp.created_at < ?
					AND (? = '' OR CONCAT_WS(' ', sp.senior_citizen_id, sp.first_name, sp.middle_name, sp.last_name, sp.barangay, sp.sex, sp.status, u.status) LIKE CONCAT('%', ?, '%'))
					AND (? = '' OR sp.barangay = ?)
					AND (? = 0 OR DAY(sp.created_at) = ?)
					ORDER BY sp.created_at DESC, sp.id DESC";
			$exportRows = reportsRunSeniorQuery($databaseConnection, $sql, $periodStart, $periodEnd, $search, $barangay, $day);
		} else {
			$config = $reportQueries[$reportType];
			$headers = $config['headers'];
			$sql = reportsPrepareGenericSql($config, 'rows');
			$exportRows = reportsRunQuery($databaseConnection, $sql, $periodStart, $periodEnd, $search, $day);
		}

		writeActivityLog($databaseConnection, $currentUserId, 'export', 'Reports', $reportTypes[$reportType]['name'] . ' Excel export for ' . $periodLabel . '.');
		header('Content-Type: text/csv; charset=utf-8');
		$monthFileLabel = $month === 0 ? 'all_months' : sprintf('%02d', $month);
		header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . $year . '_' . $monthFileLabel . '.csv"');
		$output = fopen('php://output', 'wb');
		if ($output === false) {
			throw new RuntimeException('The report export could not be opened.');
		}
		fwrite($output, "\xEF\xBB\xBF");
		fputcsv($output, $headers);
		foreach ($exportRows as $row) {
			fputcsv($output, array_map(static function ($value): string {
				$value = (string) ($value ?? '');
				return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
			}, array_values($row)));
		}
		fclose($output);
		exit;
	} catch (Throwable $exception) {
		error_log('Unable to export monthly report: ' . $exception->getMessage());
		$pageError = 'The report could not be exported. Please try again later.';
	}
}

if ($pageError === '') {
	try {
		if ($reportType === 'senior') {
			$seniorSearch = "AND (? = '' OR CONCAT_WS(' ', sp.senior_citizen_id, sp.first_name, sp.middle_name, sp.last_name, sp.barangay, sp.sex, sp.status, u.status) LIKE CONCAT('%', ?, '%'))
				AND (? = '' OR sp.barangay = ?)
				AND (? = 0 OR DAY(sp.created_at) = ?)";
			$countStatement = $databaseConnection->prepare(
				'SELECT COUNT(*) AS total FROM senior_profiles sp LEFT JOIN users u ON u.id = sp.user_id WHERE sp.created_at >= ? AND sp.created_at < ? ' . $seniorSearch
			);
			$countStatement->bind_param('ssssssii', $periodStart, $periodEnd, $search, $search, $barangay, $barangay, $day, $day);
			$countStatement->execute();
			$reportTotal = (int) $countStatement->get_result()->fetch_assoc()['total'];
			$countStatement->close();

			$totalPages = max(1, (int) ceil($reportTotal / $pageSize));
			$page = min($page, $totalPages);
			$offset = ($page - 1) * $pageSize;
			$seniorStatement = $databaseConnection->prepare(
				"SELECT sp.senior_citizen_id AS senior_id,
				        TRIM(CONCAT_WS(' ', sp.first_name, sp.middle_name, sp.last_name, sp.suffix)) AS full_name,
				        TIMESTAMPDIFF(YEAR, sp.birth_date, CURDATE()) AS age,
				        sp.sex, sp.barangay,
				        CASE WHEN COALESCE(sr.completed_requirements, 0) < 2 THEN 'pending' ELSE sp.status END AS status,
				        COALESCE(u.status, 'No Account') AS account_status, sp.created_at AS date_added
				 FROM senior_profiles sp
				 LEFT JOIN users u ON u.id = sp.user_id
				 LEFT JOIN (
					SELECT senior_id, COUNT(DISTINCT requirement_type) AS completed_requirements
					FROM senior_requirements
					WHERE requirement_type IN ('birth_certificate', 'valid_id') AND status <> 'missing'
					GROUP BY senior_id
				 ) sr ON sr.senior_id = sp.id
				 WHERE sp.created_at >= ? AND sp.created_at < ? {$seniorSearch}
				 ORDER BY sp.created_at DESC, sp.id DESC
				 LIMIT {$pageSize} OFFSET {$offset}"
			);
			$seniorStatement->bind_param('ssssssii', $periodStart, $periodEnd, $search, $search, $barangay, $barangay, $day, $day);
			$seniorStatement->execute();
			$seniorResult = $seniorStatement->get_result();
			while ($row = $seniorResult->fetch_assoc()) {
				$reportRows[] = $row;
			}
			$seniorStatement->close();
			$reportHeaders = ['#', 'Senior ID', 'Full Name', 'Age', 'Sex', 'Barangay', 'Status', 'Account Status', 'Date Added'];

			$seniorSummaryStatement = $databaseConnection->prepare(
				"SELECT COUNT(*) AS total,
				        COALESCE(SUM(effective_status = 'active'), 0) AS active,
				        COALESCE(SUM(effective_status = 'pending'), 0) AS pending,
				        COALESCE(SUM(effective_status = 'deceased'), 0) AS deceased,
				        COALESCE(SUM(effective_status = 'transferred'), 0) AS transferred
				 FROM (
					SELECT sp.id,
					       CASE
					           WHEN COUNT(DISTINCT CASE
					               WHEN sr.status <> 'missing' THEN sr.requirement_type
					           END) < 2 THEN 'pending'
					           ELSE LOWER(sp.status)
					       END AS effective_status
					FROM senior_profiles sp
					LEFT JOIN senior_requirements sr
						ON sr.senior_id = sp.id
						AND sr.requirement_type IN ('birth_certificate', 'valid_id')
					WHERE sp.created_at >= ? AND sp.created_at < ? AND (? = '' OR sp.barangay = ?) AND (? = 0 OR DAY(sp.created_at) = ?)
					GROUP BY sp.id, sp.status
				 ) senior_statuses"
			);
			$seniorSummaryStatement->bind_param('ssssii', $periodStart, $periodEnd, $barangay, $barangay, $day, $day);
			$seniorSummaryStatement->execute();
			$seniorSummary = $seniorSummaryStatement->get_result()->fetch_assoc() ?: [];
			$seniorSummaryStatement->close();
			$seniorSummary = array_map('intval', $seniorSummary);
			$pendingRequirements = $seniorSummary['pending'] ?? 0;

			$newSeniorStatement = $databaseConnection->prepare("SELECT COUNT(*) AS total FROM senior_profiles WHERE created_at >= ? AND created_at < ? AND (? = '' OR barangay = ?) AND (? = 0 OR DAY(created_at) = ?)");
			$newSeniorStatement->bind_param('ssssii', $periodStart, $periodEnd, $barangay, $barangay, $day, $day);
			$newSeniorStatement->execute();
			$newSeniorCount = (int) $newSeniorStatement->get_result()->fetch_assoc()['total'];
			$newSeniorStatement->close();

			$sexStatement = $databaseConnection->prepare("SELECT sex, COUNT(*) AS total FROM senior_profiles WHERE created_at >= ? AND created_at < ? AND (? = '' OR barangay = ?) AND (? = 0 OR DAY(created_at) = ?) GROUP BY sex");
			$sexStatement->bind_param('ssssii', $periodStart, $periodEnd, $barangay, $barangay, $day, $day);
			$sexStatement->execute();
			$sexResult = $sexStatement->get_result();
			$sexCounts = ['male' => 0, 'female' => 0];
			while ($row = $sexResult->fetch_assoc()) {
				if (isset($sexCounts[$row['sex']])) {
					$sexCounts[$row['sex']] = (int) $row['total'];
				}
			}
			$sexStatement->close();

			$accountStatement = $databaseConnection->prepare(
				"SELECT SUM(sp.user_id IS NOT NULL) AS with_account, SUM(sp.user_id IS NULL) AS without_account
				 FROM senior_profiles sp WHERE sp.created_at >= ? AND sp.created_at < ? AND (? = '' OR sp.barangay = ?) AND (? = 0 OR DAY(sp.created_at) = ?)"
			);
			$accountStatement->bind_param('ssssii', $periodStart, $periodEnd, $barangay, $barangay, $day, $day);
			$accountStatement->execute();
			$accountResult = $accountStatement->get_result();
			$accountCounts = array_map('intval', $accountResult->fetch_assoc() ?: ['with_account' => 0, 'without_account' => 0]);
			$accountStatement->close();

			$ageStatement = $databaseConnection->prepare(
				"SELECT CASE
					WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 60 AND 64 THEN '60 - 64'
					WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 65 AND 69 THEN '65 - 69'
					WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 70 AND 74 THEN '70 - 74'
					WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 75 AND 79 THEN '75 - 79'
					WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 80 AND 84 THEN '80 - 84'
					ELSE '85+'
				 END AS age_group, COUNT(*) AS total
				 FROM senior_profiles
				 WHERE TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 60
				   AND created_at >= ? AND created_at < ? AND (? = '' OR barangay = ?) AND (? = 0 OR DAY(created_at) = ?)
				 GROUP BY age_group
				 ORDER BY MIN(TIMESTAMPDIFF(YEAR, birth_date, CURDATE()))"
			);
			$ageStatement->bind_param('ssssii', $periodStart, $periodEnd, $barangay, $barangay, $day, $day);
			$ageStatement->execute();
			$ageResult = $ageStatement->get_result();
			$ageGroups = [];
			while ($row = $ageResult->fetch_assoc()) {
				$ageGroups[$row['age_group']] = (int) $row['total'];
			}
			$ageStatement->close();

			$barangayStatement = $databaseConnection->prepare(
				"SELECT barangay, SUM(sex = 'male') AS male, SUM(sex = 'female') AS female, COUNT(*) AS total
				 FROM senior_profiles WHERE created_at >= ? AND created_at < ? AND (? = '' OR barangay = ?) AND (? = 0 OR DAY(created_at) = ?)
				 GROUP BY barangay
				 ORDER BY total DESC, barangay ASC"
			);
			$barangayStatement->bind_param('ssssii', $periodStart, $periodEnd, $barangay, $barangay, $day, $day);
			$barangayStatement->execute();
			$barangayResult = $barangayStatement->get_result();
			$barangayIndex = 0;
			$otherBarangay = ['barangay' => 'Other Barangays', 'male' => 0, 'female' => 0, 'total' => 0];
			while ($row = $barangayResult->fetch_assoc()) {
				if ($barangayIndex++ < 8) {
					$reportTables['barangays'][] = $row;
				} else {
					$otherBarangay['male'] += (int) $row['male'];
					$otherBarangay['female'] += (int) $row['female'];
					$otherBarangay['total'] += (int) $row['total'];
				}
			}
			if ($otherBarangay['total'] > 0) {
				$reportTables['barangays'][] = $otherBarangay;
			}
			$barangayStatement->close();
			$reportStats = [
				'total' => $seniorSummary['total'] ?? 0,
				'active' => $seniorSummary['active'] ?? 0,
				'new' => $newSeniorCount,
				'deceased' => $seniorSummary['deceased'] ?? 0,
				'transferred' => $seniorSummary['transferred'] ?? 0,
				'requirements' => $pendingRequirements,
			];
		} else {
			$config = $reportQueries[$reportType];
			$countSql = reportsPrepareGenericSql($config, 'count');
			$countStatement = $databaseConnection->prepare($countSql);
			$countStatement->bind_param('ssssii', $periodStart, $periodEnd, $search, $search, $day, $day);
			$countStatement->execute();
			$reportTotal = (int) $countStatement->get_result()->fetch_assoc()['total'];
			$countStatement->close();

			$totalPages = max(1, (int) ceil($reportTotal / $pageSize));
			$page = min($page, $totalPages);
			$offset = ($page - 1) * $pageSize;
			$listSql = reportsPrepareGenericSql($config, 'rows') . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset;
			$reportRows = reportsRunQuery($databaseConnection, $listSql, $periodStart, $periodEnd, $search, $day);
			$reportHeaders = array_merge(['#'], $config['headers']);
			$reportStats = ['total' => $reportTotal];
			if ($reportType === 'personnel') {
				$personnelMetricsStatement = $databaseConnection->prepare(
					"SELECT COALESCE(SUM(LOWER(u.status) = 'active'), 0) AS active,
					        COALESCE(SUM(LOWER(u.status) = 'inactive'), 0) AS inactive,
					        COALESCE(SUM(u.role = 'admin'), 0) AS admin,
					        COALESCE(SUM(u.role = 'staff'), 0) AS staff
					 FROM users u
					 WHERE u.role IN ('admin', 'staff')
						AND u.created_at >= ? AND u.created_at < ? AND (? = 0 OR DAY(u.created_at) = ?)"
				);
				$personnelMetricsStatement->bind_param('ssii', $periodStart, $periodEnd, $day, $day);
				$personnelMetricsStatement->execute();
				$personnelMetrics = array_map('intval', $personnelMetricsStatement->get_result()->fetch_assoc());
				$personnelMetricsStatement->close();
			}
		}
	} catch (Throwable $exception) {
		error_log('Unable to load monthly report: ' . $exception->getMessage());
		$pageError = 'The report could not be loaded. Please try again later.';
	}
}

$totalPages = max(1, (int) ceil($reportTotal / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$monthNames = [0 => 'All Months'];
for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
	$monthNames[$monthNumber] = date('F', mktime(0, 0, 0, $monthNumber, 1));
}
$visiblePages = array_values(array_unique(array_filter(
	[1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $totalPages],
	static function ($number) use ($totalPages): bool {
		return $number >= 1 && $number <= $totalPages;
	}
)));
sort($visiblePages);

$seniorTotal = (int) ($reportStats['total'] ?? 0);
$periodPrevious = (new DateTimeImmutable($periodStart))->modify('-1 month')->format('Y-m');
$previousMonthCount = 0;
if ($reportType === 'senior' && $month !== 0 && $pageError === '') {
	$previousMonthStart = $periodPrevious . '-01 00:00:00';
	$previousMonthEnd = (new DateTimeImmutable($previousMonthStart))->modify('first day of next month')->format('Y-m-d H:i:s');
	$previousMonthStatement = $databaseConnection->prepare('SELECT COUNT(*) AS total FROM senior_profiles WHERE created_at >= ? AND created_at < ?');
	$previousMonthStatement->bind_param('ss', $previousMonthStart, $previousMonthEnd);
	$previousMonthStatement->execute();
	$previousMonthCount = (int) $previousMonthStatement->get_result()->fetch_assoc()['total'];
	$previousMonthStatement->close();
}

$queryParameters = ['type' => $reportType, 'month' => $month, 'year' => $year];
if ($day > 0) {
	$queryParameters['day'] = $day;
}
if ($barangay !== '') {
	$queryParameters['barangay'] = $barangay;
}
if ($search !== '') {
	$queryParameters['search'] = $search;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo reportsEscape($page_title); ?></title>
	<link rel="stylesheet" href="../home.css">
	<link rel="stylesheet" href="assets/reports.css?v=13">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>
			<section class="admin-content reports-page">
				<header class="reports-heading">
					<h1>Reports</h1>
					<p>View and generate reports by month, year, day, and barangay.</p>
				</header>

				<?php if ($pageError !== ''): ?><div class="reports-error" role="alert"><?php echo reportsEscape($pageError); ?></div><?php endif; ?>

				<nav class="report-tabs" aria-label="Report types">
					<?php foreach ($reportTypes as $key => $type): ?>
						<a class="report-tab <?php echo $reportType === $key ? 'active' : ''; ?>" href="?<?php echo reportsEscape(http_build_query(array_merge($queryParameters, ['type' => $key]))); ?>" <?php echo $reportType === $key ? 'aria-current="page"' : ''; ?>><?php echo reportsEscape($type['name']); ?></a>
					<?php endforeach; ?>
				</nav>

				<form class="report-period-card" method="get" action="">
					<div class="report-period-heading">
						<strong>Report Period</strong>
					</div>
					<div class="report-period-controls">
						<label for="reportMonth">
							<span class="visually-hidden">Month</span>
							<select id="reportMonth" name="month">
								<?php foreach ($monthNames as $monthNumber => $monthName): ?>
									<option value="<?php echo $monthNumber; ?>" <?php echo $month === $monthNumber ? 'selected' : ''; ?>><?php echo reportsEscape($monthName); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label for="reportYear">
							<span class="visually-hidden">Year</span>
							<select id="reportYear" name="year">
								<?php for ($availableYear = $currentYear; $availableYear >= 2000; $availableYear--): ?>
									<option value="<?php echo $availableYear; ?>" <?php echo $year === $availableYear ? 'selected' : ''; ?>><?php echo $availableYear; ?></option>
								<?php endfor; ?>
							</select>
						</label>
						<label for="reportDay"><span class="visually-hidden">Day</span><select id="reportDay" name="day" aria-label="Filter by day"><option value="0">All Days</option><?php for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++): ?><option value="<?php echo $dayNumber; ?>" <?php echo $day === $dayNumber ? 'selected' : ''; ?>><?php echo $dayNumber; ?></option><?php endfor; ?></select></label>
						<?php if ($reportType === 'senior'): ?>
							<label for="reportBarangay"><span class="visually-hidden">Barangay</span><select id="reportBarangay" name="barangay" aria-label="Filter by barangay"><option value="">All Barangays</option><?php foreach ($barangayOptions as $barangayOption): ?><option value="<?php echo reportsEscape($barangayOption); ?>" <?php echo $barangay === $barangayOption ? 'selected' : ''; ?>><?php echo reportsEscape($barangayOption); ?></option><?php endforeach; ?></select></label>
						<?php endif; ?>
						<input type="hidden" name="type" value="<?php echo reportsEscape($reportType); ?>">
						<button class="report-primary-button" type="submit">Generate Report</button>
					</div>
					<div class="report-export-controls">
						<div class="report-export-dropdown">
							<button class="report-primary-button report-export-toggle" type="button" id="reportExportToggle" aria-expanded="false" aria-controls="reportExportMenu">Export</button>
							<div class="report-export-menu" id="reportExportMenu" hidden>
								<button type="button" data-report-print>Print</button>
								<button type="button" data-report-pdf>Export PDF</button>
								<a href="?<?php echo reportsEscape(http_build_query($queryParameters + ['export' => 'excel'])); ?>">Export CSV</a>
							</div>
						</div>
					</div>
				</form>

				<section class="report-output" aria-labelledby="reportOutputHeading">
					<header class="report-output-heading">
						<h2 id="reportOutputHeading"><?php echo reportsEscape($reportTypes[$reportType]['summary']); ?></h2>
						<p>Summary for <?php echo reportsEscape($periodLabel); ?></p>
					</header>

					<?php if ($reportType === 'senior'): ?>
						<div class="senior-report-metrics">
							<article class="metric-total"><span>Total Seniors</span><strong><?php echo number_format($reportStats['total'] ?? 0); ?></strong><small>All senior citizen records</small></article>
							<article class="metric-active"><span>Active</span><strong><?php echo number_format($reportStats['active'] ?? 0); ?></strong><small><?php echo reportsPercentage((int) ($reportStats['active'] ?? 0), $seniorTotal); ?> of total</small></article>
							<article class="metric-new"><span>Newly Added</span><strong><?php echo number_format($reportStats['new'] ?? 0); ?></strong><small><?php echo reportsPercentage((int) ($reportStats['new'] ?? 0), $seniorTotal); ?> of total</small></article>
							<article class="metric-deceased"><span>Deceased</span><strong><?php echo number_format($reportStats['deceased'] ?? 0); ?></strong><small><?php echo reportsPercentage((int) ($reportStats['deceased'] ?? 0), $seniorTotal); ?> of total</small></article>
							<article class="metric-transferred"><span>Transferred</span><strong><?php echo number_format($reportStats['transferred'] ?? 0); ?></strong><small><?php echo reportsPercentage((int) ($reportStats['transferred'] ?? 0), $seniorTotal); ?> of total</small></article>
							<article class="metric-requirements"><span>Pending</span><strong><?php echo number_format($reportStats['requirements'] ?? 0); ?></strong><small><?php echo reportsPercentage((int) ($reportStats['requirements'] ?? 0), $seniorTotal); ?> of total</small></article>
						</div>

						<div class="senior-breakdown-grid">
							<section class="report-breakdown-card">
								<h3>Sex Distribution</h3>
								<table class="breakdown-table">
									<tbody>
										<tr><td>Male</td><td><?php echo number_format($sexCounts['male']); ?></td><td><?php echo reportsPercentage($sexCounts['male'], $seniorTotal); ?></td></tr>
										<tr><td>Female</td><td><?php echo number_format($sexCounts['female']); ?></td><td><?php echo reportsPercentage($sexCounts['female'], $seniorTotal); ?></td></tr>
										<tr class="breakdown-total"><th>Total</th><th><?php echo number_format($seniorTotal); ?></th><th><?php echo $seniorTotal > 0 ? '100%' : '0%'; ?></th></tr>
									</tbody>
								</table>
							</section>
							<section class="report-breakdown-card">
								<h3>Account Status</h3>
								<table class="breakdown-table">
									<tbody>
										<tr><td>With InfoConnect Account</td><td><?php echo number_format($accountCounts['with_account']); ?></td><td><?php echo reportsPercentage($accountCounts['with_account'], $seniorTotal); ?></td></tr>
										<tr><td>Without Account</td><td><?php echo number_format($accountCounts['without_account']); ?></td><td><?php echo reportsPercentage($accountCounts['without_account'], $seniorTotal); ?></td></tr>
										<tr class="breakdown-total"><th>Total</th><th><?php echo number_format($seniorTotal); ?></th><th><?php echo $seniorTotal > 0 ? '100%' : '0%'; ?></th></tr>
									</tbody>
								</table>
							</section>
							<section class="report-breakdown-card">
								<h3>Age Group</h3>
								<table class="breakdown-table">
									<tbody>
										<?php foreach (['60 - 64', '65 - 69', '70 - 74', '75 - 79', '80 - 84', '85+'] as $ageGroup): ?>
											<?php $ageCount = $ageGroups[$ageGroup] ?? 0; ?>
											<tr><td><?php echo reportsEscape($ageGroup); ?></td><td><?php echo number_format($ageCount); ?></td><td><?php echo reportsPercentage($ageCount, $seniorTotal); ?></td></tr>
										<?php endforeach; ?>
										<tr class="breakdown-total"><th>Total</th><th><?php echo number_format($seniorTotal); ?></th><th><?php echo $seniorTotal > 0 ? '100%' : '0%'; ?></th></tr>
									</tbody>
								</table>
							</section>
						</div>

						<div class="senior-detail-grid">
							<section class="report-breakdown-card barangay-breakdown">
								<h3>Summary by Barangay</h3>
								<div class="report-table-scroll">
									<table class="report-detail-table">
										<thead><tr><th>Barangay</th><th>Male</th><th>Female</th><th>Total</th></tr></thead>
										<tbody>
											<?php $barangayMale = 0; $barangayFemale = 0; $barangayTotal = 0; ?>
											<?php foreach ($reportTables['barangays'] ?? [] as $barangayRow): ?>
												<?php $barangayMale += (int) $barangayRow['male']; $barangayFemale += (int) $barangayRow['female']; $barangayTotal += (int) $barangayRow['total']; ?>
												<tr><td><?php echo reportsEscape($barangayRow['barangay'] ?: 'Unspecified'); ?></td><td><?php echo number_format((int) $barangayRow['male']); ?></td><td><?php echo number_format((int) $barangayRow['female']); ?></td><td><?php echo number_format((int) $barangayRow['total']); ?></td></tr>
											<?php endforeach; ?>
											<tr class="breakdown-total"><th>Total</th><th><?php echo number_format($barangayMale); ?></th><th><?php echo number_format($barangayFemale); ?></th><th><?php echo number_format($barangayTotal); ?></th></tr>
										</tbody>
									</table>
								</div>
							</section>
							<section class="report-breakdown-card senior-records-card">
								<div class="senior-records-heading">
									<h3>Newly Added Senior Citizens Records</h3>
									<form class="report-search-form" method="get" action="">
										<input type="hidden" name="type" value="<?php echo reportsEscape($reportType); ?>">
										<input type="hidden" name="month" value="<?php echo $month; ?>">
										<input type="hidden" name="year" value="<?php echo $year; ?>">
						<?php if ($day > 0): ?><input type="hidden" name="day" value="<?php echo $day; ?>"><?php endif; ?>
						<?php if ($barangay !== ''): ?><input type="hidden" name="barangay" value="<?php echo reportsEscape($barangay); ?>"><?php endif; ?>
										<input type="search" name="search" value="<?php echo reportsEscape($search); ?>" placeholder="Search..." aria-label="Search senior records">
										<button type="submit" aria-label="Search records">⌕</button>
									</form>
								</div>
								<div class="report-table-scroll">
									<table class="report-detail-table senior-records-table">
										<thead><tr><th>#</th><th>Senior ID</th><th>Full Name</th><th>Age</th><th>Sex</th><th>Barangay</th><th>Status</th><th>Account Status</th><th>Date Added</th></tr></thead>
										<tbody>
											<?php if ($pageError !== ''): ?>
												<tr><td class="report-empty" colspan="9">Report records are unavailable.</td></tr>
											<?php elseif (!$reportRows): ?>
												<tr><td class="report-empty" colspan="9"><?php echo $search !== '' ? 'No senior records match your search.' : 'No senior records found.'; ?></td></tr>
											<?php else: ?>
												<?php foreach ($reportRows as $index => $record): ?>
													<tr>
														<td><?php echo $offset + $index + 1; ?></td>
														<td><?php echo reportsEscape($record['senior_id']); ?></td>
														<td><?php echo reportsEscape($record['full_name']); ?></td>
														<td><?php echo (int) $record['age']; ?></td>
														<td><?php echo reportsEscape(ucfirst($record['sex'])); ?></td>
														<td><?php echo reportsEscape($record['barangay']); ?></td>
														<td><span class="report-status status-<?php echo reportsEscape(strtolower($record['status'])); ?>"><?php echo reportsEscape(ucfirst($record['status'])); ?></span></td>
														<td><span class="report-status status-<?php echo reportsEscape(strtolower(str_replace(' ', '-', $record['account_status']))); ?>"><?php echo reportsEscape(ucfirst($record['account_status'])); ?></span></td>
														<td><?php echo reportsEscape(date('M j, Y', strtotime($record['date_added']))); ?></td>
													</tr>
												<?php endforeach; ?>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
								<footer class="report-results-footer">
									<p><?php if ($reportTotal > 0 && $pageError === ''): ?>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($reportRows), $reportTotal); ?> of <?php echo number_format($reportTotal); ?> entries<?php else: ?>Showing 0 entries<?php endif; ?></p>
									<?php if ($totalPages > 1 && $pageError === ''): ?>
										<nav class="report-pagination" aria-label="Senior report pages">
											<a class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => max(1, $page - 1)])); ?>">Previous</a>
											<?php foreach ($visiblePages as $visiblePage): ?>
												<a class="<?php echo $visiblePage === $page ? 'active' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => $visiblePage])); ?>" <?php echo $visiblePage === $page ? 'aria-current="page"' : ''; ?>><?php echo $visiblePage; ?></a>
											<?php endforeach; ?>
											<a class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => min($totalPages, $page + 1)])); ?>">Next</a>
										</nav>
									<?php endif; ?>
								</footer>
							</section>
						</div>
					<?php elseif ($reportType === 'personnel'): ?>
						<div class="personnel-report-metrics" aria-label="Personnel report totals">
							<article class="personnel-metric-total"><span>Total Records</span><strong><?php echo number_format($reportTotal); ?></strong><small><?php echo reportsEscape($periodLabel); ?></small></article>
							<article class="personnel-metric-active"><span>Active</span><strong><?php echo number_format($personnelMetrics['active']); ?></strong><small>Personnel accounts</small></article>
							<article class="personnel-metric-inactive"><span>Inactive</span><strong><?php echo number_format($personnelMetrics['inactive']); ?></strong><small>Personnel accounts</small></article>
							<article class="personnel-metric-admin"><span>Admin</span><strong><?php echo number_format($personnelMetrics['admin']); ?></strong><small>Administrator accounts</small></article>
							<article class="personnel-metric-staff"><span>Staff</span><strong><?php echo number_format($personnelMetrics['staff']); ?></strong><small>Staff accounts</small></article>
						</div>
						<section class="report-breakdown-card generic-records-card">
							<div class="generic-records-heading">
								<h3>Personnel Records</h3>
								<form class="report-search-form" method="get" action="">
									<input type="hidden" name="type" value="<?php echo reportsEscape($reportType); ?>">
									<input type="hidden" name="month" value="<?php echo $month; ?>">
									<input type="hidden" name="year" value="<?php echo $year; ?>">
								<?php if ($day > 0): ?><input type="hidden" name="day" value="<?php echo $day; ?>"><?php endif; ?>
									<input type="search" name="search" value="<?php echo reportsEscape($search); ?>" placeholder="Search..." aria-label="Search personnel records">
									<button type="submit">Search</button>
								</form>
							</div>
							<div class="report-table-scroll">
								<table class="report-detail-table">
									<thead><tr><?php foreach ($reportHeaders as $header): ?><th><?php echo reportsEscape($header); ?></th><?php endforeach; ?></tr></thead>
									<tbody>
										<?php if ($pageError !== ''): ?>
											<tr><td colspan="<?php echo count($reportHeaders); ?>" class="report-empty">Report records are unavailable.</td></tr>
										<?php elseif (!$reportRows): ?>
											<tr><td colspan="<?php echo count($reportHeaders); ?>" class="report-empty"><?php echo $search !== '' ? 'No personnel records match your search.' : 'No personnel records found for this period.'; ?></td></tr>
										<?php else: ?>
											<?php foreach ($reportRows as $index => $record): ?>
												<tr><td><?php echo $offset + $index + 1; ?></td><?php foreach ($record as $value): ?><td><?php echo reportsEscape($value); ?></td><?php endforeach; ?></tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
							<footer class="report-results-footer">
								<p><?php if ($reportTotal > 0 && $pageError === ''): ?>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($reportRows), $reportTotal); ?> of <?php echo number_format($reportTotal); ?> entries<?php else: ?>Showing 0 entries<?php endif; ?></p>
								<?php if ($totalPages > 1 && $pageError === ''): ?>
									<nav class="report-pagination" aria-label="Personnel report pages">
										<a class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => max(1, $page - 1)])); ?>">Previous</a>
										<?php foreach ($visiblePages as $visiblePage): ?>
											<a class="<?php echo $visiblePage === $page ? 'active' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => $visiblePage])); ?>" <?php echo $visiblePage === $page ? 'aria-current="page"' : ''; ?>><?php echo $visiblePage; ?></a>
										<?php endforeach; ?>
										<a class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => min($totalPages, $page + 1)])); ?>">Next</a>
									</nav>
								<?php endif; ?>
							</footer>
						</section>
					<?php else: ?>
						<div class="generic-report-summary">
							<article><span>Total Records</span><strong><?php echo number_format($reportTotal); ?></strong><small><?php echo reportsEscape($periodLabel); ?></small></article>
						</div>
						<section class="report-breakdown-card generic-records-card">
							<div class="generic-records-heading">
								<h3><?php echo reportsEscape($reportTypes[$reportType]['name']); ?> Records</h3>
								<form class="report-search-form" method="get" action="">
									<input type="hidden" name="type" value="<?php echo reportsEscape($reportType); ?>">
									<input type="hidden" name="month" value="<?php echo $month; ?>">
									<input type="hidden" name="year" value="<?php echo $year; ?>">
									<input type="search" name="search" value="<?php echo reportsEscape($search); ?>" placeholder="Search..." aria-label="Search report records">
									<button type="submit">Search</button>
								</form>
							</div>
							<div class="report-table-scroll">
								<table class="report-detail-table">
									<thead><tr><?php foreach ($reportHeaders as $header): ?><th><?php echo reportsEscape($header); ?></th><?php endforeach; ?></tr></thead>
									<tbody>
										<?php if ($pageError !== ''): ?>
											<tr><td colspan="<?php echo count($reportHeaders); ?>" class="report-empty">Report records are unavailable.</td></tr>
										<?php elseif (!$reportRows): ?>
											<tr><td colspan="<?php echo count($reportHeaders); ?>" class="report-empty"><?php echo $search !== '' ? 'No records match your search.' : 'No records found for this month.'; ?></td></tr>
										<?php else: ?>
											<?php foreach ($reportRows as $index => $record): ?>
												<tr><td><?php echo $offset + $index + 1; ?></td><?php foreach ($record as $value): ?><td><?php echo reportsEscape($value); ?></td><?php endforeach; ?></tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
							<footer class="report-results-footer">
								<p><?php if ($reportTotal > 0 && $pageError === ''): ?>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($reportRows), $reportTotal); ?> of <?php echo number_format($reportTotal); ?> entries<?php else: ?>Showing 0 entries<?php endif; ?></p>
								<?php if ($totalPages > 1 && $pageError === ''): ?>
									<nav class="report-pagination" aria-label="Report pages">
										<a class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => max(1, $page - 1)])); ?>">Previous</a>
										<?php foreach ($visiblePages as $visiblePage): ?>
											<a class="<?php echo $visiblePage === $page ? 'active' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => $visiblePage])); ?>" <?php echo $visiblePage === $page ? 'aria-current="page"' : ''; ?>><?php echo $visiblePage; ?></a>
										<?php endforeach; ?>
										<a class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo reportsEscape(http_build_query($queryParameters + ['page' => min($totalPages, $page + 1)])); ?>">Next</a>
									</nav>
								<?php endif; ?>
							</footer>
						</section>
					<?php endif; ?>
				</section>
			</section>
		</main>
	</div>
	<script>
		(function () {
			const toggle = document.getElementById('reportExportToggle');
			const menu = document.getElementById('reportExportMenu');
			if (!toggle || !menu) return;
			toggle.addEventListener('click', function () {
				const open = this.getAttribute('aria-expanded') !== 'true';
				this.setAttribute('aria-expanded', String(open));
				menu.hidden = !open;
			});
			menu.querySelectorAll('[data-report-print], [data-report-pdf]').forEach(function (action) {
				action.addEventListener('click', function () {
					menu.hidden = true;
					toggle.setAttribute('aria-expanded', 'false');
					window.print();
				});
			});
			document.addEventListener('click', function (event) {
				if (!event.target.closest('.report-export-dropdown')) {
					menu.hidden = true;
					toggle.setAttribute('aria-expanded', 'false');
				}
			});
			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					menu.hidden = true;
					toggle.setAttribute('aria-expanded', 'false');
				}
			});
		}());
		document.querySelectorAll('.report-search-form input[type="search"]').forEach(function (searchInput) {
			let searchTimeout;
			searchInput.addEventListener('input', function () {
				window.clearTimeout(searchTimeout);
				searchTimeout = window.setTimeout(function () {
					searchInput.form.requestSubmit();
				}, 300);
			});
		});
	</script>
</body>
</html>
