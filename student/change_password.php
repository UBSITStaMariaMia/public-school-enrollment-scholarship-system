<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
$user = current_user();
$isStudentPasswordPage = $user['role_name'] === 'Student';
if (!$isStudentPasswordPage && empty($user['must_change_password'])) {
    redirect(dashboard_path_for_role($user['role_name']));
}
$userId = (int) ($user['user_id'] ?? 0);
$firstLogin = !empty($user['must_change_password']);

$errors = [];
$success = '';

if ($userId <= 0) {
    http_response_code(403);
    exit('Unable to identify the authenticated account.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)) {
        http_response_code(400);
        exit('Invalid password submission.');
    }

    if ((!$firstLogin && $currentPassword === '') || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'All password fields are required.';
    }

    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'Your new password must contain at least 8 characters.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'The new password and confirmation do not match.';
    }

    if (
        $currentPassword !== '' &&
        $newPassword !== '' &&
        $currentPassword === $newPassword
    ) {
        $errors[] = 'Your new password must be different from your current password.';
    }

    if (empty($errors)) {
        try {
            $query = $pdo->prepare("
                SELECT password_hash, must_change_password
                FROM users
                WHERE user_id = :user_id AND account_status = 'Active'
                LIMIT 1
            ");

            $query->execute([
                'user_id' => $userId
            ]);

            $account = $query->fetch();

            if (!$account || ($firstLogin && !$account['must_change_password'])) {
                unset($_SESSION['user']);
                session_regenerate_id(true);
                redirect('/index.php');
            }
            if (!$firstLogin && !password_verify($currentPassword, $account['password_hash'])) {
                $errors[] = 'Your current password is incorrect.';
            } elseif (password_verify($newPassword, $account['password_hash'])) {
                $errors[] = 'Your new password must be different from your current password.';
            } else {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $pdo->prepare("
                    UPDATE users
                    SET password_hash = :password_hash, must_change_password = 0
                    WHERE user_id = :user_id AND password_hash = :previous_hash
                      AND account_status = 'Active'
                ");

                $update->execute([
                    'password_hash' => $newPasswordHash,
                    'previous_hash' => $account['password_hash'],
                    'user_id' => $userId
                ]);

                if ($update->rowCount() !== 1) {
                    $errors[] = 'Your password could not be changed. Please sign in again.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user']['must_change_password'] = false;
                    if ($firstLogin) {
                        redirect(dashboard_path_for_role($user['role_name']));
                    }
                    $success = 'Your password has been changed successfully.';
                }
            }
        } catch (PDOException $exception) {
            error_log('Password update error: ' . $exception->getMessage());
            $errors[] = 'Your password could not be changed. Please try again.';
        }
    }

    // Clear password values after processing.
    unset($currentPassword, $newPassword, $confirmPassword);
}

$pageTitle = 'Change Password';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="student-account-shell student-account-shell-narrow password-change-shell">
    <?php if (!$firstLogin): ?>
    <div class="portal-back-row">
        <a class="portal-back-link" href="<?= BASE_URL ?>/student/settings.php">Back to Settings</a>
    </div>
    <?php endif; ?>
    <section class="form-card form-card-narrow">
        <h1>Change Password</h1>

        <p class="muted">
            <?= $firstLogin
                ? 'Choose a new password to continue to the portal.'
                : 'Enter your current password before choosing a new one.' ?>
        </p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $message): ?>
            <div class="alert alert-error" role="alert">
                <?= e($message) ?>
            </div>
        <?php endforeach; ?>

        <form method="POST" class="form-grid password-change-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <p class="muted"><span class="student-required" aria-hidden="true">*</span> Required fields</p>

            <?php if (!$firstLogin): ?>
            <label>
                <span><span class="student-required" aria-hidden="true">*</span> Current Password</span>
                <input
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >
            </label>

            <?php endif; ?>

            <label>
                <span><span class="student-required" aria-hidden="true">*</span> New Password</span>
                <input
                    type="password"
                    name="new_password"
                    aria-describedby="new-password-hint"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
                <small id="new-password-hint" class="muted">Use at least 8 characters.</small>
            </label>

            <label>
                <span><span class="student-required" aria-hidden="true">*</span> Confirm New Password</span>
                <input
                    type="password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            <div class="form-actions">
                <button type="submit" class="button button-primary button-full">
                    Update Password
                </button>
            </div>
        </form>
        <?php if ($firstLogin): ?>
            <button type="button" class="button button-outline" data-logout-trigger>Log Out</button>
        <?php endif; ?>
    </section>
</div>
