<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}

$errors = [];
$values = ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'email' => '', 'username' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $field => $_) {
        $values[$field] = trim($_POST[$field] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    if ($values['first_name'] === '' || $values['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{4,60}$/', $values['username'])) {
        $errors[] = 'Username must be 4–60 characters and may contain letters, numbers, dots, underscores, and hyphens.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
        $check->execute(['username' => $values['username'], 'email' => $values['email']]);
        if ((int)$check->fetchColumn() > 0) {
            $errors[] = 'That username or email address is already registered.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $roleStatement = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = :role LIMIT 1');
            $roleStatement->execute(['role' => 'Student']);
            $roleId = $roleStatement->fetchColumn();
            if (!$roleId) {
                throw new RuntimeException('Student role is missing from the database. Import database/schema.sql again.');
            }

            $userStatement = $pdo->prepare(
                'INSERT INTO users (role_id, username, email, password_hash)
                 VALUES (:role_id, :username, :email, :password_hash)'
            );
            $userStatement->execute([
                'role_id' => $roleId,
                'username' => $values['username'],
                'email' => $values['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int)$pdo->lastInsertId();

            $studentStatement = $pdo->prepare(
                'INSERT INTO students (user_id, first_name, middle_name, last_name)
                 VALUES (:user_id, :first_name, :middle_name, :last_name)'
            );
            $studentStatement->execute([
                'user_id' => $userId,
                'first_name' => $values['first_name'],
                'middle_name' => $values['middle_name'] !== '' ? $values['middle_name'] : null,
                'last_name' => $values['last_name'],
            ]);

            $pdo->commit();
            $success = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student registration error: ' . $exception->getMessage());
            $errors[] = 'Registration could not be completed. Please check the database setup and try again.';
        }
    }
}

$pageTitle = 'Student Registration';
require_once __DIR__ . '/includes/header.php';
?>
<section class="form-card form-card-wide">
    <p class="eyebrow">Create an account</p>
    <h1>Student Registration</h1>
    <p class="muted">Only students can create portal accounts. Parent and guardian information will be collected separately in the student profile.</p>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">Your student account has been created. <a href="<?= BASE_URL ?>/login.php">Continue to login</a>.</div>
    <?php endif; ?>
    <?php foreach ($errors as $message): ?><div class="alert alert-error"><?= e($message) ?></div><?php endforeach; ?>
    <?php if (empty($success)): ?>
    <form method="post" class="form-grid two-columns">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>First Name<input type="text" name="first_name" value="<?= e($values['first_name']) ?>" required></label>
        <label>Middle Name <span class="muted">(optional)</span><input type="text" name="middle_name" value="<?= e($values['middle_name']) ?>"></label>
        <label>Last Name<input type="text" name="last_name" value="<?= e($values['last_name']) ?>" required></label>
        <label>Email Address<input type="email" name="email" value="<?= e($values['email']) ?>" required></label>
        <label>Username<input type="text" name="username" value="<?= e($values['username']) ?>" required minlength="4" maxlength="60"></label>
        <label>Password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Confirm Password<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
        <div class="form-actions"><button class="button button-primary" type="submit">Create Student Account</button></div>
    </form>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
