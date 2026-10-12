<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/activity_log.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

activityLogClearDatabase($databaseConnection);

$page_title = 'Activity Logs';
$pageSize = 10;
$pageError = '';
$totalEntries = 0;
$activityLogs = [];
$userOptions = [];
$actionOptions = [];
$moduleOptions = [];

$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$userFilter = trim(is_string($_GET['user'] ?? null) ? $_GET['user'] : '');
$actionFilter = trim(is_string($_GET['action'] ?? null) ? $_GET['action'] : '');
$moduleFilter = trim(is_string($_GET['module'] ?? null) ? $_GET['module'] : '');
$dateFrom = trim(is_string($_GET['date_from'] ?? null) ? $_GET['date_from'] : '');
$dateTo = trim(is_string($_GET['date_to'] ?? null) ? $_GET['date_to'] : '');
$dateRange = is_string($_GET['date_range'] ?? null) ? $_GET['date_range'] : 'all';
if (!in_array($dateRange, ['all', 'today', 'week', 'month', 'custom'], true)) {
	$dateRange = 'all';
}
if ($dateRange !== 'custom') {
	$dateFrom = '';
	$dateTo = '';
	$today = new DateTimeImmutable('today');
	if ($dateRange === 'today') {
		$dateFrom = $today->format('Y-m-d');
		$dateTo = $today->format('Y-m-d');
	} elseif ($dateRange === 'week') {
		$dateFrom = $today->modify('monday this week')->format('Y-m-d');
		$dateTo = $today->format('Y-m-d');
	} elseif ($dateRange === 'month') {
		$dateFrom = $today->modify('first day of this month')->format('Y-m-d');
		$dateTo = $today->format('Y-m-d');
	}
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;

function activityLogEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function activityLogValidDate(string $value): bool
{
	$date = DateTime::createFromFormat('!Y-m-d', $value);
	return $date !== false && $date->format('Y-m-d') === $value;
}

if (($dateFrom !== '' && !activityLogValidDate($dateFrom)) || ($dateTo !== '' && !activityLogValidDate($dateTo))) {
	$pageError = 'Enter valid start and end dates.';
} elseif ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
	$pageError = 'The start date must not be later than the end date.';
}

if ($pageError === '') {
	try {
		$allLogs = activityLogReadFiles();
		$userOptionsById = [];
		$actionOptionsByValue = [];
		$moduleOptionsByValue = [];
		foreach ($allLogs as $log) {
			if ($log['account_id'] !== '') {
				$userOptionsById[$log['account_id']] = ['account_id' => $log['account_id'], 'role' => $log['role']];
			}
			if ($log['action'] !== '') {
				$actionOptionsByValue[$log['action']] = true;
			}
			if ($log['module'] !== '') {
				$moduleOptionsByValue[$log['module']] = true;
			}

			$logDate = substr($log['created_at'], 0, 10);
			if (($userFilter !== '' && $log['account_id'] !== $userFilter)
				|| ($actionFilter !== '' && $log['action'] !== $actionFilter)
				|| ($moduleFilter !== '' && $log['module'] !== $moduleFilter)
				|| ($dateFrom !== '' && $logDate < $dateFrom)
				|| ($dateTo !== '' && $logDate > $dateTo)) {
				continue;
			}
			$searchable = implode(' ', [$log['account_id'], $log['role'], $log['action'], $log['module'], $log['description']]);
			if ($search !== '' && stripos($searchable, $search) === false) {
				continue;
			}
			$activityLogs[] = $log;
		}
		$userOptions = array_values($userOptionsById);
		usort($userOptions, static fn (array $left, array $right): int => strcasecmp($left['account_id'], $right['account_id']));
		$actionOptions = array_keys($actionOptionsByValue);
		$moduleOptions = array_keys($moduleOptionsByValue);
		sort($actionOptions, SORT_NATURAL | SORT_FLAG_CASE);
		sort($moduleOptions, SORT_NATURAL | SORT_FLAG_CASE);
		$totalEntries = count($activityLogs);
	} catch (Throwable $exception) {
		error_log('Unable to load activity logs: ' . $exception->getMessage());
		$pageError = 'Activity logs could not be loaded. Please try again later.';
	}
}

$totalPages = max(1, (int) ceil($totalEntries / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$activityLogs = array_slice($activityLogs, $offset, $pageSize);
$queryParameters = [
	'search' => $search,
	'user' => $userFilter,
	'action' => $actionFilter,
	'module' => $moduleFilter,
	'date_range' => $dateRange,
	'date_from' => $dateFrom,
	'date_to' => $dateTo,
];
$queryParameters = array_filter($queryParameters, static function ($value): bool {
	return $value !== '';
});
$paginationPages = array_values(array_unique(array_filter(
	[1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $totalPages],
	static function ($number) use ($totalPages): bool {
		return $number >= 1 && $number <= $totalPages;
	}
)));
sort($paginationPages);

if (in_array($_GET['logs'] ?? '', ['view', 'download'], true) && $pageError === '') {
	try {
		$exportLogs = activityLogReadFiles($dateFrom, $dateTo);
	} catch (Throwable $exception) {
		error_log('Unable to export activity logs: ' . $exception->getMessage());
		http_response_code(500);
		exit('Activity logs could not be exported.');
	}
	$exportLogs = array_values(array_filter($exportLogs, static function (array $log) use ($userFilter, $actionFilter, $moduleFilter, $search): bool {
		if (($userFilter !== '' && $log['account_id'] !== $userFilter)
			|| ($actionFilter !== '' && $log['action'] !== $actionFilter)
			|| ($moduleFilter !== '' && $log['module'] !== $moduleFilter)) {
			return false;
		}
		return $search === '' || stripos(implode(' ', [$log['account_id'], $log['role'], $log['action'], $log['module'], $log['description']]), $search) !== false;
	}));
	$viewLogs = ($_GET['logs'] ?? '') === 'view';
	header('Content-Type: text/plain; charset=UTF-8');
	header('Content-Disposition: ' . ($viewLogs ? 'inline' : 'attachment') . '; filename="activity_logs.txt"');
	echo "Date & Time\tUser\tRole\tAction\tModule\tDetails\r\n";
	foreach ($exportLogs as $log) {
		$columns = [
			date('M j, Y h:i:s A', strtotime($log['created_at'])),
			$log['account_id'] ?: 'System',
			$log['role'] ? ucfirst($log['role']) : 'System',
			ucfirst($log['action']),
			ucfirst($log['module']),
			$log['description'] ?: '—',
		];
		$columns = array_map(static fn ($value): string => (string) preg_replace('/[\t\r\n]+/', ' ', (string) $value), $columns);
		echo implode("\t", $columns) . "\r\n";
	}
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo activityLogEscape($page_title); ?></title>
	<link rel="stylesheet" href="../home.css">
	<link rel="stylesheet" href="assets/activity_logs.css?v=8">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>
			<section class="admin-content activity-logs-page">
				<header class="activity-logs-heading">
					<div>
						<h1><?php echo activityLogEscape($page_title); ?></h1>
						<p>Review and filter system activity records stored in daily text files.</p>
					</div>
					<div class="activity-log-actions">
						<a class="activity-download-button" href="?<?php echo activityLogEscape(http_build_query($queryParameters + ['logs' => 'view'])); ?>" target="_blank" rel="noopener">View Logs</a>
						<a class="activity-download-button" href="?<?php echo activityLogEscape(http_build_query($queryParameters + ['logs' => 'download'])); ?>">Download Logs</a>
					</div>
				</header>

				<?php if ($pageError !== ''): ?>
					<div class="activity-logs-error" role="alert"><?php echo activityLogEscape($pageError); ?></div>
				<?php endif; ?>

				<section class="page-card activity-log-card" aria-label="Activity log records">
					<form class="activity-filter-grid" id="activityFilterForm" method="get" action="">
						<div class="activity-search">
							<input id="activitySearch" name="search" type="search" value="<?php echo activityLogEscape($search); ?>" placeholder="Search by user, action, module, or details..." aria-label="Search activity logs">
							<button type="submit">Search</button>
						</div>
						<label class="activity-filter" for="activityDateRange">
							<span>Date Range</span>
							<select id="activityDateRange" name="date_range">
								<option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
								<option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
								<option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>This Week</option>
								<option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>This Month</option>
								<option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom</option>
							</select>
						</label>
						<label class="activity-filter" for="activityUser">
							<span>User</span>
							<select id="activityUser" name="user">
								<option value="">All Users</option>
								<?php foreach ($userOptions as $option): ?>
									<option value="<?php echo activityLogEscape($option['account_id']); ?>" <?php echo $userFilter === $option['account_id'] ? 'selected' : ''; ?>><?php echo activityLogEscape($option['account_id']); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="activity-filter" for="activityAction">
							<span>Action</span>
							<select id="activityAction" name="action">
								<option value="">All Actions</option>
								<?php foreach ($actionOptions as $option): ?>
									<option value="<?php echo activityLogEscape($option); ?>" <?php echo $actionFilter === $option ? 'selected' : ''; ?>><?php echo activityLogEscape(ucfirst($option)); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="activity-filter" for="activityModule">
							<span>Module</span>
							<select id="activityModule" name="module">
								<option value="">All Modules</option>
								<?php foreach ($moduleOptions as $option): ?>
									<option value="<?php echo activityLogEscape($option); ?>" <?php echo $moduleFilter === $option ? 'selected' : ''; ?>><?php echo activityLogEscape(ucfirst($option)); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="activity-filter" for="activityDateFrom">
							<span>Date From</span>
							<input id="activityDateFrom" name="date_from" type="date" value="<?php echo activityLogEscape($dateFrom); ?>" <?php echo $dateRange !== 'custom' ? 'disabled' : ''; ?>>
						</label>
						<label class="activity-filter" for="activityDateTo">
							<span>Date To</span>
							<input id="activityDateTo" name="date_to" type="date" value="<?php echo activityLogEscape($dateTo); ?>" <?php echo $dateRange !== 'custom' ? 'disabled' : ''; ?>>
						</label>
						<input type="hidden" name="page" value="1">
						<a class="activity-reset-button" href="activity_logs.php">Reset Filters</a>
					</form>
					<div class="activity-table-wrap">
						<table class="activity-table">
							<thead>
								<tr>
									<th scope="col">#</th>
									<th scope="col">Date &amp; Time</th>
									<th scope="col">User</th>
									<th scope="col">Role</th>
									<th scope="col">Action</th>
									<th scope="col">Module</th>
									<th scope="col">Details</th>
								</tr>
							</thead>
							<tbody>
								<?php if ($pageError !== ''): ?>
									<tr><td colspan="7" class="activity-empty">Logs are unavailable.</td></tr>
								<?php elseif (!$activityLogs): ?>
									<tr><td colspan="7" class="activity-empty"><?php echo $search === '' && $userFilter === '' && $actionFilter === '' && $moduleFilter === '' && $dateFrom === '' && $dateTo === '' ? 'No activity logs found.' : 'No activity logs match these filters.'; ?></td></tr>
								<?php else: ?>
									<?php foreach ($activityLogs as $index => $log): ?>
										<tr>
											<td class="activity-row-number"><?php echo $offset + $index + 1; ?></td>
											<td><?php echo activityLogEscape(date('M j, Y h:i:s A', strtotime($log['created_at']))); ?></td>
											<td><?php echo activityLogEscape($log['account_id'] ?: 'System'); ?></td>
											<td><span class="activity-role-badge role-<?php echo activityLogEscape($log['role'] ?: 'system'); ?>"><?php echo activityLogEscape($log['role'] ? ucfirst($log['role']) : 'System'); ?></span></td>
											<td><span class="activity-action-text"><?php echo activityLogEscape(ucfirst($log['action'])); ?></span></td>
											<td><?php echo activityLogEscape(ucfirst($log['module'])); ?></td>
											<td class="activity-details"><?php echo activityLogEscape($log['description'] ?: '—'); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>

					<footer class="activity-table-footer">
						<p class="activity-result-count" aria-live="polite">
							<?php if ($totalEntries > 0 && $pageError === ''): ?>
								Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($activityLogs), $totalEntries); ?> of <?php echo number_format($totalEntries); ?> entries
							<?php else: ?>
								Showing 0 entries
							<?php endif; ?>
						</p>
						<?php if ($totalPages > 1 && $pageError === ''): ?>
							<nav class="activity-pagination" aria-label="Activity log pages">
								<a class="page-control <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo activityLogEscape(http_build_query($queryParameters + ['page' => max(1, $page - 1)])); ?>" <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Previous</a>
								<?php $previousPage = 0; ?>
								<?php foreach ($paginationPages as $paginationPage): ?>
									<?php if ($paginationPage - $previousPage > 1): ?><span class="page-ellipsis" aria-hidden="true">...</span><?php endif; ?>
									<a class="page-number <?php echo $paginationPage === $page ? 'active' : ''; ?>" href="?<?php echo activityLogEscape(http_build_query($queryParameters + ['page' => $paginationPage])); ?>" <?php echo $paginationPage === $page ? 'aria-current="page"' : ''; ?>><?php echo $paginationPage; ?></a>
									<?php $previousPage = $paginationPage; ?>
								<?php endforeach; ?>
								<a class="page-control <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo activityLogEscape(http_build_query($queryParameters + ['page' => min($totalPages, $page + 1)])); ?>" <?php echo $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Next</a>
							</nav>
						<?php endif; ?>
					</footer>
				</section>
			</section>
		</main>
	</div>
	<script>
		const activityFilterForm = document.getElementById('activityFilterForm');
		if (activityFilterForm) {
			const activitySearchInput = document.getElementById('activitySearch');
			const activityDateRange = document.getElementById('activityDateRange');
			const activityDateFrom = document.getElementById('activityDateFrom');
			const activityDateTo = document.getElementById('activityDateTo');
			let activitySearchTimeout;
			if (activitySearchInput) {
				activitySearchInput.addEventListener('input', function () {
					window.clearTimeout(activitySearchTimeout);
					activitySearchTimeout = window.setTimeout(function () {
						activityFilterForm.requestSubmit();
					}, 300);
				});
			}
			activityFilterForm.querySelectorAll('select, input[type="date"]').forEach(function (filter) {
				filter.addEventListener('change', function () {
					if (filter === activityDateRange) {
						const customRange = activityDateRange.value === 'custom';
						if (customRange) {
							activityDateFrom.value = '';
							activityDateTo.value = '';
						}
						activityDateFrom.disabled = !customRange;
						activityDateTo.disabled = !customRange;
					} else if (filter === activityDateFrom || filter === activityDateTo) {
						activityDateRange.value = 'custom';
					}
					activityFilterForm.requestSubmit();
				});
			});
		}
	</script>
</body>
</html>
