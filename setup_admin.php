
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    exit('Please log out before using this setup page.');
}

$error = '';
$success = '';

$adminExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role_id = 1"
)->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please refresh the page.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($username === '' || strlen($username) > 80) {
            $error = 'Enter a username with no more than 80 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must contain at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM users
                 WHERE username = :username OR email = :email"
            );

            $check->execute([
                'username' => $username,
                'email' => $email
            ]);

            if ((int) $check->fetchColumn() > 0) {
                $error = 'Username or email is already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    "INSERT INTO users
                    (role_id, username, email, password_hash, account_status)
                    VALUES (1, :username, :email, :password_hash, 'Active')"
                );

                $insert->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash
                ]);

                $success = 'System Administrator account created successfully.';
                $adminExists = true;
            }
        }
    }
}

$pageTitle = 'Create Initial Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<main class="page-container">
    <section class="form-card">
        <p class="eyebrow">Initial system setup</p>
        <h1>Create System Administrator</h1>

        <?php if ($success): ?>
            <p class="alert alert-success"><?= e($success) ?></p>
            <p>You can now log in using your administrator credentials.</p>

        <?php elseif ($adminExists): ?>
            <p class="alert alert-error">
                A System Administrator account already exists.
                This setup page can no longer create another one.
            </p>

        <?php else: ?>

            <?php if ($error): ?>
                <p class="alert alert-error"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrf_token()) ?>">

                <label for="username">Administrator Username</label>
                <input type="text" id="username" name="username"
                       maxlength="80" required>

                <label for="email">Administrator Email</label>
                <input type="email" id="email" name="email"
                       maxlength="150" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       minlength="8" required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password"
                       name="confirm_password" minlength="8" required>

                <button type="submit">Create Administrator Account</button>
            </form>

        <?php endif; ?>
    </section>
</main>
</body>
</html>
