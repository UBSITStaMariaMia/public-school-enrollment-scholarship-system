<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);

$errors = [];
$success = '';

if ($userId <= 0) {
    http_response_code(403);
    exit('Unable to identify the authenticated student account.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
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
                SELECT password_hash
                FROM users
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $query->execute([
                'user_id' => $userId
            ]);

            $account = $query->fetch();

            if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
                $errors[] = 'Your current password is incorrect.';
            } else {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $pdo->prepare("
                    UPDATE users
                    SET password_hash = :password_hash
                    WHERE user_id = :user_id
                ");

                $update->execute([
                    'password_hash' => $newPasswordHash,
                    'user_id' => $userId
                ]);

                $success = 'Your password has been changed successfully.';
            }
        } catch (PDOException $exception) {
            error_log('Student password update error: ' . $exception->getMessage());
            $errors[] = 'Your password could not be changed. Please try again.';
        }
    }

    // Clear password values after processing.
    unset($currentPassword, $newPassword, $confirmPassword);
}

$pageTitle = 'Change Password';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-narrow">
    <p class="eyebrow">Account Security</p>
    <h1>Change Password</h1>

    <p class="muted">
        Update your account password. Enter your current password before choosing a new one.
    </p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $message): ?>
        <div class="alert alert-error">
            <?= e($message) ?>
        </div>
    <?php endforeach; ?>

    <form method="POST" class="form-grid" autocomplete="off">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <label>
            Current Password
            <input
                type="password"
                name="current_password"
                autocomplete="current-password"
                required
            >
        </label>

        <label>
            New Password
            <input
                type="password"
                name="new_password"
                minlength="8"
                autocomplete="new-password"
                required
            >
        </label>

        <label>
            Confirm New Password
            <input
                type="password"
                name="confirm_password"
                minlength="8"
                autocomplete="new-password"
                required
            >
        </label>

        <p class="muted">
            Your new password must contain at least 8 characters.
        </p>

        <div class="form-actions">
            <button type="submit" class="button button-primary button-full">
                Update Password
            </button>
        </div>
    </form>
</section>
