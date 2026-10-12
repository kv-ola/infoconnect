<?php

function activityLogDirectory(): string
{
	return __DIR__ . '/activity_logs';
}

function activityLogFilePath(string $date): string
{
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
		throw new InvalidArgumentException('Invalid activity log date.');
	}

	return activityLogDirectory() . '/activity_' . $date . '.txt';
}

function activityLogEncodeField($value): string
{
	return strtr((string) ($value ?? ''), [
		'\\' => '\\\\',
		"\t" => '\\t',
		"\r" => '\\r',
		"\n" => '\\n',
	]);
}

function activityLogDecodeField(string $value): string
{
	return strtr($value, [
		'\\t' => "\t",
		'\\r' => "\r",
		'\\n' => "\n",
		'\\\\' => '\\',
	]);
}

function activityLogAppend(array $entry): void
{
	$directory = activityLogDirectory();
	if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
		throw new RuntimeException('Unable to create the activity log directory.');
	}

	$date = substr((string) ($entry['created_at'] ?? ''), 0, 10);
	$filePath = activityLogFilePath($date);
	$columns = [
		$entry['created_at'] ?? '',
		$entry['user_id'] ?? '',
		$entry['account_id'] ?? '',
		$entry['role'] ?? '',
		$entry['action'] ?? '',
		$entry['module'] ?? '',
		$entry['reference_id'] ?? '',
		$entry['description'] ?? '',
		$entry['ip_address'] ?? '',
		$entry['archive_id'] ?? '',
	];
	$line = implode("\t", array_map('activityLogEncodeField', $columns)) . PHP_EOL;
	$file = fopen($filePath, 'ab');
	if ($file === false) {
		throw new RuntimeException('Unable to open the daily activity log file.');
	}

	try {
		if (!flock($file, LOCK_EX) || fwrite($file, $line) !== strlen($line) || !fflush($file)) {
			throw new RuntimeException('Unable to write to the daily activity log file.');
		}
	} finally {
		flock($file, LOCK_UN);
		fclose($file);
	}
}

function activityLogReadFiles(string $startDate = '', string $endDate = ''): array
{
	$directory = activityLogDirectory();
	if (!is_dir($directory)) {
		return [];
	}

	$paths = glob($directory . '/activity_????-??-??.txt');
	if ($paths === false) {
		throw new RuntimeException('Unable to list the daily activity log files.');
	}

	$entries = [];
	foreach ($paths as $path) {
		$fileDate = substr(basename($path), 9, 10);
		if (($startDate !== '' && $fileDate < $startDate) || ($endDate !== '' && $fileDate > $endDate)) {
			continue;
		}

		$file = fopen($path, 'rb');
		if ($file === false) {
			throw new RuntimeException('Unable to read a daily activity log file.');
		}

		try {
			while (($line = fgets($file)) !== false) {
				$columns = explode("\t", rtrim($line, "\r\n"));
				if (count($columns) !== 10) {
					error_log('Skipping malformed activity log line in ' . basename($path));
					continue;
				}

				$columns = array_map('activityLogDecodeField', $columns);
				$entries[] = [
					'created_at' => $columns[0],
					'user_id' => $columns[1] === '' ? null : (int) $columns[1],
					'account_id' => $columns[2],
					'role' => $columns[3],
					'action' => $columns[4],
					'module' => $columns[5],
					'reference_id' => $columns[6] === '' ? null : (int) $columns[6],
					'description' => $columns[7],
					'ip_address' => $columns[8],
					'archive_id' => $columns[9] === '' ? null : (int) $columns[9],
				];
			}
		} finally {
			fclose($file);
		}
	}

	usort($entries, static function (array $left, array $right): int {
		return strcmp($right['created_at'], $left['created_at']);
	});

	return $entries;
}

function activityLogArchiveDatabase(mysqli $connection): void
{
	$result = $connection->query(
		'SELECT al.id, al.user_id, u.account_id, u.role, al.action, al.module,
		        al.reference_id, al.description, al.ip_address, al.created_at
		 FROM activity_logs al
		 LEFT JOIN users u ON u.id = al.user_id
		 ORDER BY al.created_at ASC, al.id ASC'
	);
	$archivedIds = [];
	foreach (activityLogReadFiles() as $entry) {
		if (isset($entry['archive_id'])) {
			$archivedIds[(int) $entry['archive_id']] = true;
		}
	}

	while ($row = $result->fetch_assoc()) {
		if (isset($archivedIds[(int) $row['id']])) {
			continue;
		}
		$entry = $row;
		$entry['created_at'] = (string) $row['created_at'];
		$entry['archive_id'] = (int) $row['id'];
		activityLogAppend($entry);
	}

	$connection->query('DELETE FROM activity_logs');
}

function activityLogClearDatabase(mysqli $connection): void
{
	$directory = activityLogDirectory();
	$statePath = $directory . '/.database-cleared-date';
	if (!is_file($statePath) || trim((string) file_get_contents($statePath)) === '') {
		throw new RuntimeException('Run the activity log archive migration before recording new activity.');
	}

	$lock = fopen($directory . '/.database-hourly-clear-lock', 'c');
	if ($lock === false || !flock($lock, LOCK_EX)) {
		if (is_resource($lock)) fclose($lock);
		throw new RuntimeException('Unable to lock activity log database cleanup.');
	}
	try {
		$now = time();
		$statePath = $directory . '/.database-last-clear';
		$lastClearedAt = is_file($statePath) ? (int) trim((string) file_get_contents($statePath)) : 0;
		if ($lastClearedAt === 0 || ($now - $lastClearedAt) >= 3600) {
			$connection->query('DELETE FROM activity_logs');
			$tempPath = $statePath . '.tmp';
			if (file_put_contents($tempPath, (string) $now . PHP_EOL, LOCK_EX) === false || !rename($tempPath, $statePath)) {
				@unlink($tempPath);
				throw new RuntimeException('Unable to record the hourly activity log table refresh.');
			}
		}
	} finally {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}

function writeActivityLog(
	mysqli $connection,
	?int $userId,
	string $action,
	string $module,
	string $description,
	?int $referenceId = null
): void {
	$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
	$createdAt = $now->format('Y-m-d H:i:s');
	activityLogClearDatabase($connection);

	$accountId = '';
	$role = '';
	if ($userId !== null) {
		$userStatement = $connection->prepare('SELECT account_id, role FROM users WHERE id = ? LIMIT 1');
		$userStatement->bind_param('i', $userId);
		$userStatement->execute();
		$user = $userStatement->get_result()->fetch_assoc();
		$userStatement->close();
		if ($user) {
			$accountId = (string) ($user['account_id'] ?? '');
			$role = (string) ($user['role'] ?? '');
		}
	}

	$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
	$entry = [
		'created_at' => $createdAt,
		'user_id' => $userId,
		'account_id' => $accountId,
		'role' => $role,
		'action' => $action,
		'module' => $module,
		'reference_id' => $referenceId,
		'description' => $description,
		'ip_address' => $ipAddress,
	];
	activityLogAppend($entry);
}
