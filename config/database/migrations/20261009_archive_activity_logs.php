<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../activity_log.php';

try {
	$countResult = $databaseConnection->query('SELECT COUNT(*) AS total FROM activity_logs');
	$count = (int) $countResult->fetch_assoc()['total'];
	activityLogArchiveDatabase($databaseConnection);

	$directory = activityLogDirectory();
	$statePath = $directory . '/.database-cleared-date';
	if (file_put_contents($statePath . '.tmp', date('Y-m-d') . PHP_EOL, LOCK_EX) === false || !rename($statePath . '.tmp', $statePath)) {
		throw new RuntimeException('Unable to record the activity log archive date.');
	}

	echo "Archived existing database activity rows and cleared the table ({$count} rows).\n";
} catch (Throwable $exception) {
	fwrite(STDERR, 'Activity log archive failed: ' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
