<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/activity_log.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$pageTitle = 'Help & Support';
$currentAdminId = (int) ($_SESSION['user_id'] ?? 0);
$pageSize = 10;
$pageError = '';
$flashMessage = $_SESSION['admin_support_flash'] ?? '';
unset($_SESSION['admin_support_flash']);
$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (!in_array($statusFilter, ['', 'open', 'in_progress', 'resolved'], true)) {
	$statusFilter = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;

if (empty($_SESSION['admin_support_csrf_token'])) {
	$_SESSION['admin_support_csrf_token'] = bin2hex(random_bytes(32));
}

function adminSupportEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$postedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
	$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
	$newStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
	if (!hash_equals($_SESSION['admin_support_csrf_token'], $postedToken)) {
		$pageError = 'This form has expired. Refresh the page and try again.';
	} elseif ($requestId === false || $requestId < 1 || !in_array($newStatus, ['open', 'in_progress', 'resolved'], true)) {
		$pageError = 'Choose a valid support request and status.';
	} else {
		$statusTransactionStarted = false;
		try {
			$databaseConnection->begin_transaction();
			$statusTransactionStarted = true;
			if ($newStatus === 'open') {
				$updateStatement = $databaseConnection->prepare(
					"UPDATE support_requests SET status = 'open', handled_by = NULL, handled_at = NULL WHERE id = ?"
				);
				$updateStatement->bind_param('i', $requestId);
			} else {
				$updateStatement = $databaseConnection->prepare(
					'UPDATE support_requests SET status = ?, handled_by = ?, handled_at = NOW() WHERE id = ?'
				);
				$updateStatement->bind_param('sii', $newStatus, $currentAdminId, $requestId);
			}
			$updateStatement->execute();
			$updateStatement->close();
			$existsStatement = $databaseConnection->prepare('SELECT id FROM support_requests WHERE id = ? LIMIT 1');
			$existsStatement->bind_param('i', $requestId);
			$existsStatement->execute();
			$requestExists = $existsStatement->get_result()->num_rows === 1;
			$existsStatement->close();
			if (!$requestExists) {
				throw new RuntimeException('The support request no longer exists.');
			}
			writeActivityLog($databaseConnection, $currentAdminId, 'update', 'Support Requests', 'Updated support request #' . $requestId . ' to ' . str_replace('_', ' ', $newStatus) . '.', $requestId);
			$databaseConnection->commit();
			$statusTransactionStarted = false;
			$_SESSION['admin_support_flash'] = 'Support request status updated.';
			$_SESSION['admin_support_csrf_token'] = bin2hex(random_bytes(32));
			header('Location: /BMirk/admin/assistance/support_requests.php', true, 303);
			exit;
		} catch (Throwable $exception) {
			if ($statusTransactionStarted) {
				try {
					$databaseConnection->rollback();
				} catch (Throwable $rollbackException) {
					error_log('Unable to roll back support request update: ' . $rollbackException->getMessage());
				}
				$statusTransactionStarted = false;
			}
			error_log('Unable to update support request: ' . $exception->getMessage());
			$pageError = 'The support request status could not be updated.';
		}
	}
}

$fromSql = "FROM support_requests sr
	LEFT JOIN users requester ON requester.id = sr.requested_by
	LEFT JOIN staff_profiles sp ON sp.user_id = requester.id";
$whereSql = "WHERE (? = '' OR sr.status = ?)
	AND (? = '' OR CONCAT_WS(' ', sr.id, sr.subject, sr.concern_type, sr.message, requester.account_id, sp.employee_id, sp.first_name, sp.last_name) LIKE CONCAT('%', ?, '%'))";
$requests = [];
$filteredTotal = 0;
$totalPages = 1;
$offset = 0;
try {
	$countStatement = $databaseConnection->prepare('SELECT COUNT(*) AS total ' . $fromSql . ' ' . $whereSql);
	$countStatement->bind_param('ssss', $statusFilter, $statusFilter, $search, $search);
	$countStatement->execute();
	$filteredTotal = (int) $countStatement->get_result()->fetch_assoc()['total'];
	$countStatement->close();

	$totalPages = max(1, (int) ceil($filteredTotal / $pageSize));
	$page = min($page, $totalPages);
	$offset = ($page - 1) * $pageSize;
	$listStatement = $databaseConnection->prepare(
		"SELECT sr.id, sr.subject, sr.concern_type, sr.message, sr.status, sr.created_at,
		        requester.account_id, requester.role,
		        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', sp.first_name, sp.middle_name, sp.last_name, sp.suffix)), ''), requester.account_id, 'Unknown') AS requester_name
		 {$fromSql}
		 {$whereSql}
		 ORDER BY FIELD(sr.status, 'open', 'in_progress', 'resolved'), sr.created_at DESC, sr.id DESC
		 LIMIT {$pageSize} OFFSET {$offset}"
	);
	$listStatement->bind_param('ssss', $statusFilter, $statusFilter, $search, $search);
	$listStatement->execute();
	$listResult = $listStatement->get_result();
	while ($row = $listResult->fetch_assoc()) {
		$requests[] = $row;
	}
	$listStatement->close();
} catch (Throwable $exception) {
	error_log('Unable to load support requests: ' . $exception->getMessage());
	$pageError = 'Support requests could not be loaded. Please try again later.';
	$filteredTotal = 0;
	$totalPages = 1;
	$offset = 0;
}

$queryParameters = array_filter(['search' => $search, 'status' => $statusFilter], static function ($value): bool {
	return $value !== '';
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo adminSupportEscape($pageTitle); ?></title>
	<link rel="stylesheet" href="../home.css">
	<link rel="stylesheet" href="assets/support_requests.css?v=2">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>
			<section class="admin-content support-requests-page">
				<header class="support-requests-heading">
					<h1><?php echo adminSupportEscape($pageTitle); ?></h1>
					<p>Review and track help requests sent by staff through Help &amp; Support.</p>
				</header>
				<?php if ($flashMessage !== ''): ?><div class="support-requests-alert success" role="status"><?php echo adminSupportEscape($flashMessage); ?></div><?php endif; ?>
				<?php if ($pageError !== ''): ?><div class="support-requests-alert error" role="alert"><?php echo adminSupportEscape($pageError); ?></div><?php endif; ?>

				<section class="page-card support-requests-card" aria-label="Staff support requests">
					<h2>Help &amp; Support</h2>
					<form class="support-requests-filters" method="get">
						<label class="support-requests-search"><span class="visually-hidden">Search support requests</span><input type="search" name="search" value="<?php echo adminSupportEscape($search); ?>" placeholder="Search by subject, staff member, concern, or message..."><button type="submit">Search</button></label>
						<label class="support-requests-status"><span>Status</span><select name="status" onchange="this.form.requestSubmit()"><option value="">All Statuses</option><?php foreach (['open', 'in_progress', 'resolved'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $statusFilter === $option ? 'selected' : ''; ?>><?php echo adminSupportEscape(ucwords(str_replace('_', ' ', $option))); ?></option><?php endforeach; ?></select></label>
						<a class="support-requests-reset" href="support_requests.php">Reset Filters</a>
					</form>
					<div class="support-requests-table-wrap">
						<table class="support-requests-table">
							<thead><tr><th>#</th><th>Staff Member</th><th>Subject</th><th>Concern Type</th><th>Message</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
							<tbody>
								<?php if ($pageError !== ''): ?>
									<tr><td colspan="8" class="support-requests-empty">Support requests are unavailable.</td></tr>
								<?php elseif (!$requests): ?>
									<tr><td colspan="8" class="support-requests-empty"><?php echo $search !== '' || $statusFilter !== '' ? 'No support requests match these filters.' : 'No staff support requests have been submitted.'; ?></td></tr>
								<?php else: ?>
									<?php foreach ($requests as $index => $request): ?>
										<tr>
											<td><?php echo $offset + $index + 1; ?></td>
											<td><?php echo adminSupportEscape($request['requester_name']); ?><small><?php echo adminSupportEscape($request['account_id'] ?: ''); ?></small></td>
											<td><?php echo adminSupportEscape($request['subject']); ?></td>
											<td><?php echo adminSupportEscape($request['concern_type']); ?></td>
											<td class="support-request-message"><?php echo nl2br(adminSupportEscape($request['message'])); ?></td>
											<td><?php echo adminSupportEscape(date('M j, Y h:i A', strtotime($request['created_at']))); ?></td>
											<td><span class="support-request-badge <?php echo adminSupportEscape($request['status']); ?>"><?php echo adminSupportEscape(ucwords(str_replace('_', ' ', $request['status']))); ?></span></td>
											<td>
												<form class="support-request-update" method="post" aria-label="Update support request <?php echo (int) $request['id']; ?>">
													<input type="hidden" name="csrf_token" value="<?php echo adminSupportEscape($_SESSION['admin_support_csrf_token']); ?>">
													<input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
													<select name="status" aria-label="Update status for support request <?php echo (int) $request['id']; ?>"><?php foreach (['open', 'in_progress', 'resolved'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $request['status'] === $option ? 'selected' : ''; ?>><?php echo adminSupportEscape(ucwords(str_replace('_', ' ', $option))); ?></option><?php endforeach; ?></select>
													<button type="submit">Update</button>
												</form>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<footer class="support-requests-footer">
						<p>Showing <?php echo $filteredTotal > 0 ? $offset + 1 . ' to ' . min($offset + count($requests), $filteredTotal) . ' of ' . number_format($filteredTotal) : '0'; ?> requests</p>
						<?php if ($totalPages > 1): ?><nav aria-label="Support request pages"><?php for ($number = 1; $number <= $totalPages; $number++): ?><a class="<?php echo $number === $page ? 'active' : ''; ?>" href="?<?php echo adminSupportEscape(http_build_query($queryParameters + ['page' => $number])); ?>"><?php echo $number; ?></a><?php endfor; ?></nav><?php endif; ?>
					</footer>
				</section>
			</section>
		</main>
	</div>
</body>
</html>
