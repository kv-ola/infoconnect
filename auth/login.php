<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/activity_log.php';
require_once __DIR__ . '/../config/system_settings.php';

if (($_GET['embed'] ?? $_POST['embed'] ?? '') !== '1') {
    $loginRedirect = '/BMirk/?login=1';
    $requestedForm = $_GET['form'] ?? '';
    if (in_array($requestedForm, ['forgot', 'forgot-reset'], true)) {
        $loginRedirect .= '&form=' . rawurlencode($requestedForm);
    }
    $requestedPortal = $_GET['portal'] ?? '';
    if (in_array($requestedPortal, ['admin', 'staff', 'senior'], true)) {
        $loginRedirect .= '&portal=' . rawurlencode($requestedPortal);
    }
    header('Location: ' . $loginRedirect);
    exit;
}

$page_title = 'InfoConnect | Log In';
$formError = $_SESSION['auth_form_error'] ?? '';
unset($_SESSION['auth_form_error']);
$loginNotice = $_SESSION['password_reset_success'] ?? '';
unset($_SESSION['password_reset_success']);
foreach (['password_recovery_token'] as $tokenName) {
    if (empty($_SESSION[$tokenName])) {
        $_SESSION[$tokenName] = bin2hex(random_bytes(32));
    }
}
$formMode = $_GET['form'] ?? 'login';
if (!in_array($formMode, ['login', 'forgot', 'forgot-reset'], true)) {
    $formMode = 'login';
}
if ($formMode === 'forgot-reset' && (empty($_SESSION['password_reset_user_id']) || empty($_SESSION['password_reset_token']))) {
    $formMode = 'forgot';
}
$formPortal = $_GET['portal'] ?? $_POST['portal'] ?? '';
if (!in_array($formPortal, ['admin', 'staff', 'senior'], true)) {
    $formPortal = '';
}

function redirectAfterLogin(string $location): void
{
    if (($_GET['embed'] ?? $_POST['embed'] ?? '') === '1') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><meta charset="UTF-8"><title>Signing in</title><script>window.top.location.replace('
            . json_encode($location, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            . ');</script></html>';
        exit;
    }
    header('Location: ' . $location);
    exit;
}

if (isset($_SESSION['user_id'])) {
    moveSessionToRole((string) ($_SESSION['role'] ?? ''));
    if (($_SESSION['role'] ?? '') === 'admin') {
        redirectAfterLogin('/BMirk/admin/home.php');
    }
    if (($_SESSION['role'] ?? '') === 'staff') {
        redirectAfterLogin('/BMirk/staff/home.php');
    }
    if (($_SESSION['role'] ?? '') === 'senior') {
        redirectAfterLogin('/BMirk/senior/home.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountId = trim($_POST['account_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $systemSettings = loadSystemSettings($databaseConnection);
    $autoDeactivateDays = max(1, (int) $systemSettings['auto_deactivate_days']);
    $deactivateBefore = (new DateTimeImmutable())->modify('-' . $autoDeactivateDays . ' days')->format('Y-m-d H:i:s');
    $deactivateStatement = $databaseConnection->prepare(
        "UPDATE users SET status = 'inactive'
         WHERE role IN ('staff', 'senior') AND status = 'active'
           AND COALESCE(last_login, created_at) < ?"
    );
    $deactivateStatement->bind_param('s', $deactivateBefore);
    $deactivateStatement->execute();
    $deactivateStatement->close();

    if ($accountId === '' || $password === '') {
        $formError = 'Account ID and password are required.';
    } else {
        $stmt = $databaseConnection->prepare(
            'SELECT id, account_id, password, role, status, must_change_password, created_at,
                    COALESCE((SELECT MAX(ph.created_at) FROM password_history ph WHERE ph.user_id = users.id), created_at) AS password_set_at
             FROM users WHERE account_id = ? LIMIT 1'
        );
        $stmt->bind_param('s', $accountId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $attemptStatement = $databaseConnection->prepare('SELECT failed_attempts, locked_until, updated_at FROM system_login_attempts WHERE account_id = ? LIMIT 1');
        $attemptStatement->bind_param('s', $accountId);
        $attemptStatement->execute();
        $attempt = $attemptStatement->get_result()->fetch_assoc();
        $attemptStatement->close();
        $attemptLimit = max(1, (int) $systemSettings['login_attempt_limit']);

        if (!$user) {
            $formError = 'Invalid account ID or password.';
        } elseif ($attempt && $attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) {
            $formError = 'Too many failed sign-in attempts. Try again after ' . date('h:i A', strtotime($attempt['locked_until'])) . '.';
        } elseif ($user['status'] !== 'active') {
            $formError = 'This account is currently inactive.';
        } elseif (!password_verify($password, $user['password'])) {
            $attemptWindowActive = $attempt && strtotime($attempt['updated_at']) > time() - 15 * 60;
            $failedAttempts = $attemptWindowActive ? (int) $attempt['failed_attempts'] + 1 : 1;
            $lockedUntil = $failedAttempts >= $attemptLimit ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            $failedAttemptStatement = $databaseConnection->prepare(
                'INSERT INTO system_login_attempts (account_id, failed_attempts, locked_until)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE failed_attempts = VALUES(failed_attempts), locked_until = VALUES(locked_until)'
            );
            $failedAttemptStatement->bind_param('sis', $accountId, $failedAttempts, $lockedUntil);
            $failedAttemptStatement->execute();
            $failedAttemptStatement->close();
            $formError = $lockedUntil
                ? 'Too many failed sign-in attempts. Try again after ' . date('h:i A', strtotime($lockedUntil)) . '.'
                : 'Invalid account ID or password.';
        } else {
            $clearAttemptsStatement = $databaseConnection->prepare('DELETE FROM system_login_attempts WHERE account_id = ?');
            $clearAttemptsStatement->bind_param('s', $accountId);
            $clearAttemptsStatement->execute();
            $clearAttemptsStatement->close();
            $lastLoginUpdate = $databaseConnection->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $lastLoginUpdate->bind_param('i', $user['id']);
            $lastLoginUpdate->execute();
            $lastLoginUpdate->close();

            $passwordExpirationDays = max(0, (int) $systemSettings['password_expiration_days']);
            $passwordExpired = $passwordExpirationDays > 0
                && strtotime($user['password_set_at']) <= strtotime('-' . $passwordExpirationDays . ' days');
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['account_id'] = $user['account_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['password_change_required'] = (int) $user['must_change_password'] === 1 || $passwordExpired;
            moveSessionToRole((string) $user['role']);
            writeActivityLog(
                $databaseConnection,
                (int) $user['id'],
                'login',
                'Authentication',
                'Successful sign-in.'
            );

            $rememberCookieOptions = [
                'expires' => isset($_POST['remember_me']) ? time() + 60 * 60 * 24 * 30 : time() - 3600,
                'path' => '/BMirk',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            setcookie('infoconnect_remember', isset($_POST['remember_me']) ? '1' : '', $rememberCookieOptions);

            if ($_SESSION['password_change_required']) {
                redirectAfterLogin('/BMirk/auth/change_password.php?portal=' . rawurlencode((string) $_SESSION['role']));
            }
            if ($user['role'] === 'admin') {
                redirectAfterLogin('/BMirk/admin/home.php');
            }
            if ($user['role'] === 'staff') {
                redirectAfterLogin('/BMirk/staff/home.php');
            }
            redirectAfterLogin('/BMirk/senior/home.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/login.css">
    <link rel="stylesheet" href="assets/login-page.css">
</head>
<body class="login-page<?php echo (($_GET['embed'] ?? '') === '1') ? ' login-embedded' : ''; ?>">
    <div class="page-shell">
        <main class="login-layout">
            <div class="login-container">
                <section class="brand-panel" aria-label="Department branding">
                    <div class="portal-branding">
                        <img class="portal-mark" src="assets/images/infoconnect-mark-white.png" alt="InfoConnect wireless IC logo">
                        <div class="portal-wordmark"><span>Info</span><strong>Connect</strong></div>
                        <div class="portal-rule" aria-hidden="true"></div>
                        <h1>Office for Senior Citizens Affairs</h1>
                        <p>Mandaluyong City</p>
                    </div>
                </section>

                <section class="form-panel" aria-label="Login form">
                    <div class="login-card">
                        <a class="return-link" href="/BMirk/">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m0 0 7-7m-7 7 7 7"/></svg>
                            <span>Return to Portal</span>
                        </a>

                        <div class="card-brand" aria-label="InfoConnect">
                            <img src="assets/images/infoconnect-mark-white.png" alt="" class="card-mark">
                            <div class="card-wordmark"><span>Info</span><strong>Connect</strong></div>
                        </div>
                        <p class="subtitle" data-form-subtitle>Sign in to your account</p>

                        <button class="login-guide-toggle" type="button" aria-expanded="false" aria-controls="login-guide-description">
                            How to log in
                        </button>
                        <div class="mode-description login-guide" id="login-guide-description" hidden>
                            <ol>
								<li>Enter your account ID. Personnel use their employee ID; senior citizens use their senior citizen ID.</li>
                                <li>Enter the default temporary password given to you by OSCA.</li>
                            </ol>
							<p class="login-guide-example"><strong>Example</strong><br>Account ID: <code>your ID number</code><br>Default password: <code>infoconnect</code></p>
                            <small>Use the default password exactly as shown, all lowercase. You will be asked to create a new password after signing in.</small>
                        </div>

                        <?php if ($loginNotice !== ''): ?><div class="success-box" role="status"><?php echo htmlspecialchars($loginNotice, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                        <?php if ($formError !== ''): ?>
                            <div class="error-box" role="alert">
                                <?php echo htmlspecialchars($formError); ?>
                            </div>
                        <?php endif; ?>

                        <form class="auth-form-view" id="login-form" method="POST" action="" <?php echo $formMode !== 'login' ? 'hidden' : ''; ?>>
                        <div class="form-group">
							<label for="account_id">Account ID</label>
                            <div class="field-control">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.5-4 3.4-6 8-6s7.5 2 8 6"/></svg>
								<input id="account_id" name="account_id" type="text" placeholder="Enter your account ID" value="<?php echo htmlspecialchars($_POST['account_id'] ?? ''); ?>" autocomplete="username" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="field-control">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v3"/></svg>
                                <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                                <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="meta-row">
                            <label class="remember">
                                <input type="checkbox" name="remember_me" value="1" <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                                <span>Remember me</span>
                            </label>

                            <button class="form-switch forgot-link" type="button" data-show-form="forgot">Forgot password?</button>
                        </div>

                        <button class="btn-login" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m10-9h5a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-5"/></svg>
                            <span>Login</span>
                        </button>

                        </form>

                        <section class="auth-form-view" id="forgot-form" <?php echo $formMode !== 'forgot' ? 'hidden' : ''; ?> aria-label="Forgot password form">
                            <form method="post" action="forgot_password.php<?php echo $formPortal !== '' ? '?portal=' . rawurlencode($formPortal) : ''; ?>">
                                <input type="hidden" name="step" value="verify">
                                <?php if ($formPortal !== ''): ?><input type="hidden" name="portal" value="<?php echo htmlspecialchars($formPortal, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_recovery_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="form-group">
									<label for="recovery_account_id">Account ID</label>
									<div class="field-control"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.5-4 3.4-6 8-6s7.5 2 8 6"/></svg><input id="recovery_account_id" name="account_id" type="text" maxlength="50" autocomplete="username" placeholder="Enter your account ID" required></div>
                                </div>
                                <div class="form-group">
                                    <label for="recovery_full_name">Full name</label>
                                    <div class="field-control"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.5-4 3.4-6 8-6s7.5 2 8 6"/></svg><input id="recovery_full_name" name="full_name" type="text" maxlength="350" autocomplete="name" placeholder="Enter your full name" required></div>
                                </div>
                                <div class="form-group">
                                    <label for="registered_contact">Registered email or contact number</label>
                                    <div class="field-control"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><input id="registered_contact" name="registered_contact" type="text" maxlength="150" autocomplete="email" placeholder="Enter your registered contact" required></div>
                                </div>
                                <button class="btn-login" type="submit"><span>Verify account</span></button>
                            </form>
                            <div class="mode-links"><button class="form-switch" type="button" data-show-form="login">Back to log in</button></div>
                        </section>

                        <section class="auth-form-view" id="forgot-reset-form" <?php echo $formMode !== 'forgot-reset' ? 'hidden' : ''; ?> aria-label="Choose a new password">
                            <form method="post" action="forgot_password.php<?php echo $formPortal !== '' ? '?portal=' . rawurlencode($formPortal) : ''; ?>">
                                <input type="hidden" name="step" value="reset">
                                <?php if ($formPortal !== ''): ?><input type="hidden" name="portal" value="<?php echo htmlspecialchars($formPortal, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_recovery_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($_SESSION['password_reset_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="form-group"><label for="new_password">New password</label><div class="field-control"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v3"/></svg><input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required></div></div>
                                <div class="form-group"><label for="confirm_password">Confirm new password</label><div class="field-control"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v3"/></svg><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required></div></div>
                                <button class="btn-login" type="submit"><span>Save new password</span></button>
                            </form>
                            <div class="mode-links"><button class="form-switch" type="button" data-show-form="forgot">Back to verification</button><button class="form-switch" type="button" data-show-form="login">Back to log in</button></div>
                        </section>

                    </div>
                </section>
            </div>
        </main>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.querySelector('.password-toggle');
        const passwordEyeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>';
        const passwordEyeOffIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 5.7A9.7 9.7 0 0 1 12 5.6c6.1 0 9.5 6.4 9.5 6.4a15.7 15.7 0 0 1-3.1 3.8M6.2 6.4C3.7 8.1 2.5 12 2.5 12s3.4 6 9.5 6a9.5 9.5 0 0 0 4-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
        passwordToggle.addEventListener('click', function () {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            this.innerHTML = showPassword ? passwordEyeOffIcon : passwordEyeIcon;
            this.setAttribute('aria-pressed', String(showPassword));
            this.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            this.title = showPassword ? 'Hide password' : 'Show password';
        });
        const formSubtitles = {
            login: 'Sign in to your account',
            forgot: 'Verify your account to reset your password',
            'forgot-reset': 'Choose a new secure password',
        };
        function showAuthForm(mode, updateAddress) {
            document.querySelectorAll('.auth-form-view').forEach(function (formView) {
                formView.hidden = formView.id !== mode + '-form';
            });
            document.querySelector('.login-guide-toggle').hidden = mode !== 'login';
            if (mode !== 'login') {
                document.querySelector('.login-guide').hidden = true;
                document.querySelector('.login-guide-toggle').setAttribute('aria-expanded', 'false');
            }
            document.querySelector('[data-form-subtitle]').textContent = formSubtitles[mode];
            if (updateAddress) {
                const url = new URL(window.location.href);
                if (mode === 'login') url.searchParams.delete('form');
                else url.searchParams.set('form', mode);
                window.history.replaceState({}, '', url);
            }
        }
        document.querySelectorAll('[data-show-form]').forEach(function (button) {
            button.addEventListener('click', function () {
                showAuthForm(this.dataset.showForm, true);
            });
        });
        const loginGuideToggle = document.querySelector('.login-guide-toggle');
        const loginGuide = document.querySelector('.login-guide');
        loginGuideToggle.addEventListener('click', function () {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!isExpanded));
            loginGuide.hidden = isExpanded;
        });
        showAuthForm(<?php echo json_encode($formMode); ?>, false);
    </script>
<script src="/BMirk/admin/assets/password-visibility.js?v=3" defer></script>
<script src="/BMirk/assets/unsaved_changes.js?v=1" defer></script>
</body>
</html>
