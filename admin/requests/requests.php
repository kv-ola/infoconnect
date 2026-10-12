<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$requestQueue = $requestQueue ?? 'posts';
$isSeniorQueue = $requestQueue === 'senior';
$requestTypeCondition = $isSeniorQueue
	? "ar.request_type = 'senior'"
	: "ar.request_type IN ('event', 'announcement')";
$page_title = $isSeniorQueue ? 'Pending Senior' : 'Pending Posts';
$approvalItems = [];
$requestsError = '';
$requestCounts = [
	'total' => 0,
	'senior_new' => 0,
	'senior_imported' => 0,
	'event' => 0,
	'announcement' => 0,
];

$result = $databaseConnection->query(
	"SELECT ar.id, ar.request_type, ar.reference_id, ar.created_at, ar.status,
	        CASE WHEN ar.request_type = 'senior'
	                  AND EXISTS (SELECT 1 FROM import_records ir WHERE ir.senior_id = ar.reference_id)
	             THEN 'Imported'
	             WHEN ar.request_type = 'senior' THEN 'New'
	             ELSE ''
	        END AS request_source,
	        COALESCE(
	            NULLIF(events.title, ''),
	            NULLIF(announcements.title, ''),
	            NULLIF(TRIM(CONCAT_WS(' ', seniors.first_name, seniors.middle_name, seniors.last_name, seniors.suffix)), ''),
	            CASE WHEN ar.request_type = 'password_reset' THEN CONCAT('Password reset for ', reset_account.account_id) END,
	            CONCAT('Request #', ar.reference_id)
	        ) AS request_title,
	        COALESCE(NULLIF(staff.employee_id, ''), NULLIF(admin.employee_id, ''), requester.account_id, 'Unknown') AS submitted_by
	 FROM approval_requests ar
	 LEFT JOIN events ON ar.request_type = 'event' AND events.id = ar.reference_id
	 LEFT JOIN announcements ON ar.request_type = 'announcement' AND announcements.id = ar.reference_id
	 LEFT JOIN senior_profiles seniors ON ar.request_type = 'senior' AND seniors.id = ar.reference_id
	 LEFT JOIN users reset_account ON ar.request_type = 'password_reset' AND reset_account.id = ar.reference_id
	 LEFT JOIN users requester ON requester.id = ar.requested_by
	 LEFT JOIN staff_profiles staff ON staff.user_id = requester.id
	 LEFT JOIN admin_profiles admin ON admin.user_id = requester.id
	 WHERE ar.status = 'pending' AND $requestTypeCondition
	 ORDER BY ar.created_at DESC, ar.id DESC"
);

if (!$result) {
	error_log('Unable to load ' . strtolower($page_title) . ': ' . $databaseConnection->error);
	$requestsError = 'Unable to load ' . strtolower($page_title) . '. Please try again later.';
} else {
	while ($row = $result->fetch_assoc()) {
		$requestType = str_replace('_', ' ', $row['request_type'] ?? 'Request');
		$createdAt = $row['created_at'] ?? null;
		$approvalItems[] = [
			'id' => (int) $row['id'],
			'type' => ucwords($requestType),
			'source' => $row['request_source'] ?? '',
			'reference_id' => (int) $row['reference_id'],
			'title' => $row['request_title'] ?: 'Untitled request',
			'submitted_by' => $row['submitted_by'] ?: 'Unknown',
			'created_at' => $createdAt ? date('M j, Y h:i A', strtotime($createdAt)) : 'N/A',
			'created_date' => $createdAt ? date('Y-m-d', strtotime($createdAt)) : '',
			'status' => ucfirst($row['status'] ?? 'pending'),
			'raw_status' => strtolower($row['status'] ?? 'pending'),
		];
		$requestCounts['total']++;
		if ($row['request_type'] === 'senior') {
			$requestCounts[$row['request_source'] === 'Imported' ? 'senior_imported' : 'senior_new']++;
		} elseif ($row['request_type'] === 'event') {
			$requestCounts['event']++;
		} elseif ($row['request_type'] === 'announcement') {
			$requestCounts['announcement']++;
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="stylesheet" href="/BMirk/admin/home.css">
	<link rel="stylesheet" href="/BMirk/admin/assets/table.css?v=3">
	<link rel="stylesheet" href="/BMirk/admin/requests/assets/requests_form.css?v=6">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>
			<section class="admin-content pending-requests-page <?php echo $isSeniorQueue ? 'senior-requests-queue' : 'post-requests-queue'; ?>">
				<header class="requests-heading">
					<h1><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
					<p><?php echo $isSeniorQueue
						? 'Review newly added and imported senior profiles awaiting approval.'
						: 'Review and manage pending event and announcement posts submitted by staff.'; ?></p>
				</header>

				<?php if ($requestsError !== ''): ?>
					<div class="requests-error" role="alert"><?php echo htmlspecialchars($requestsError, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<?php if ($isSeniorQueue): ?>
					<div class="requests-summary-grid" aria-label="Pending senior requests summary">
						<article class="request-summary-card summary-total">
							<span class="request-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2.5h9l5 5v14H6z"/><path d="M14 2.5v5h6M9 12h8m-8 3.5h8m-8 3.5h5"/></svg></span>
							<span class="request-summary-copy"><span>Pending for Approval</span><strong><?php echo number_format($requestCounts['total']); ?></strong><small>Awaiting review</small></span>
						</article>
						<article class="request-summary-card summary-active">
							<span class="request-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span>
							<span class="request-summary-copy"><span>New Entries</span><strong><?php echo number_format($requestCounts['senior_new']); ?></strong><small>Manually submitted profiles</small></span>
						</article>
						<article class="request-summary-card summary-pending">
							<span class="request-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M8 8h8m-8 4h8m-8 4h5"/><path d="M2 7V2h5m15 15v5h-5"/></svg></span>
							<span class="request-summary-copy"><span>Imported Entries</span><strong><?php echo number_format($requestCounts['senior_imported']); ?></strong><small>Profiles from imported lists</small></span>
						</article>
					</div>
				<?php endif; ?>

				<div class="page-card pending-requests-card">
					<div class="requests-filter-grid">
						<div class="search-group requests-search">
							<input id="approvalSearch" type="search" placeholder="<?php echo $isSeniorQueue ? 'Search by senior name, request ID, or submitted by...' : 'Search by post title, request ID, or submitted by...'; ?>" aria-label="Search <?php echo htmlspecialchars(strtolower($page_title), ENT_QUOTES, 'UTF-8'); ?>">
							<button type="button" id="approvalSearchButton">Search</button>
						</div>
						<?php if (!$isSeniorQueue): ?>
							<label class="filter-field">
								<span>Type</span>
								<select id="typeFilter">
									<option value="All">All</option>
									<option value="Event">Event</option>
									<option value="Announcement">Announcement</option>
								</select>
							</label>
						<?php else: ?>
							<label class="filter-field">
								<span>Source</span>
								<select id="typeFilter">
									<option value="All">All</option>
									<option value="New">New entries</option>
									<option value="Imported">Imported entries</option>
								</select>
							</label>
						<?php endif; ?>
						<label class="filter-field">
							<span>Date</span>
							<select id="dateFilter">
								<option value="All">All</option>
								<option value="Today">Today</option>
								<option value="Last 7 Days">Last 7 Days</option>
								<option value="This Month">This Month</option>
							</select>
						</label>
					</div>

					<div class="table-wrap">
						<table class="data-table requests-data-table" id="approvalsTable">
							<thead>
								<tr>
									<th>#</th>
									<th>Request ID</th>
									<?php if (!$isSeniorQueue): ?><th>Type</th><?php endif; ?>
									<th><?php echo $isSeniorQueue ? 'Senior Profile' : 'Post Title'; ?></th>
									<?php if ($isSeniorQueue): ?><th>Source</th><?php endif; ?>
									<th>Submitted By</th>
									<th>Date Submitted</th>
									<th>Status</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($approvalItems as $index => $item): ?>
									<tr data-search="<?php echo htmlspecialchars(strtolower('REQ-' . str_pad((string) $item['id'], 3, '0', STR_PAD_LEFT) . ' ' . $item['type'] . ' ' . $item['reference_id'] . ' ' . $item['title'] . ' ' . $item['submitted_by'] . ' ' . $item['status'] . ' ' . $item['source']), ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo htmlspecialchars($isSeniorQueue ? $item['source'] : $item['type'], ENT_QUOTES, 'UTF-8'); ?>" data-date="<?php echo htmlspecialchars($item['created_date'], ENT_QUOTES, 'UTF-8'); ?>">
										<td class="request-row-number"><?php echo $index + 1; ?></td>
										<td>REQ-<?php echo str_pad((string) $item['id'], 3, '0', STR_PAD_LEFT); ?></td>
										<?php if (!$isSeniorQueue): ?><td><?php echo htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8'); ?></td><?php endif; ?>
										<td><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></td>
										<?php if ($isSeniorQueue): ?><td><?php echo htmlspecialchars($item['source'], ENT_QUOTES, 'UTF-8'); ?></td><?php endif; ?>
										<td><?php echo htmlspecialchars($item['submitted_by'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars($item['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><span class="status-badge <?php echo htmlspecialchars($item['raw_status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
										<td><a class="section-action" href="/BMirk/admin/requests/requests_review.php?id=<?php echo (int) $item['id']; ?><?php echo $isSeniorQueue ? '&amp;from=senior' : ''; ?>">Review</a></td>
									</tr>
								<?php endforeach; ?>
								<tr class="requests-empty-row" id="requestsEmptyRow" <?php echo $approvalItems ? 'hidden' : ''; ?>>
									<td colspan="8"><?php echo $requestsError !== '' ? 'Requests could not be loaded.' : ($isSeniorQueue ? 'No pending senior requests found.' : 'No pending event or announcement posts found.'); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
					<footer class="requests-table-footer">
						<p class="requests-result-count" id="requestsResultCount" aria-live="polite"></p>
						<div class="pagination" id="requestsPagination" aria-label="Request result pages"></div>
					</footer>
				</div>
			</section>
		</main>
	</div>

	<script>
		const approvalSearchInput = document.getElementById('approvalSearch');
		const approvalSearchButton = document.getElementById('approvalSearchButton');
		const typeFilter = document.getElementById('typeFilter');
		const dateFilter = document.getElementById('dateFilter');
		const approvalTableBody = document.querySelector('#approvalsTable tbody');
		const requestsPagination = document.getElementById('requestsPagination');
		const requestsEmptyRow = document.getElementById('requestsEmptyRow');
		const requestsResultCount = document.getElementById('requestsResultCount');
		const pageSize = 10;
		let currentPage = 1;

		function renderRequestPagination(totalPages) {
			if (!requestsPagination) return;
			if (totalPages <= 1) {
				requestsPagination.replaceChildren();
				return;
			}

			const visiblePages = Array.from(new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]))
				.filter(function (page) { return page >= 1 && page <= totalPages; })
				.sort(function (a, b) { return a - b; });
			const controls = [
				'<button class="page-arrow" type="button" data-page="' + (currentPage - 1) + '" aria-label="Previous page"' + (currentPage === 1 ? ' disabled' : '') + '>&lsaquo;</button>'
			];
			let previousPage = 0;
			visiblePages.forEach(function (page) {
				if (page - previousPage > 1) controls.push('<span class="page-ellipsis" aria-hidden="true">...</span>');
				controls.push('<button class="page-btn' + (page === currentPage ? ' active' : '') + '" type="button" data-page="' + page + '" aria-current="' + (page === currentPage ? 'page' : 'false') + '">' + page + '</button>');
				previousPage = page;
			});
			controls.push(
				'<button class="page-arrow" type="button" data-page="' + (currentPage + 1) + '" aria-label="Next page"' + (currentPage === totalPages ? ' disabled' : '') + '>&rsaquo;</button>'
			);
			requestsPagination.innerHTML = controls.join('');

			requestsPagination.querySelectorAll('button[data-page]').forEach(function (button) {
				button.addEventListener('click', function () {
					const targetPage = Number(button.dataset.page);
					if (targetPage >= 1 && targetPage <= totalPages) {
						currentPage = targetPage;
						applyApprovalFilters();
					}
				});
			});
		}

		function applyApprovalFilters() {
			if (!approvalTableBody) return;

			const allRows = Array.from(approvalTableBody.querySelectorAll('tr[data-search]'));
			const term = (approvalSearchInput ? approvalSearchInput.value.trim().toLowerCase() : '');
			const selectedType = typeFilter ? typeFilter.value : 'All';
			const selectedDate = dateFilter ? dateFilter.value : 'All';
			const today = new Date();
			today.setHours(0, 0, 0, 0);
			const startOfWeek = new Date(today);
			startOfWeek.setDate(today.getDate() - 6);
			const filteredRows = allRows.filter(function (row) {
				const haystack = (row.dataset.search || '').toLowerCase();
				const matchesTerm = haystack.includes(term);
				const matchesType = selectedType === 'All' || (row.dataset.type || '').toLowerCase() === selectedType.toLowerCase();
				const rowDate = row.dataset.date ? new Date(row.dataset.date + 'T00:00:00') : null;
				const matchesDate = selectedDate === 'All'
					|| (rowDate && selectedDate === 'Today' && rowDate.getTime() === today.getTime())
					|| (rowDate && selectedDate === 'Last 7 Days' && rowDate >= startOfWeek && rowDate <= today)
					|| (rowDate && selectedDate === 'This Month' && rowDate.getFullYear() === today.getFullYear() && rowDate.getMonth() === today.getMonth());
				return matchesTerm && matchesType && matchesDate;
			});

			const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
			currentPage = Math.min(currentPage, totalPages);
			const startIndex = (currentPage - 1) * pageSize;
			const visibleRows = filteredRows.slice(startIndex, currentPage * pageSize);
			allRows.forEach(function (row) {
				row.hidden = !visibleRows.includes(row);
				if (!row.hidden) row.querySelector('.request-row-number').textContent = String(filteredRows.indexOf(row) + 1);
			});
			if (requestsEmptyRow) requestsEmptyRow.hidden = filteredRows.length > 0;
			requestsResultCount.textContent = filteredRows.length
				? 'Showing ' + (startIndex + 1) + ' to ' + (startIndex + visibleRows.length) + ' of ' + filteredRows.length + ' records'
				: 'No records found';
			renderRequestPagination(totalPages);
		}

		if (approvalSearchInput && approvalTableBody) {
			approvalSearchInput.addEventListener('input', function () {
				currentPage = 1;
				applyApprovalFilters();
			});
			if (approvalSearchButton) approvalSearchButton.addEventListener('click', function () {
				currentPage = 1;
				applyApprovalFilters();
			});
			if (typeFilter) typeFilter.addEventListener('change', function () {
				currentPage = 1;
				applyApprovalFilters();
			});
			if (dateFilter) dateFilter.addEventListener('change', function () {
				currentPage = 1;
				applyApprovalFilters();
			});
			applyApprovalFilters();
		}
	</script>
</body>
</html>
