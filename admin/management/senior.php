<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/activity_log.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: /BMirk/auth/login.php');
	exit;
}

$page_title = 'Senior Management';
$adminId = (int) ($_SESSION['user_id'] ?? 0);
$formErrors = [];
$successMessage = '';
$pageError = '';
$formValues = [
	'senior_citizen_id' => '',
	'first_name' => '',
	'middle_name' => '',
	'last_name' => '',
	'suffix' => '',
	'sex' => '',
	'birth_date' => '',
	'civil_status_id' => '',
	'barangay' => '',
	'address' => '',
	'contact_number' => '',
	'status' => 'active',
];
$civilStatuses = [];
$activeBarangays = [];
$requirementsVerified = [
	'birth_certificate' => false,
	'valid_id' => false,
];

if (empty($_SESSION['admin_senior_create_token'])) {
	$_SESSION['admin_senior_create_token'] = bin2hex(random_bytes(32));
}

try {
	$civilStatusResult = $databaseConnection->query('SELECT id, name FROM civil_statuses WHERE is_active = 1 ORDER BY name');
	if (!$civilStatusResult) {
		throw new RuntimeException($databaseConnection->error);
	}
	while ($row = $civilStatusResult->fetch_assoc()) {
		$civilStatuses[] = $row;
	}

	$barangayResult = $databaseConnection->query('SELECT name FROM barangays WHERE is_active = 1 ORDER BY name');
	if (!$barangayResult) {
		throw new RuntimeException($databaseConnection->error);
	}
	while ($row = $barangayResult->fetch_assoc()) {
		$activeBarangays[] = $row['name'];
	}
} catch (Throwable $exception) {
	error_log('Unable to load admin senior form options: ' . $exception->getMessage());
	$formErrors[] = 'Form options could not be loaded. Refresh the page or contact an administrator.';
}
if (!$civilStatuses && !in_array('No civil status options are configured. Contact an administrator.', $formErrors, true)) {
	$formErrors[] = 'No civil status options are configured. Contact an administrator.';
}
if (!$activeBarangays && !in_array('No active barangays are configured. Contact an administrator.', $formErrors, true)) {
	$formErrors[] = 'No active barangays are configured. Contact an administrator.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['create_senior'])) {
	foreach ($formValues as $field => $default) {
		$value = $_POST[$field] ?? $default;
		$formValues[$field] = is_string($value) ? trim($value) : $default;
	}
	$postedRequirements = $_POST['requirements'] ?? [];
	if (!is_array($postedRequirements)) {
		$formErrors[] = 'Choose valid verified requirements.';
	} else {
		foreach ($postedRequirements as $requirementType => $requirementValue) {
			if (!array_key_exists($requirementType, $requirementsVerified) || $requirementValue !== '1') {
				$formErrors[] = 'Choose valid verified requirements.';
				break;
			}
		}
		foreach ($requirementsVerified as $requirementType => $isVerified) {
			$requirementsVerified[$requirementType] = ($postedRequirements[$requirementType] ?? null) === '1';
		}
	}

	$submittedToken = $_POST['csrf_token'] ?? '';
	if (!is_string($submittedToken) || !hash_equals($_SESSION['admin_senior_create_token'], $submittedToken)) {
		$formErrors[] = 'This form has expired. Refresh the page and try again.';
	}

	foreach ([
		'senior_citizen_id' => 50,
		'first_name' => 100,
		'middle_name' => 100,
		'last_name' => 100,
		'suffix' => 20,
		'contact_number' => 30,
	] as $field => $maximumLength) {
		if (mb_strlen($formValues[$field], 'UTF-8') > $maximumLength) {
			$formErrors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be ' . $maximumLength . ' characters or fewer.';
		}
	}

	foreach (['senior_citizen_id', 'first_name', 'last_name', 'address', 'barangay'] as $requiredField) {
		if ($formValues[$requiredField] === '') {
			$formErrors[] = ucfirst(str_replace('_', ' ', $requiredField)) . ' is required.';
		}
	}

	if ($formValues['contact_number'] !== '' && !preg_match('/^\d{11}$/', $formValues['contact_number'])) {
		$formErrors[] = 'Contact number must contain exactly 11 digits.';
	}
	if (!in_array($formValues['sex'], ['male', 'female'], true)) {
		$formErrors[] = 'Choose a valid sex.';
	}

	$birthDate = DateTime::createFromFormat('!Y-m-d', $formValues['birth_date']);
	if (!$birthDate || $birthDate->format('Y-m-d') !== $formValues['birth_date'] || $formValues['birth_date'] > date('Y-m-d')) {
		$formErrors[] = 'Enter a valid birth date that is not in the future.';
	} elseif ((int) $birthDate->diff(new DateTimeImmutable('today'))->format('%y') < 60) {
		$formErrors[] = 'Senior citizens must be at least 60 years old.';
	}

	$civilStatusIds = array_map(static fn ($status) => (int) $status['id'], $civilStatuses);
	$civilStatusId = filter_var($formValues['civil_status_id'], FILTER_VALIDATE_INT);
	if ($civilStatusId === false || !in_array($civilStatusId, $civilStatusIds, true)) {
		$formErrors[] = 'Choose a valid civil status.';
	}
	if (!in_array($formValues['barangay'], $activeBarangays, true)) {
		$formErrors[] = 'Choose a valid barangay.';
	}
	if (!in_array($formValues['status'], ['active', 'pending', 'unlocated', 'transferred', 'unclaimed', 'deceased'], true)) {
		$formErrors[] = 'Choose a valid senior status.';
	}
	if ($formValues['status'] === 'active' && in_array(false, $requirementsVerified, true)) {
		$formErrors[] = 'Verify both the birth certificate and valid ID before setting this record as active.';
	}

	if (!$formErrors) {
		try {
			$duplicateStatement = $databaseConnection->prepare('SELECT id FROM senior_profiles WHERE senior_citizen_id = ? LIMIT 1');
			if (!$duplicateStatement) {
				throw new RuntimeException('Unable to validate the senior ID.');
			}
			$duplicateStatement->bind_param('s', $formValues['senior_citizen_id']);
			$duplicateStatement->execute();
			$isDuplicate = $duplicateStatement->get_result()->num_rows > 0;
			$duplicateStatement->close();
			if ($isDuplicate) {
				$formErrors[] = 'That Senior Citizen ID is already in use.';
			}
		} catch (Throwable $exception) {
			error_log('Unable to validate new admin senior ID: ' . $exception->getMessage());
			$formErrors[] = 'The Senior Citizen ID could not be validated. Try again.';
		}
	}

	if (!$formErrors) {
		$transactionStarted = false;
		try {
			$databaseConnection->begin_transaction();
			$transactionStarted = true;

			$middleName = $formValues['middle_name'] !== '' ? $formValues['middle_name'] : null;
			$suffix = $formValues['suffix'] !== '' ? $formValues['suffix'] : null;
			$contactNumber = $formValues['contact_number'] !== '' ? $formValues['contact_number'] : null;
			$insertStatement = $databaseConnection->prepare(
				"INSERT INTO senior_profiles
				 (senior_citizen_id, last_name, first_name, middle_name, suffix, sex, birth_date, civil_status_id,
				  address, barangay, contact_number, status, approval_status, submitted_by, reviewed_by, reviewed_at)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, CURRENT_TIMESTAMP)"
			);
			if (!$insertStatement) {
				throw new RuntimeException('Unable to prepare the senior record.');
			}
			$insertStatement->bind_param(
				'sssssssissssii',
				$formValues['senior_citizen_id'],
				$formValues['last_name'],
				$formValues['first_name'],
				$middleName,
				$suffix,
				$formValues['sex'],
				$formValues['birth_date'],
				$civilStatusId,
				$formValues['address'],
				$formValues['barangay'],
				$contactNumber,
				$formValues['status'],
				$adminId,
				$adminId
			);
			if (!$insertStatement->execute()) {
				throw new RuntimeException('Unable to save the senior record.');
			}
			$seniorId = (int) $databaseConnection->insert_id;
			$insertStatement->close();

			$requirementStatement = $databaseConnection->prepare(
				'INSERT INTO senior_requirements (senior_id, requirement_type, status, reviewed_by, reviewed_at)
				 VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)'
			);
			if (!$requirementStatement) {
				throw new RuntimeException('Unable to prepare senior requirement records.');
			}
			$requirementType = '';
			$requirementStatus = '';
			$requirementStatement->bind_param('issi', $seniorId, $requirementType, $requirementStatus, $adminId);
			foreach ($requirementsVerified as $requirementType => $isVerified) {
				$requirementStatus = $isVerified ? 'approved' : 'missing';
				if (!$requirementStatement->execute()) {
					throw new RuntimeException('Unable to save senior requirement records.');
				}
			}
			$requirementStatement->close();

			writeActivityLog(
				$databaseConnection,
				$adminId,
				'create',
				'Seniors',
				'Created senior record ' . $formValues['senior_citizen_id'] . '.',
				$seniorId
			);
			$databaseConnection->commit();
			$transactionStarted = false;
			$_SESSION['admin_senior_create_token'] = bin2hex(random_bytes(32));
			$_SESSION['admin_senior_flash'] = 'Senior record added successfully.';
			header('Location: /BMirk/admin/management/senior.php', true, 303);
			exit;
		} catch (Throwable $exception) {
			if ($transactionStarted) {
				$databaseConnection->rollback();
			}
			error_log('Unable to create admin senior record: ' . $exception->getMessage());
			$formErrors[] = 'The senior record could not be saved. Review the details and try again.';
		}
	}
}

$successMessage = is_string($_SESSION['admin_senior_flash'] ?? null) ? $_SESSION['admin_senior_flash'] : '';
unset($_SESSION['admin_senior_flash']);
$showAddForm = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['create_senior']))
	|| !$civilStatuses
	|| !$activeBarangays
	|| $formErrors !== [];
$seniorRecords = [];
$seniorCounts = [
	'total' => 0,
	'with_account' => 0,
	'pending_approval' => 0,
	'deceased_inactive' => 0,
];
$query = $databaseConnection->query(
	"SELECT sp.id, sp.senior_citizen_id, sp.first_name, sp.middle_name, sp.last_name, sp.suffix, sp.sex, sp.birth_date, sp.address, sp.barangay, sp.status, sp.approval_status,
	        u.account_id, u.status AS account_status
	 FROM senior_profiles sp
	 LEFT JOIN users u ON u.id = sp.user_id AND u.role = 'senior'
	 ORDER BY sp.last_name, sp.first_name"
);

if (!$query) {
	error_log('Unable to load admin senior records: ' . $databaseConnection->error);
	$pageError = 'Senior records could not be loaded. Refresh the page or contact an administrator.';
} else {
	while ($row = $query->fetch_assoc()) {
		$status = $row['approval_status'] !== 'approved' ? 'pending' : strtolower($row['status']);
		$seniorCounts['total']++;
		if (!empty($row['account_id'])) {
			$seniorCounts['with_account']++;
		}
		if ($row['approval_status'] === 'pending') {
			$seniorCounts['pending_approval']++;
		}
		if ($row['status'] === 'deceased' || $row['account_status'] === 'inactive') {
			$seniorCounts['deceased_inactive']++;
		}

		$fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name'] . ' ' . ($row['suffix'] ?? ''));
		$birthDate = $row['birth_date'] ?: '2000-01-01';
		$age = (int) date_diff(date_create($birthDate), date_create('today'))->format('%y');

		$seniorRecords[] = [
			'database_id' => (int) $row['id'],
			'id' => $row['senior_citizen_id'],
			'name' => $fullName,
			'sex' => ucfirst($row['sex']),
			'age' => $age,
			'address' => $row['address'],
			'barangay' => $row['barangay'] ?: 'N/A',
			'status' => ucfirst($status),
			'raw_status' => $status,
			'account_id' => $row['account_id'] ?: 'N/A',
			'account_status' => $row['account_status'] ? ucfirst($row['account_status']) : 'Inactive',
			'raw_account_status' => $row['account_status'] ?: 'inactive'
		];
	}
}

$barangays = array_values(array_unique(array_column($seniorRecords, 'barangay')));
sort($barangays, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="stylesheet" href="../home.css">
	<link rel="stylesheet" href="../assets/table.css?v=3">
	<link rel="stylesheet" href="assets/seniors_form.css?v=8">
</head>
<body>
	<div class="admin-layout">
		<?php include __DIR__ . '/../includes/sidebar.php'; ?>

		<main class="admin-main">
			<?php include __DIR__ . '/../includes/header.php'; ?>

			<section class="admin-content seniors-page">
				<div class="seniors-heading">
					<h1>Senior Citizens</h1>
					<p>Manage and view all senior citizen records.</p>
				</div>
				<?php if ($pageError !== ''): ?>
					<div class="senior-form-errors" role="alert"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>
				<?php if ($successMessage !== ''): ?>
					<div class="senior-form-success" role="status"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<div class="senior-summary-grid" aria-label="Senior record summary">
					<article class="senior-summary-card summary-total">
						<span class="senior-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M15 14c3.8-.2 5.8 1.7 6.2 5"/></svg></span>
						<span class="senior-summary-copy"><span>Total Seniors</span><strong><?php echo number_format($seniorCounts['total']); ?></strong><small>Senior citizen records</small></span>
					</article>
					<article class="senior-summary-card summary-active">
						<span class="senior-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2z"/></svg></span>
						<span class="senior-summary-copy"><span>Seniors with Account</span><strong><?php echo number_format($seniorCounts['with_account']); ?></strong><small><?php echo $seniorCounts['total'] > 0 ? number_format(($seniorCounts['with_account'] / $seniorCounts['total']) * 100, 1) : '0.0'; ?>% of seniors</small></span>
					</article>
					<article class="senior-summary-card summary-pending">
						<span class="senior-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2.5h9l5 5v14H6z"/><path d="M14 2.5v5h6M9 12h8m-8 3.5h8m-8 3.5h5"/></svg></span>
						<span class="senior-summary-copy"><span>Pending for Approval</span><strong><?php echo number_format($seniorCounts['pending_approval']); ?></strong><small>Awaiting review</small></span>
					</article>
					<article class="senior-summary-card summary-unlocated">
						<span class="senior-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M2.5 20c.3-3.8 2.5-5.8 6.5-5.8s6.2 2 6.5 5.8M18 8l4 4m0-4-4 4"/></svg></span>
						<span class="senior-summary-copy"><span>Deceased / Inactive</span><strong><?php echo number_format($seniorCounts['deceased_inactive']); ?></strong><small>Records needing attention</small></span>
					</article>
				</div>

				<div class="seniors-workspace<?php echo $showAddForm ? ' has-add-form' : ''; ?>">
				<section class="page-card seniors-table-card" aria-label="Senior records">
					<div class="seniors-list-heading">
						<button class="add-senior-button" id="addSeniorButton" type="button" aria-expanded="<?php echo $showAddForm ? 'true' : 'false'; ?>" aria-controls="addSeniorPanel">
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
							<span><?php echo $showAddForm ? 'Close Form' : 'Add Senior'; ?></span>
						</button>
					</div>
					<div class="filter-grid">
						<div class="search-group">
							<input id="searchInput" type="search" placeholder="Search by name, senior ID, or address..." aria-label="Search seniors">
							<button id="searchButton" type="button">Search</button>
						</div>
						<label class="filter-field">
							<span>Age Range</span>
							<select id="filterAge">
								<option value="All">All</option>
								<option value="60-69">60-69</option>
								<option value="70-79">70-79</option>
								<option value="80+">80+</option>
							</select>
						</label>
						<label class="filter-field">
							<span>Status</span>
							<select id="filterSeniorStatus">
								<option value="All">All</option>
								<option value="Active">Active</option>
								<option value="Pending">Pending</option>
								<option value="Unlocated">Unlocated</option>
								<option value="Transferred">Transferred</option>
								<option value="Unclaimed">Unclaimed</option>
								<option value="Deceased">Deceased</option>
							</select>
						</label>
						<label class="filter-field">
							<span>Barangay</span>
							<select id="filterBarangay">
								<option value="All">All</option>
								<?php foreach ($barangays as $barangay): ?>
									<option value="<?php echo htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8'); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="filter-field">
							<span>Sex</span>
							<select id="filterSex">
								<option value="All">All</option>
								<option value="Male">Male</option>
								<option value="Female">Female</option>
							</select>
						</label>
						<label class="filter-field">
							<span>Account Status</span>
							<select id="filterAccountStatus">
								<option value="All">All</option>
								<option value="Active">Active</option>
								<option value="Inactive">Inactive</option>
							</select>
						</label>
					</div>

					<div class="table-wrap">
						<table class="data-table senior-data-table">
							<thead>
								<tr>
										<th>#</th>
										<th>Senior ID</th>
										<th>Full Name</th>
									<th>Age</th>
										<th>Sex</th>
										<th>Barangay</th>
										<th>Status</th>
									<th>Account ID</th>
										<th>Account Status</th>
										<th>Actions</th>
									</tr>
								</thead>
								<tbody id="seniorTableBody">
									<?php if (empty($seniorRecords)): ?>
										<tr><td colspan="10" class="empty-seniors">No senior records found.</td></tr>
									<?php else: ?>
										<?php foreach ($seniorRecords as $index => $record): ?>
											<tr data-index="<?php echo $index + 1; ?>" data-name="<?php echo htmlspecialchars(strtolower($record['name']), ENT_QUOTES, 'UTF-8'); ?>" data-id="<?php echo htmlspecialchars(strtolower($record['id']), ENT_QUOTES, 'UTF-8'); ?>" data-address="<?php echo htmlspecialchars(strtolower($record['address']), ENT_QUOTES, 'UTF-8'); ?>" data-sex="<?php echo htmlspecialchars($record['sex'], ENT_QUOTES, 'UTF-8'); ?>" data-age="<?php echo (int) $record['age']; ?>" data-barangay="<?php echo htmlspecialchars($record['barangay'], ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8'); ?>" data-account-status="<?php echo htmlspecialchars($record['account_status'], ENT_QUOTES, 'UTF-8'); ?>">
												<td class="row-number"><?php echo $index + 1; ?></td>
												<td><?php echo htmlspecialchars($record['id'], ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars($record['name'], ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo (int) $record['age']; ?></td>
												<td><?php echo htmlspecialchars($record['sex'], ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars($record['barangay'], ENT_QUOTES, 'UTF-8'); ?></td>
												<td><span class="status-badge <?php echo htmlspecialchars($record['raw_status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
												<td><?php echo htmlspecialchars($record['account_id'], ENT_QUOTES, 'UTF-8'); ?></td>
												<td><span class="status-badge <?php echo htmlspecialchars($record['raw_account_status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($record['account_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
												<td><a class="section-action" href="senior_view.php?id=<?php echo (int) $record['database_id']; ?>">View</a></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<div class="senior-table-footer">
						<p class="senior-result-count" id="seniorResultCount" aria-live="polite"></p>
						<div class="pagination" id="pagination" aria-label="Senior result pages"></div>
					</div>
				</section>

				<aside class="page-card add-senior-panel" id="addSeniorPanel" aria-labelledby="addSeniorPanelTitle"<?php echo $showAddForm ? '' : ' hidden'; ?>>
					<div class="add-senior-panel-heading">
						<h2 id="addSeniorPanelTitle">Add New Senior</h2>
						<button class="close-add-senior" id="closeAddSeniorButton" type="button" aria-label="Close Add Senior form">&times;</button>
					</div>
					<?php if ($formErrors): ?>
						<div class="senior-form-errors" role="alert">
							<ul><?php foreach (array_unique($formErrors) as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
						</div>
					<?php endif; ?>
					<form method="post" class="add-senior-form">
						<input type="hidden" name="create_senior" value="1">
						<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_senior_create_token'], ENT_QUOTES, 'UTF-8'); ?>">
						<section class="add-senior-section" aria-labelledby="addSeniorPersonalHeading">
							<h3 id="addSeniorPersonalHeading">Personal Information</h3>
							<div class="add-senior-fields">
								<label class="add-senior-field"><span>Senior ID <b>*</b></span><input name="senior_citizen_id" maxlength="50" required value="<?php echo htmlspecialchars($formValues['senior_citizen_id'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off"></label>
								<label class="add-senior-field"><span>First Name <b>*</b></span><input name="first_name" maxlength="100" required value="<?php echo htmlspecialchars($formValues['first_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="given-name"></label>
								<label class="add-senior-field"><span>Last Name <b>*</b></span><input name="last_name" maxlength="100" required value="<?php echo htmlspecialchars($formValues['last_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="family-name"></label>
								<label class="add-senior-field"><span>Middle Name</span><input name="middle_name" maxlength="100" value="<?php echo htmlspecialchars($formValues['middle_name'], ENT_QUOTES, 'UTF-8'); ?>"></label>
								<label class="add-senior-field"><span>Suffix</span><select name="suffix"><option value="">None</option><?php foreach (['Jr.', 'Sr.', 'II', 'III', 'IV'] as $suffix): ?><option value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $formValues['suffix'] === $suffix ? ' selected' : ''; ?>><?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
								<label class="add-senior-field"><span>Sex <b>*</b></span><select name="sex" required><option value="">Select sex</option><option value="male"<?php echo $formValues['sex'] === 'male' ? ' selected' : ''; ?>>Male</option><option value="female"<?php echo $formValues['sex'] === 'female' ? ' selected' : ''; ?>>Female</option></select></label>
								<label class="add-senior-field"><span>Birth Date <b>*</b></span><input id="newSeniorBirthDate" name="birth_date" type="date" max="<?php echo date('Y-m-d'); ?>" required value="<?php echo htmlspecialchars($formValues['birth_date'], ENT_QUOTES, 'UTF-8'); ?>"><small id="newSeniorAge">Age will be calculated</small></label>
								<label class="add-senior-field"><span>Civil Status <b>*</b></span><select name="civil_status_id" required><option value="">Select civil status</option><?php foreach ($civilStatuses as $civilStatus): ?><option value="<?php echo (int) $civilStatus['id']; ?>"<?php echo (string) $civilStatus['id'] === $formValues['civil_status_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($civilStatus['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
							</div>
						</section>
						<section class="add-senior-section" aria-labelledby="addSeniorContactHeading">
							<h3 id="addSeniorContactHeading">Contact Information</h3>
							<div class="add-senior-fields">
								<label class="add-senior-field full-field"><span>Barangay <b>*</b></span><select name="barangay" required><option value="">Select barangay</option><?php foreach ($activeBarangays as $barangay): ?><option value="<?php echo htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $formValues['barangay'] === $barangay ? ' selected' : ''; ?>><?php echo htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
								<label class="add-senior-field full-field"><span>Address <b>*</b></span><textarea name="address" rows="2" required><?php echo htmlspecialchars($formValues['address'], ENT_QUOTES, 'UTF-8'); ?></textarea></label>
								<label class="add-senior-field full-field"><span>Contact Number</span><input name="contact_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" placeholder="11-digit contact number" value="<?php echo htmlspecialchars($formValues['contact_number'], ENT_QUOTES, 'UTF-8'); ?>"></label>
							</div>
						</section>
						<section class="add-senior-section" aria-labelledby="addSeniorStatusHeading">
							<h3 id="addSeniorStatusHeading">Status</h3>
							<label class="add-senior-field full-field"><span>Record Status <b>*</b></span><select name="status" required><?php foreach (['active' => 'Active', 'pending' => 'Pending', 'unlocated' => 'Unlocated', 'transferred' => 'Transferred', 'unclaimed' => 'Unclaimed', 'deceased' => 'Deceased'] as $statusValue => $statusLabel): ?><option value="<?php echo $statusValue; ?>"<?php echo $formValues['status'] === $statusValue ? ' selected' : ''; ?>><?php echo $statusLabel; ?></option><?php endforeach; ?></select></label>
							<label class="add-senior-check"><input type="checkbox" name="requirements[birth_certificate]" value="1"<?php echo $requirementsVerified['birth_certificate'] ? ' checked' : ''; ?>><span>Birth certificate verified</span></label>
							<label class="add-senior-check"><input type="checkbox" name="requirements[valid_id]" value="1"<?php echo $requirementsVerified['valid_id'] ? ' checked' : ''; ?>><span>Valid ID verified</span></label>
						</section>
						<div class="add-senior-form-actions">
							<button class="add-senior-reset" type="reset">Reset</button>
							<button class="add-senior-save" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l4 4v14H3V3z"/><path d="M7 3v6h10V3M7 21v-7h10v7"/></svg>Save Senior</button>
						</div>
					</form>
				</aside>
				</div>
			</section>
		</main>
	</div>

	<script>
		const seniorRows = Array.from(document.querySelectorAll('#seniorTableBody tr[data-name]'));
		const searchInput = document.getElementById('searchInput');
		const searchButton = document.getElementById('searchButton');
		const addSeniorButton = document.getElementById('addSeniorButton');
		const closeAddSeniorButton = document.getElementById('closeAddSeniorButton');
		const addSeniorPanel = document.getElementById('addSeniorPanel');
		const seniorsWorkspace = document.querySelector('.seniors-workspace');
		const addSeniorForm = document.querySelector('.add-senior-form');
		const birthDateInput = document.getElementById('newSeniorBirthDate');
		const agePreview = document.getElementById('newSeniorAge');
		const filterSex = document.getElementById('filterSex');
		const filterAge = document.getElementById('filterAge');
		const filterBarangay = document.getElementById('filterBarangay');
		const filterSeniorStatus = document.getElementById('filterSeniorStatus');
		const filterAccountStatus = document.getElementById('filterAccountStatus');
		const pagination = document.getElementById('pagination');
		const resultCount = document.getElementById('seniorResultCount');
		const pageSize = 10;
		let currentPage = 1;

		function setAddSeniorOpen(isOpen) {
			addSeniorPanel.hidden = !isOpen;
			seniorsWorkspace.classList.toggle('has-add-form', isOpen);
			addSeniorButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			addSeniorButton.querySelector('span').textContent = isOpen ? 'Close Form' : 'Add Senior';
			if (isOpen) addSeniorPanel.querySelector('input[name="senior_citizen_id"]').focus();
			else addSeniorButton.focus();
		}

		function updateAgePreview() {
			if (!birthDateInput.value) {
				agePreview.textContent = 'Age will be calculated';
				return;
			}
			const birthDate = new Date(birthDateInput.value + 'T00:00:00');
			const today = new Date();
			let age = today.getFullYear() - birthDate.getFullYear();
			if (today.getMonth() < birthDate.getMonth() || (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) age--;
			agePreview.textContent = age >= 0 ? 'Age: ' + age : 'Enter a valid birth date';
		}

		addSeniorButton.addEventListener('click', function () {
			setAddSeniorOpen(addSeniorPanel.hidden);
		});
		closeAddSeniorButton.addEventListener('click', function () {
			setAddSeniorOpen(false);
		});
		birthDateInput.addEventListener('change', updateAgePreview);
		addSeniorForm.addEventListener('reset', function () {
			requestAnimationFrame(updateAgePreview);
		});
		updateAgePreview();
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !addSeniorPanel.hidden) setAddSeniorOpen(false);
		});

		function matchesAgeRange(age, range) {
			if (range === '60-69') return age >= 60 && age <= 69;
			if (range === '70-79') return age >= 70 && age <= 79;
			if (range === '80+') return age >= 80;
			return true;
		}

		function applyFilters() {
			const term = searchInput.value.trim().toLowerCase();
			const sex = filterSex.value;
			const age = filterAge.value;
			const barangay = filterBarangay.value;
			const status = filterSeniorStatus.value;
			const accountStatus = filterAccountStatus.value;
			const filteredRows = seniorRows.filter(function (row) {
				return (!term || row.dataset.name.includes(term) || row.dataset.id.includes(term) || row.dataset.address.includes(term))
					&& (sex === 'All' || row.dataset.sex === sex)
					&& matchesAgeRange(Number(row.dataset.age), age)
					&& (barangay === 'All' || row.dataset.barangay === barangay)
					&& (status === 'All' || row.dataset.status === status)
					&& (accountStatus === 'All' || row.dataset.accountStatus === accountStatus);
			});

			const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
			currentPage = Math.min(currentPage, totalPages);
			const startIndex = (currentPage - 1) * pageSize;
			const visibleRows = filteredRows.slice(startIndex, currentPage * pageSize);
			seniorRows.forEach(function (row) {
				row.hidden = !visibleRows.includes(row);
				if (!row.hidden) row.querySelector('.row-number').textContent = String(filteredRows.indexOf(row) + 1);
			});

			resultCount.textContent = filteredRows.length
				? 'Showing ' + (startIndex + 1) + ' to ' + (startIndex + visibleRows.length) + ' of ' + filteredRows.length + ' records'
				: 'No records found';
			renderPagination(totalPages);
		}

		function renderPagination(totalPages) {
			if (totalPages <= 1) {
				pagination.replaceChildren();
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

			pagination.innerHTML = controls.join('');

			pagination.querySelectorAll('button[data-page]').forEach(function (button) {
				button.addEventListener('click', function () {
					const targetPage = Number(button.dataset.page);
					if (targetPage >= 1 && targetPage <= totalPages) {
						currentPage = targetPage;
						applyFilters();
					}
				});
			});
		}

		[searchInput, filterSex, filterAge, filterBarangay, filterSeniorStatus, filterAccountStatus].forEach(function (control) {
			control.addEventListener('input', function () {
				currentPage = 1;
				applyFilters();
			});
			control.addEventListener('change', function () {
				currentPage = 1;
				applyFilters();
			});
		});

		if (searchButton) searchButton.addEventListener('click', applyFilters);
		if (seniorRows.length) {
			applyFilters();
		} else {
			resultCount.textContent = 'No records found';
		}
	</script>
</body>
</html>
