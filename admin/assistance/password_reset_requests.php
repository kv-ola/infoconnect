<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$page_title = 'Password Reset';
$pageSize = 10;
$pageError = '';
$requests = [];
$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (!in_array($status, ['', 'pending', 'approved', 'rejected'], true)) {
	$status = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;

function passwordResetRequestEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$fromSql = "FROM approval_requests ar
	LEFT JOIN users requester ON requester.id = ar.requested_by
	LEFT JOIN staff_profiles sp ON sp.user_id = requester.id
	LEFT JOIN admin_profiles ap ON ap.user_id = requester.id
	LEFT JOIN senior_profiles seniors ON seniors.user_id = requester.id
	LEFT JOIN users reviewer ON reviewer.id = ar.reviewed_by";
$searchSql = "WHERE ar.request_type = 'password_reset'
	AND (? = '' OR ar.status = ?)
	AND (? = '' OR CONCAT_WS(' ', ar.id, requester.account_id, requester.role, sp.employee_id, sp.first_name, sp.last_name, ap.employee_id, ap.first_name, ap.last_name, seniors.senior_citizen_id, seniors.first_name, seniors.last_name, ar.remarks) LIKE CONCAT('%', ?, '%'))";

try {
	$countStatement = $databaseConnection->prepare('SELECT COUNT(*) AS total ' . $fromSql . ' ' . $searchSql);
	$countStatement->bind_param('ssss', $status, $status, $search, $search);
	$countStatement->execute();
	$filteredTotal = (int) $countStatement->get_result()->fetch_assoc()['total'];
	$countStatement->close();

	$totalPages = max(1, (int) ceil($filteredTotal / $pageSize));
	$page = min($page, $totalPages);
	$offset = ($page - 1) * $pageSize;
	$requestStatement = $databaseConnection->prepare(
		"SELECT ar.id, ar.status, ar.created_at,
		        reviewer.account_id AS reviewer_account
		 {$fromSql}
		 {$searchSql}
		 ORDER BY ar.created_at DESC, ar.id DESC
		 LIMIT {$pageSize} OFFSET {$offset}"
	);
	$requestStatement->bind_param('ssss', $status, $status, $search, $search);
	$requestStatement->execute();
	$requestResult = $requestStatement->get_result();
	while ($row = $requestResult->fetch_assoc()) {
		$requests[] = $row;
	}
	$requestStatement->close();
} catch (mysqli_sql_exception $exception) {
	error_log('Unable to load password reset requests: ' . $exception->getMessage());
	$pageError = 'Password reset requests could not be loaded. Please try again later.';
	$filteredTotal = 0;
	$totalPages = 1;
	$offset = 0;
}

$queryParameters = array_filter(['search' => $search, 'status' => $status], static function ($value): bool {
	return $value !== '';
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo passwordResetRequestEscape($page_title); ?></title>
	<link rel="stylesheet" href="../home.css">
	<link rel="stylesheet" href="assets/password_reset_requests.css?v=3">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>
			<section class="admin-content password-reset-requests-page">
				<header class="password-reset-heading">
					<div>
						<h1><?php echo passwordResetRequestEscape($page_title); ?></h1>
						<p>Track account recovery requests started through Forgot Password.</p>
					</div>
				</header>

				<?php if ($pageError !== ''): ?>
					<div class="password-reset-error" role="alert"><?php echo passwordResetRequestEscape($pageError); ?></div>
				<?php endif; ?>

				<section class="page-card password-reset-card" aria-label="Password reset request records">
					<form class="password-reset-filters" method="get" action="">
						<label class="password-reset-search">
							<span class="visually-hidden">Search password reset requests</span>
							<input type="search" name="search" value="<?php echo passwordResetRequestEscape($search); ?>" placeholder="Search by request ID, account, name, or remarks...">
							<button type="submit">Search</button>
						</label>
						<label class="password-reset-status">
							<span>Status</span>
							<select name="status" onchange="this.form.requestSubmit()">
								<option value="">All Statuses</option>
								<?php foreach (['pending' => 'Pending', 'approved' => 'Completed', 'rejected' => 'Rejected'] as $option => $label): ?>
									<option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo $label; ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<a class="password-reset-clear" href="password_reset_requests.php">Reset Filters</a>
					</form>
					<div class="password-reset-table-wrap">
						<table class="password-reset-table">
							<thead>
								<tr>
									<th>#</th>
									<th>Request ID</th>
									<th>Date Submitted</th>
									<th>Status</th>
									<th>Reviewed By</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<?php if ($pageError !== ''): ?>
									<tr><td colspan="6" class="password-reset-empty">Requests are unavailable.</td></tr>
								<?php elseif (!$requests): ?>
									<tr><td colspan="6" class="password-reset-empty"><?php echo $search !== '' || $status !== '' ? 'No password reset requests match these filters.' : 'No password reset requests have been recorded.'; ?></td></tr>
								<?php else: ?>
									<?php foreach ($requests as $index => $request): ?>
										<tr>
											<td><?php echo $offset + $index + 1; ?></td>
											<td>REQ-<?php echo str_pad((string) $request['id'], 3, '0', STR_PAD_LEFT); ?></td>
											<td><?php echo passwordResetRequestEscape(date('M j, Y h:i A', strtotime($request['created_at']))); ?></td>
											<td><span class="password-reset-badge <?php echo passwordResetRequestEscape($request['status']); ?>"><?php echo passwordResetRequestEscape(ucfirst($request['status'])); ?></span></td>
											<td><?php echo passwordResetRequestEscape($request['reviewer_account'] ?: ($request['status'] === 'approved' ? 'Self-service' : '—')); ?></td>
											<td><a class="password-reset-action" href="/BMirk/admin/requests/requests_review.php?id=<?php echo (int) $request['id']; ?>&amp;from=password_reset">View</a></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<footer class="password-reset-footer">
						<p>Showing <?php echo $filteredTotal > 0 ? $offset + 1 . ' to ' . min($offset + count($requests), $filteredTotal) . ' of ' . number_format($filteredTotal) : '0'; ?> requests</p>
						<?php if ($totalPages > 1 && $pageError === ''): ?>
							<nav class="password-reset-pagination" aria-label="Password reset request pages">
								<?php for ($number = 1; $number <= $totalPages; $number++): ?>
									<a class="<?php echo $number === $page ? 'active' : ''; ?>" href="?<?php echo passwordResetRequestEscape(http_build_query($queryParameters + ['page' => $number])); ?>"<?php echo $number === $page ? ' aria-current="page"' : ''; ?>><?php echo $number; ?></a>
								<?php endfor; ?>
							</nav>
						<?php endif; ?>
					</footer>
				</section>
			</section>
		</main>
	</div>
</body>
</html>
