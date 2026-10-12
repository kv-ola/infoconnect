<?php

function seniorEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function requireSeniorPortalUser(): array
{
	if (($_SESSION['role'] ?? '') !== 'senior') {
		header('Location: /BMirk/auth/login.php', true, 303);
		exit;
	}
	$userId = (int) ($_SESSION['user_id'] ?? 0);
	$statement = $GLOBALS['databaseConnection']->prepare(
		'SELECT id, senior_citizen_id, first_name, middle_name, last_name, suffix
		 FROM senior_profiles WHERE user_id = ? LIMIT 1'
	);
	$statement->bind_param('i', $userId);
	$statement->execute();
	$profile = $statement->get_result()->fetch_assoc();
	$statement->close();
	if (!$profile) {
		http_response_code(403);
		exit('The senior profile linked to this account could not be found.');
	}
	$nameParts = array_filter([$profile['first_name'], $profile['middle_name'], $profile['last_name'], $profile['suffix']]);
	return [
		'user_id' => $userId,
		'profile_id' => (int) $profile['id'],
		'profile' => $profile,
		'name' => implode(' ', $nameParts),
	];
}

function seniorPortalPageStart(string $title, string $name, string $description = ''): void
{
	$pageTitle = seniorEscape($title);
	$seniorFirstName = $name !== '' ? explode(' ', $name)[0] : 'Senior';
	echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
	echo '<title>' . $pageTitle . ' | InfoConnect</title><link rel="stylesheet" href="/BMirk/admin/home.css?v=2"><link rel="stylesheet" href="/BMirk/admin/includes/assets/sidebar.css?v=11"><link rel="stylesheet" href="/BMirk/senior/assets/portal.css?v=3"></head><body>';
	echo '<div class="admin-layout senior-portal-layout">';
	include __DIR__ . '/sidebar.php';
	echo '<main class="admin-main">';
	include __DIR__ . '/header.php';
	echo '<section class="home-content senior-portal-content"><header class="portal-page-heading"><div><h2>' . $pageTitle . '</h2>';
	if ($description !== '') {
		echo '<p>' . seniorEscape($description) . '</p>';
	}
	echo '</div></header>';
}

function seniorPortalPageEnd(): void
{
	echo '</section></main></div><script src="/BMirk/admin/assets/password-visibility.js?v=3" defer></script><script src="/BMirk/assets/unsaved_changes.js?v=1" defer></script></body></html>';
}
