<?php
if (session_status() === PHP_SESSION_NONE) {
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $sessionRole = null;
    foreach (['admin', 'staff', 'senior'] as $roleName) {
        if (str_starts_with($scriptPath, '/BMirk/' . $roleName . '/')) {
            $sessionRole = $roleName;
            break;
        }
    }
    if ($sessionRole === null && str_starts_with($scriptPath, '/BMirk/auth/')) {
        $requestedRole = $_GET['portal'] ?? $_POST['portal'] ?? '';
        if (in_array($requestedRole, ['admin', 'staff', 'senior'], true)) {
            $sessionRole = $requestedRole;
        }
    }
    // Give each portal its own cookie. The browser sends all cookies to every
    // tab, so separate names keep signing into another role from replacing this one.
    if ($sessionRole !== null) {
        session_name('BMirk_' . ucfirst($sessionRole));
    }
    $rememberSession = isset($_COOKIE['infoconnect_remember']) && $_COOKIE['infoconnect_remember'] === '1';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'login.php') {
        $rememberSession = isset($_POST['remember_me']);
    }

    if ($rememberSession) {
        ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    }

    session_set_cookie_params([
        'lifetime' => $rememberSession ? 60 * 60 * 24 * 30 : 0,
        'path' => '/BMirk',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function moveSessionToRole(string $role): void
{
    if (!in_array($role, ['admin', 'staff', 'senior'], true)) {
        return;
    }
    $sessionData = $_SESSION;
    session_write_close();
    session_name('BMirk_' . ucfirst($role));
    session_start();
    $_SESSION = $sessionData;
    session_regenerate_id(true);
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /BMirk/auth/login.php');
        exit;
    }
    if (!empty($_SESSION['password_change_required']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'change_password.php') {
        header('Location: /BMirk/auth/change_password.php?portal=' . rawurlencode((string) $_SESSION['role']));
        exit;
    }
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}
