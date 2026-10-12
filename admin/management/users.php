<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/activity_log.php';
require_once __DIR__ . '/../../config/system_settings.php';
require_once __DIR__ . '/../../config/account_helpers.php';
require_once __DIR__ . '/../../config/personnel_notifications.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$page_title = 'Personnel Management';
$adminId = (int) ($_SESSION['user_id'] ?? 0);
$usersFlash = $_SESSION['users_flash'] ?? '';
unset($_SESSION['users_flash']);
$createdAccount = $_SESSION['created_user_account'] ?? null;
unset($_SESSION['created_user_account']);
$formError = '';
$showAddForm = false;
$formValues = [
	'last_name' => '',
	'first_name' => '',
	'middle_name' => '',
	'employee_id' => '',
	'email' => '',
	'contact_number' => '',
	'role' => 'staff',
];

if (empty($_SESSION['users_add_token'])) {
	$_SESSION['users_add_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['create_personnel'])) {
	$showAddForm = true;
	foreach ($formValues as $field => $default) {
		$postedValue = $_POST[$field] ?? $default;
		$formValues[$field] = is_string($postedValue) ? trim($postedValue) : $default;
	}

	$submittedToken = $_POST['csrf_token'] ?? '';
	if (!is_string($submittedToken) || !hash_equals($_SESSION['users_add_token'], $submittedToken)) {
		$formError = 'This form has expired. Refresh the page and try again.';
	} elseif ($formValues['last_name'] === '' || $formValues['first_name'] === '' || $formValues['employee_id'] === '' || $formValues['email'] === '' || $formValues['contact_number'] === '') {
		$formError = 'Last name, first name, employee ID, email, and contact number are required.';
	} elseif (!in_array($formValues['role'], ['admin', 'staff'], true)) {
		$formError = 'Select either Admin or Staff as the account role.';
	} elseif (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
		$formError = 'Enter a valid email address.';
	} elseif (!preg_match('/^\d{11}$/', $formValues['contact_number'])) {
		$formError = 'Contact number must contain exactly 11 digits.';
	} elseif (
		strlen($formValues['last_name']) > 100
		|| strlen($formValues['first_name']) > 100
		|| strlen($formValues['middle_name']) > 100
		|| strlen($formValues['employee_id']) > 50
		|| strlen($formValues['email']) > 150
		|| strlen($formValues['contact_number']) > 30
	) {
		$formError = 'One or more fields exceed the allowed length.';
	} else {
		$transactionStarted = false;
		try {
			foreach (['admin_profiles', 'staff_profiles'] as $profileTable) {
				$employeeCheck = $databaseConnection->prepare("SELECT id FROM {$profileTable} WHERE employee_id = ? LIMIT 1");
				if (!$employeeCheck) {
					throw new RuntimeException('Unable to validate the employee ID.');
				}
				$employeeCheck->bind_param('s', $formValues['employee_id']);
				if (!$employeeCheck->execute()) {
					$employeeCheck->close();
					throw new RuntimeException('Unable to validate the employee ID.');
				}
				$isDuplicateEmployeeId = $employeeCheck->get_result()->num_rows > 0;
				$employeeCheck->close();
				if ($isDuplicateEmployeeId) {
					throw new RuntimeException('Employee ID is already assigned.');
				}
			}

			$systemSettings = loadSystemSettings($databaseConnection);
			$databaseConnection->begin_transaction();
			$transactionStarted = true;
			$accountId = generateAccountId($databaseConnection, $formValues['employee_id']);
			$temporaryPassword = 'infoconnect';
			$passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
			$profileTable = $formValues['role'] === 'admin' ? 'admin_profiles' : 'staff_profiles';
			$mustChangePassword = (int) $systemSettings['require_password_change_first_login'];

			$userInsert = $databaseConnection->prepare('INSERT INTO users (account_id, password, role, must_change_password) VALUES (?, ?, ?, ?)');
			if (!$userInsert) {
				throw new RuntimeException('Unable to prepare the personnel account.');
			}
			$userInsert->bind_param('sssi', $accountId, $passwordHash, $formValues['role'], $mustChangePassword);
			if (!$userInsert->execute()) {
				throw new RuntimeException('Unable to create the personnel account.');
			}
			$userId = (int) $databaseConnection->insert_id;
			$userInsert->close();

			$profileInsert = $databaseConnection->prepare("INSERT INTO {$profileTable} (user_id, employee_id, first_name, middle_name, last_name, email, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
			if (!$profileInsert) {
				throw new RuntimeException('Unable to prepare the personnel profile.');
			}
			$middleName = $formValues['middle_name'] !== '' ? $formValues['middle_name'] : null;
			$email = $formValues['email'] !== '' ? $formValues['email'] : null;
			$contactNumber = $formValues['contact_number'] !== '' ? $formValues['contact_number'] : null;
			$profileInsert->bind_param('issssss', $userId, $formValues['employee_id'], $formValues['first_name'], $middleName, $formValues['last_name'], $email, $contactNumber);
			if (!$profileInsert->execute()) {
				throw new RuntimeException('Unable to create the personnel profile.');
			}
			$profileInsert->close();

			$fullName = trim($formValues['first_name'] . ' ' . $formValues['last_name']);
			writeActivityLog(
				$databaseConnection,
				$adminId,
				'create',
				'Accounts',
				'Created ' . $formValues['role'] . ' account ' . $accountId . ' for ' . $fullName . '.',
				$userId
			);
			$databaseConnection->commit();
			$transactionStarted = false;
			$deliveryStatus = sendPersonnelCredentialNotifications($formValues['email'], $formValues['contact_number'], $fullName, $accountId, $temporaryPassword, ucfirst($formValues['role']));
			$_SESSION['created_user_account'] = [
				'account_id' => $accountId,
				'temporary_password' => $temporaryPassword,
				'role' => ucfirst($formValues['role']),
				'name' => $fullName,
				'delivery' => $deliveryStatus,
			];
			$_SESSION['users_add_token'] = bin2hex(random_bytes(32));
			header('Location: /BMirk/admin/management/users.php', true, 303);
			exit;
		} catch (Throwable $exception) {
			if ($transactionStarted) {
				$databaseConnection->rollback();
			}
			$formError = $exception instanceof RuntimeException
				? $exception->getMessage()
				: 'The account could not be created. Check that the employee ID is unique and try again.';
		}
	}
}
$userAccounts = [];
$personnelCounts = [
	'total' => 0,
	'active' => 0,
	'inactive' => 0,
	'admins' => 0,
	'staff' => 0,
];

$query = $databaseConnection->query(
	"SELECT u.id, u.account_id, u.status, u.last_login, ap.employee_id, ap.first_name, ap.middle_name, ap.last_name, ap.suffix, ap.contact_number, 'Admin' AS account_role
	 FROM users u
	 LEFT JOIN admin_profiles ap ON ap.user_id = u.id
	 WHERE u.role = 'admin'

	 UNION ALL

	 SELECT u.id, u.account_id, u.status, u.last_login, sp.employee_id, sp.first_name, sp.middle_name, sp.last_name, sp.suffix, sp.contact_number, 'Staff' AS account_role
	 FROM users u
	 LEFT JOIN staff_profiles sp ON sp.user_id = u.id
	 WHERE u.role = 'staff'
	 ORDER BY last_name, first_name, account_id"
);

if ($query) {
	while ($row = $query->fetch_assoc()) {
		$status = strtolower($row['status'] ?? 'inactive');
		$role = $row['account_role'] ?? 'Admin';
		$personnelCounts['total']++;
		$personnelCounts[$status === 'active' ? 'active' : 'inactive']++;
		$personnelCounts[$role === 'Admin' ? 'admins' : 'staff']++;

		$fullName = trim(
			($row['first_name'] ?? '') . ' ' .
			($row['middle_name'] ? $row['middle_name'] . ' ' : '') .
			($row['last_name'] ?? '') . ' ' .
			($row['suffix'] ?? '')
		);

		$userAccounts[] = [
			'database_id' => (int) $row['id'],
			'account_id' => $row['account_id'] ?? 'N/A',
			'employee_id' => $row['employee_id'] ?? 'N/A',
			'contact_number' => $row['contact_number'] ?? '',
			'full_name' => $fullName ?: 'Unassigned',
			'status' => ucfirst($status),
			'raw_status' => $status,
			'last_login' => $row['last_login'] ? date('M d, Y h:i A', strtotime($row['last_login'])) : 'Never',
			'account_role' => $role
		];
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="stylesheet" href="../home.css">
	<link rel="stylesheet" href="../assets/table.css?v=3">
	<link rel="stylesheet" href="assets/users_form.css?v=12">
	<link rel="stylesheet" href="assets/seniors_form.css?v=8">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>
		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>
			<section class="admin-content personnel-page">
				<?php if ($usersFlash !== ''): ?>
					<div class="user-success" role="status"><?php echo htmlspecialchars($usersFlash, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>
				<?php if ($createdAccount): ?>
					<section class="credential-notice" aria-labelledby="personnel-credential-title">
						<h2 id="personnel-credential-title">Account created</h2>
						<p><?php echo htmlspecialchars($createdAccount['name'], ENT_QUOTES, 'UTF-8'); ?> now has an active <?php echo htmlspecialchars($createdAccount['role'], ENT_QUOTES, 'UTF-8'); ?> account. Share these credentials securely; the default password will not be shown again.</p>
						<p class="credential-delivery-status">Email: <strong><?php echo htmlspecialchars($createdAccount['delivery']['email'] ?? 'not sent', ENT_QUOTES, 'UTF-8'); ?></strong> — <?php echo htmlspecialchars($createdAccount['delivery']['email_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>SMS: <strong><?php echo htmlspecialchars($createdAccount['delivery']['sms'] ?? 'not sent', ENT_QUOTES, 'UTF-8'); ?></strong> — <?php echo htmlspecialchars($createdAccount['delivery']['sms_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
						<dl class="credential-list">
							<div><dt>Account ID</dt><dd><code><?php echo htmlspecialchars($createdAccount['account_id'], ENT_QUOTES, 'UTF-8'); ?></code></dd></div>
							<div><dt>Default password</dt><dd><code><?php echo htmlspecialchars($createdAccount['temporary_password'], ENT_QUOTES, 'UTF-8'); ?></code></dd></div>
						</dl>
					</section>
				<?php endif; ?>
				<div class="personnel-heading">
					<div>
						<h1>Personnel Management</h1>
						<p>View and manage admin and staff accounts.</p>
					</div>
				</div>

				<div class="personnel-summary-grid" aria-label="Personnel account summary">
					<article class="personnel-summary-card summary-total">
						<span class="personnel-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span>
						<span class="personnel-summary-copy"><span>Total Personnel</span><strong><?php echo number_format($personnelCounts['total']); ?></strong><small>Admin and staff accounts</small></span>
					</article>
					<article class="personnel-summary-card summary-active">
						<span class="personnel-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2z"/></svg></span>
						<span class="personnel-summary-copy"><span>Active Accounts</span><strong><?php echo number_format($personnelCounts['active']); ?></strong><small>Accounts ready to use</small></span>
					</article>
					<article class="personnel-summary-card summary-pending">
						<span class="personnel-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
						<span class="personnel-summary-copy"><span>Inactive Accounts</span><strong><?php echo number_format($personnelCounts['inactive']); ?></strong><small>Currently inactive</small></span>
					</article>
					<article class="personnel-summary-card summary-unlocated personnel-summary-roles">
						<span class="personnel-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
						<span class="personnel-summary-copy"><span>Account Roles</span><strong class="personnel-role-counts"><?php echo number_format($personnelCounts['admins']); ?> Admin · <?php echo number_format($personnelCounts['staff']); ?> Staff</strong><small>Personnel by role</small></span>
					</article>
				</div>

				<div class="personnel-workspace<?php echo $showAddForm ? ' has-add-form' : ''; ?>">
				<div class="page-card personnel-table-card">
					<div class="seniors-list-heading">
						<button class="add-senior-button personnel-add-button" id="addPersonnelButton" type="button" aria-expanded="<?php echo $showAddForm ? 'true' : 'false'; ?>" aria-controls="addPersonnelPanel">
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
							<span><?php echo $showAddForm ? 'Close Form' : 'Add Account'; ?></span>
						</button>
					</div>
					<div class="filter-grid">
						<div class="search-group">
							<input id="accountSearch" type="search" placeholder="Search by name, employee ID, or account ID..." aria-label="Search personnel">
							<button type="button" id="searchButton">Search</button>
						</div>
						<label class="filter-field">
							<span>Role</span>
							<select id="roleFilter">
								<option value="All">All</option>
								<option value="Admin">Admin</option>
								<option value="Staff">Staff</option>
							</select>
						</label>
						<label class="filter-field">
							<span>Account Status</span>
							<select id="statusFilter">
								<option value="All">All</option>
								<option value="Active">Active</option>
								<option value="Inactive">Inactive</option>
							</select>
						</label>
					</div>

					<div class="table-wrap">
						<table class="data-table personnel-data-table" id="userAccountsTable">
							<thead>
								<tr>
									<th>#</th>
									<th>Employee ID</th>
									<th>Account ID</th>
									<th>Full Name</th>
									<th>Role</th>
									<th>Contact</th>
									<th>Account Status</th>
									<th>Last Login</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($userAccounts)): ?>
									<tr><td colspan="9" class="empty-personnel">No personnel accounts found.</td></tr>
								<?php else: ?>
									<?php foreach ($userAccounts as $index => $account): ?>
										<tr data-index="<?php echo $index + 1; ?>" data-search="<?php echo htmlspecialchars(strtolower($account['account_id'] . ' ' . $account['employee_id'] . ' ' . $account['full_name'] . ' ' . $account['account_role'] . ' ' . $account['status']), ENT_QUOTES, 'UTF-8'); ?>" data-role="<?php echo htmlspecialchars($account['account_role'], ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo htmlspecialchars($account['status'], ENT_QUOTES, 'UTF-8'); ?>">
											<td class="personnel-row-number"><?php echo $index + 1; ?></td>
											<td><?php echo htmlspecialchars($account['employee_id'], ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($account['account_id'], ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($account['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($account['account_role'], ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($account['contact_number'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
											<td><span class="status-badge <?php echo htmlspecialchars($account['raw_status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($account['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
											<td><?php echo htmlspecialchars($account['last_login'], ENT_QUOTES, 'UTF-8'); ?></td>
											<td><a class="section-action" href="users_edit.php?id=<?php echo (int) $account['database_id']; ?>">Edit</a></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<div class="personnel-table-footer">
						<p class="personnel-result-count" id="personnelResultCount" aria-live="polite"></p>
						<div class="pagination" id="userPagination" aria-label="Personnel result pages"></div>
					</div>
				</div>
				<aside class="page-card personnel-add-panel" id="addPersonnelPanel" aria-labelledby="addPersonnelPanelTitle"<?php echo $showAddForm ? '' : ' hidden'; ?>>
					<div class="personnel-add-panel-heading">
						<h2 id="addPersonnelPanelTitle">Add Personnel</h2>
						<button class="close-add-personnel" id="closePersonnelFormButton" type="button" aria-label="Close Add Personnel form">&times;</button>
					</div>
					<?php if ($formError !== ''): ?>
						<div class="form-error" role="alert"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<form class="personnel-create-form" method="post">
						<input type="hidden" name="create_personnel" value="1">
						<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['users_add_token'], ENT_QUOTES, 'UTF-8'); ?>">
						<h3>Name Information</h3>
						<div class="personnel-create-fields">
							<label class="form-field"><span>Last Name <b>*</b></span><input name="last_name" type="text" maxlength="100" autocomplete="family-name" value="<?php echo htmlspecialchars($formValues['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
							<label class="form-field"><span>First Name <b>*</b></span><input name="first_name" type="text" maxlength="100" autocomplete="given-name" value="<?php echo htmlspecialchars($formValues['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
							<label class="form-field"><span>Middle Name</span><input name="middle_name" type="text" maxlength="100" value="<?php echo htmlspecialchars($formValues['middle_name'], ENT_QUOTES, 'UTF-8'); ?>"></label>
							<label class="form-field"><span>Employee ID <b>*</b></span><input name="employee_id" type="text" maxlength="50" value="<?php echo htmlspecialchars($formValues['employee_id'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
						</div>
						<h3>Contact Information</h3>
						<div class="personnel-create-fields">
							<label class="form-field"><span>Email <b>*</b></span><input name="email" type="email" maxlength="150" autocomplete="email" required value="<?php echo htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8'); ?>"></label>
							<label class="form-field"><span>Contact Number <b>*</b></span><input name="contact_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" autocomplete="tel" required value="<?php echo htmlspecialchars($formValues['contact_number'], ENT_QUOTES, 'UTF-8'); ?>"></label>
						</div>
						<h3>Account Settings</h3>
						<label class="form-field"><span>Role <b>*</b></span><select name="role" required><option value="staff"<?php echo $formValues['role'] === 'staff' ? ' selected' : ''; ?>>Staff</option><option value="admin"<?php echo $formValues['role'] === 'admin' ? ' selected' : ''; ?>>Admin</option></select></label>
						<div class="personnel-create-actions">
							<button class="secondary-form-action" type="reset">Reset</button>
							<button class="primary-add-btn" type="submit">Create Account</button>
						</div>
					</form>
				</aside>
				</div>
			</section>
		</main>
	</div>

	<script>
		const searchInput = document.getElementById('accountSearch');
		const searchButton = document.getElementById('searchButton');
		const statusFilter = document.getElementById('statusFilter');
		const roleFilter = document.getElementById('roleFilter');
		const tableBody = document.querySelector('#userAccountsTable tbody');
		const userPagination = document.getElementById('userPagination');
		const resultCount = document.getElementById('personnelResultCount');
		const addPersonnelButton = document.getElementById('addPersonnelButton');
		const closePersonnelFormButton = document.getElementById('closePersonnelFormButton');
		const addPersonnelPanel = document.getElementById('addPersonnelPanel');
		const personnelWorkspace = document.querySelector('.personnel-workspace');
		const pageSize = 10;
		let currentPage = 1;

		function setPersonnelFormOpen(isOpen) {
			addPersonnelPanel.hidden = !isOpen;
			personnelWorkspace.classList.toggle('has-add-form', isOpen);
			addPersonnelButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			addPersonnelButton.textContent = isOpen ? 'Close Form' : 'Add Account';
			if (isOpen) addPersonnelPanel.querySelector('input[name="last_name"]').focus();
			else addPersonnelButton.focus();
		}

		addPersonnelButton.addEventListener('click', function () {
			setPersonnelFormOpen(addPersonnelPanel.hidden);
		});
		closePersonnelFormButton.addEventListener('click', function () {
			setPersonnelFormOpen(false);
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !addPersonnelPanel.hidden) setPersonnelFormOpen(false);
		});

		function renderUserPagination(totalPages) {
			if (!userPagination) return;
			if (totalPages <= 1) {
				userPagination.replaceChildren();
				return;
			}

			const pages = new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
			const orderedPages = Array.from(pages).filter(function (page) {
				return page >= 1 && page <= totalPages;
			}).sort(function (a, b) {
				return a - b;
			});
			const controls = [
				'<button class="page-arrow" type="button" data-page="' + (currentPage - 1) + '" aria-label="Previous page"' + (currentPage === 1 ? ' disabled' : '') + '>&lsaquo;</button>'
			];

			let previousPage = 0;
			orderedPages.forEach(function (page) {
				if (page - previousPage > 1) controls.push('<span class="page-ellipsis" aria-hidden="true">...</span>');
				controls.push('<button class="page-btn' + (page === currentPage ? ' active' : '') + '" type="button" data-page="' + page + '" aria-current="' + (page === currentPage ? 'page' : 'false') + '">' + page + '</button>');
				previousPage = page;
			});
			controls.push(
				'<button class="page-arrow" type="button" data-page="' + (currentPage + 1) + '" aria-label="Next page"' + (currentPage === totalPages ? ' disabled' : '') + '>&rsaquo;</button>'
			);
			userPagination.innerHTML = controls.join('');

			userPagination.querySelectorAll('button[data-page]').forEach(function (button) {
				button.addEventListener('click', function () {
					const targetPage = Number(button.dataset.page);
					if (targetPage >= 1 && targetPage <= totalPages) {
						currentPage = targetPage;
						applyUserFilters();
					}
				});
			});
		}

		function applyUserFilters() {
			if (!tableBody) return;

			const rows = Array.from(tableBody.querySelectorAll('tr[data-search]'));
			const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
			const statusValue = statusFilter ? statusFilter.value : 'All';
			const roleValue = roleFilter ? roleFilter.value : 'All';
			const filteredRows = rows.filter(function (row) {
				const haystack = (row.dataset.search || '').toLowerCase();
				const rowRole = (row.dataset.role || '').toLowerCase();
				const rowStatus = (row.dataset.status || '').toLowerCase();
				const matchesTerm = haystack.includes(term);
				const matchesStatus = statusValue === 'All' || rowStatus === statusValue.toLowerCase();
				const matchesRole = roleValue === 'All' || rowRole === roleValue.toLowerCase();
				return matchesTerm && matchesStatus && matchesRole;
			});

			const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
			currentPage = Math.min(currentPage, totalPages);
			const startIndex = (currentPage - 1) * pageSize;
			const visibleRows = filteredRows.slice(startIndex, currentPage * pageSize);
			rows.forEach(function (row) {
				row.hidden = !visibleRows.includes(row);
				if (!row.hidden) {
					row.querySelector('.personnel-row-number').textContent = String(filteredRows.indexOf(row) + 1);
				}
			});

			resultCount.textContent = filteredRows.length
				? 'Showing ' + (startIndex + 1) + ' to ' + (startIndex + visibleRows.length) + ' of ' + filteredRows.length + ' records'
				: 'No records found';
			renderUserPagination(totalPages);
		}

		if (searchInput && tableBody) {
			searchInput.addEventListener('input', function () {
				currentPage = 1;
				applyUserFilters();
			});
			if (searchButton) searchButton.addEventListener('click', function () {
				currentPage = 1;
				applyUserFilters();
			});
			if (statusFilter) statusFilter.addEventListener('change', function () {
				currentPage = 1;
				applyUserFilters();
			});
			if (roleFilter) roleFilter.addEventListener('change', function () {
				currentPage = 1;
				applyUserFilters();
			});
			applyUserFilters();
		}
	</script>
</body>
</html>
