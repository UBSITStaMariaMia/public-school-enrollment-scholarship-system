
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $statement = $pdo->prepare(
            'SELECT
                u.user_id,
                u.username,
                u.email,
                u.password_hash,
                u.account_status,
                r.role_name
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE u.username = :username
                OR u.email = :email
             LIMIT 1'
        );

        $statement->execute([
            'username' => $identifier,
            'email' => $identifier
        ]);

        $account = $statement->fetch();

        if (
            $account &&
            $account['account_status'] === 'Active' &&
            password_verify($password, $account['password_hash'])
        ) {
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'user_id' => (int) $account['user_id'],
                'username' => $account['username'],
                'email' => $account['email'],
                'role_name' => $account['role_name'],
            ];

            redirect(dashboard_path_for_role($account['role_name']));
        }

        $error = 'Invalid username/email or password.';
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<section class="form-card form-card-narrow">
    <p class="eyebrow">Welcome back</p>

    <h1>Student and Staff Login</h1>

    <p class="muted">
        Enter your username or email address and password.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <label>
            Username or Email
            <input
                type="text"
                name="identifier"
                value="<?= e($identifier) ?>"
                required
                autocomplete="username"
            >
        </label>

        <label>
            Password
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
            >
        </label>

        <button
            class="button button-primary button-full"
            type="submit"
        >
            Login
        </button>
    </form>

    <p class="form-footnote">
        Don't have a student account?
        <a href="<?= BASE_URL ?>/register.php">Register here</a>.
    </p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
