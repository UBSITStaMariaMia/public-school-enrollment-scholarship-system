
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate submitted input types.
    $submittedIdentifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';

    if (
        !is_string($submittedIdentifier) ||
        !is_string($password)
    ) {
        $error = 'Invalid username/email or password.';
    } else {
        $identifier = trim($submittedIdentifier);

        // Validate required input.
        if ($identifier === '' || $password === '') {
            $error = 'Invalid username/email or password.';
        } elseif (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            $error = 'Your session expired. Please try again.';
        } else {
            try {
                // Retrieve the account using a PDO prepared statement.
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

                $account = $statement->fetch(PDO::FETCH_ASSOC);

                // Verify account status and password.
                if (
                    $account &&
                    $account['account_status'] === 'Active' &&
                    password_verify($password, $account['password_hash'])
                ) {

                    // Update the password hash when necessary.
                    if (
                        password_needs_rehash(
                            $account['password_hash'],
                            PASSWORD_DEFAULT
                        )
                    ) {
                        $newHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $updateStatement = $pdo->prepare(
                            'UPDATE users
                             SET password_hash = :password_hash
                             WHERE user_id = :user_id'
                        );

                        $updateStatement->execute([
                            'password_hash' => $newHash,
                            'user_id' => $account['user_id']
                        ]);
                    }

                    // Regenerate session ID after authentication.
                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'user_id' => (int) $account['user_id'],
                        'username' => $account['username'],
                        'email' => $account['email'],
                        'role_name' => $account['role_name'],
                    ];

                    // Redirect according to the user's role.
                    redirect(
                        dashboard_path_for_role($account['role_name'])
                    );

                } else {
                    // Use a generic authentication error.
                    $error = 'Invalid username/email or password.';
                }

            } catch (PDOException $e) {

                // Record technical errors privately.
                error_log('Login database error: ' . $e->getMessage());

                // Do not expose database information to users.
                http_response_code(500);
                $error = 'An unexpected error occurred. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<section class="form-card form-card-narrow">

    <p class="eyebrow">Welcome back</p>

    <h1>Log In</h1>

    <p class="muted">
         Enter your username or email and password to continue.
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
            Sign In
        </button>

    </form>

    <p class="form-footnote">
        Don't have a student account?
        <a href="<?= BASE_URL ?>/register.php">Register here</a>.
    </p>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
