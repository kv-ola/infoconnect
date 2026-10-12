<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$pageTitle = 'Assistance History';
$pageSize = 10;
$pageError = '';
$entries = [];
$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$typeFilter = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (!in_array($typeFilter, ['', 'support', 'password_reset'], true)) {
	$typeFilter = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;

function assistanceHistoryEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

try {
	$supportResult = $databaseConnection->query(
		"SELECT sr.id, sr.subject, sr.concern_type, sr.message, sr.created_at, sr.handled_at,
		        requester.account_id, requester.role,
		        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', sp.first_name, sp.middle_name, sp.last_name, sp.suffix)), ''), requester.account_id, 'Unknown') AS requester_name,
		        handler.account_id AS handled_by
		 FROM support_requests sr
		 LEFT JOIN users requester ON requester.id = sr.requested_by
		 LEFT JOIN staff_profiles sp ON sp.user_id = requester.id
		 LEFT JOIN users handler ON handler.id = sr.handled_by
		 WHERE sr.status = 'resolved'"
	);
	while ($row = $supportResult->fetch_assoc()) {
		$entries[] = [
			'type' => 'support',
			'type_label' => 'Support Request',
			'request_id' => 'SUP-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT),
			'requester' => $row['requester_name'],
			'account_id' => $row['account_id'] ?? '',
			'details' => trim($row['subject'] . ' — ' . $row['concern_type'] . ': ' . $row['message']),
			'submitted_at' => $row['created_at'],
			'closed_at' => $row['handled_at'],
			'status' => 'Resolved',
			'handled_by' => $row['handled_by'] ?? '',
		];
	}

	$passwordResult = $databaseConnection->query(
		"SELECT ar.id, ar.status, ar.created_at, ar.reviewed_at, ar.remarks,
		        requester.account_id, requester.role,
		        COALESCE(
		            NULLIF(TRIM(CONCAT_WS(' ', sp.first_name, sp.middle_name, sp.last_name, sp.suffix)), ''),
		            NULLIF(TRIM(CONCAT_WS(' ', ap.first_name, ap.middle_name, ap.last_name, ap.suffix)), ''),
		            NULLIF(TRIM(CONCAT_WS(' ', seniors.first_name, seniors.middle_name, seniors.last_name, seniors.suffix)), ''),
		            requester.account_id,
		            'Unknown'
		        ) AS requester_name,
		        reviewer.account_id AS handled_by
		 FROM approval_requests ar
		 LEFT JOIN users requester ON requester.id = ar.requested_by
		 LEFT JOIN staff_profiles sp ON sp.user_id = requester.id
		 LEFT JOIN admin_profiles ap ON ap.user_id = requester.id
		 LEFT JOIN senior_profiles seniors ON seniors.user_id = requester.id
		 LEFT JOIN users reviewer ON reviewer.id = ar.reviewed_by
		 WHERE ar.request_type = 'password_reset'
		   AND ar.status IN ('approved', 'rejected')"
	);
	while ($row = $passwordResult->fetch_assoc()) {
		$entries[] = [
			'type' => 'password_reset',
			'type_label' => 'Password Reset',
			'request_id' => 'REQ-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT),
			'requester' => $row['requester_name'],
			'account_id' => $row['account_id'] ?? '',
			'details' => $row['remarks'] ?: 'Password reset request processed.',
			'submitted_at' => $row['created_at'],
			'closed_at' => $row['reviewed_at'],
			'status' => $row['status'] === 'approved' ? 'Completed' : 'Rejected',
			'handled_by' => $row['handled_by'] ?? '',
		];
	}

	$entries = array_values(array_filter($entries, static function (array $entry) use ($typeFilter, $search): bool {
		if ($typeFilter !== '' && $entry['type'] !== $typeFilter) {
			return false;
		}
		return $search === '' || stripos(implode(' ', [
			$entry['request_id'],
			$entry['type_label'],
			$entry['requester'],
			$entry['account_id'],
			$entry['details'],
			$entry['status'],
			$entry['handled_by'],
		]), $search) !== false;
	}));
	usort($entries, static function (array $left, array $right): int {
		return strcmp((string) $right['closed_at'], (string) $left['closed_at']);
	});
} catch (Throwable $exception) {
	error_log('Unable to load assistance history: ' . $exception->getMessage());
	$pageError = 'Assistance history could not be loaded. Please try again later.';
}

$totalEntries = count($entries);
$totalPages = max(1, (int) ceil($totalEntries / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$entries = array_slice($entries, $offset, $pageSize);
$queryParameters = array_filter(['search' => $search, 'type' => $typeFilter], static fn ($value): bool => $value !== '');
$paginationPages = array_values(array_unique(array_filter(
	[1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $totalPages],
	static fn ($number): bool => $number >= 1 && $number <= $totalPages
)));
sort($paginationPages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo assistanceHistoryEscape($pageTitle); ?></title>
	<link rel="stylesheet" href="../home.css">
	<link rel="stylesheet" href="assets/assistance_history.css?v=2">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>
			<section class="admin-content assistance-history-page">
				<header class="assistance-history-heading">
					<h1><?php echo assistanceHistoryEscape($pageTitle); ?></h1>
					<p>Review resolved staff support requests and completed or rejected password reset requests.</p>
				</header>
				<?php if ($pageError !== ''): ?><div class="assistance-history-error" role="alert"><?php echo assistanceHistoryEscape($pageError); ?></div><?php endif; ?>
				<section class="page-card assistance-history-card" aria-label="Assistance request history">
					<form class="assistance-history-filters" method="get" action="">
						<label class="assistance-history-search">
							<span class="visually-hidden">Search assistance history</span>
							<input type="search" name="search" value="<?php echo assistanceHistoryEscape($search); ?>" placeholder="Search request, account, requester, or details...">
							<button type="submit">Search</button>
						</label>
						<label class="assistance-history-type">
							<span>Request Type</span>
							<select name="type" onchange="this.form.requestSubmit()">
								<option value="">All Assistance</option>
								<option value="support" <?php echo $typeFilter === 'support' ? 'selected' : ''; ?>>Help &amp; Support</option>
								<option value="password_reset" <?php echo $typeFilter === 'password_reset' ? 'selected' : ''; ?>>Password Reset</option>
							</select>
						</label>
						<a class="assistance-history-reset" href="assistance_history.php">Reset Filters</a>
					</form>
					<div class="assistance-history-table-wrap">
						<table class="assistance-history-table">
							<thead><tr><th>#</th><th>Type</th><th>Request ID</th><th>Requested By</th><th>Details</th><th>Submitted</th><th>Closed / Completed</th><th>Status</th><th>Handled By</th></tr></thead>
							<tbody>
								<?php if ($pageError !== ''): ?>
									<tr><td colspan="9" class="assistance-history-empty">History records are unavailable.</td></tr>
								<?php elseif (!$entries): ?>
									<tr><td colspan="9" class="assistance-history-empty"><?php echo $search !== '' || $typeFilter !== '' ? 'No assistance history matches these filters.' : 'No closed assistance requests have been recorded.'; ?></td></tr>
								<?php else: foreach ($entries as $index => $entry): ?>
									<tr>
										<td><?php echo $offset + $index + 1; ?></td>
										<td><?php echo assistanceHistoryEscape($entry['type_label']); ?></td>
										<td><?php echo assistanceHistoryEscape($entry['request_id']); ?></td>
										<td><?php echo assistanceHistoryEscape($entry['requester']); ?><small><?php echo assistanceHistoryEscape($entry['account_id']); ?></small></td>
										<td class="assistance-history-details"><?php echo nl2br(assistanceHistoryEscape($entry['details'])); ?></td>
										<td><?php echo assistanceHistoryEscape(date('M j, Y h:i A', strtotime($entry['submitted_at']))); ?></td>
										<td><?php echo $entry['closed_at'] ? assistanceHistoryEscape(date('M j, Y h:i A', strtotime($entry['closed_at']))) : '—'; ?></td>
										<td><span class="assistance-history-badge <?php echo assistanceHistoryEscape(strtolower($entry['status'])); ?>"><?php echo assistanceHistoryEscape($entry['status']); ?></span></td>
										<td><?php echo assistanceHistoryEscape($entry['handled_by'] ?: '—'); ?></td>
									</tr>
								<?php endforeach; endif; ?>
							</tbody>
						</table>
					</div>
					<footer class="assistance-history-footer">
						<p><?php if ($totalEntries > 0 && $pageError === ''): ?>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($entries), $totalEntries); ?> of <?php echo number_format($totalEntries); ?> records<?php else: ?>Showing 0 records<?php endif; ?></p>
						<?php if ($totalPages > 1 && $pageError === ''): ?>
							<nav class="assistance-history-pagination" aria-label="Assistance history pages">
								<a class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo assistanceHistoryEscape(http_build_query($queryParameters + ['page' => max(1, $page - 1)])); ?>">Previous</a>
								<?php foreach ($paginationPages as $number): ?><a class="<?php echo $number === $page ? 'active' : ''; ?>" href="?<?php echo assistanceHistoryEscape(http_build_query($queryParameters + ['page' => $number])); ?>"<?php echo $number === $page ? ' aria-current="page"' : ''; ?>><?php echo $number; ?></a><?php endforeach; ?>
								<a class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo assistanceHistoryEscape(http_build_query($queryParameters + ['page' => min($totalPages, $page + 1)])); ?>">Next</a>
							</nav>
						<?php endif; ?>
					</footer>
				</section>
			</section>
		</main>
	</div>
</body>
</html>
