
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}

$errors = [];
$success = false;
$generatedUsername = '';

$values = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => ''
];

$password = '';
$confirmPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and validate submitted input types.
    foreach ($values as $field => $_) {
        $submittedValue = $_POST[$field] ?? '';

        if (!is_string($submittedValue)) {
            $errors[] = 'Invalid registration information submitted.';
            continue;
        }

        $values[$field] = trim($submittedValue);

        // Capitalize student names using Title Case.
        if (in_array($field, [
            'first_name',
            'middle_name',
            'last_name'
        ], true)) {
            $values[$field] = mb_convert_case(
                mb_strtolower($values[$field], 'UTF-8'),
                MB_CASE_TITLE,
                'UTF-8'
            );
        }
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_string($password) || !is_string($confirmPassword)) {
        $password = '';
        $confirmPassword = '';
        $errors[] = 'Invalid password information submitted.';
    }

    // CSRF protection.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }

    // Validate required names.
    if ($values['first_name'] === '' || $values['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }

    if (
        strlen($values['first_name']) > 100 ||
        strlen($values['last_name']) > 100
    ) {
        $errors[] = 'Names must not exceed 100 characters.';
    }

    if (strlen($values['middle_name']) > 100) {
        $errors[] = 'Middle name must not exceed 100 characters.';
    }

    // Validate email.
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($values['email']) > 150) {
        $errors[] = 'Email address must not exceed 150 characters.';
    }

    // Validate password.
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    /*
     * Generate username automatically.
     * Format: surname_firstinitialmiddleinitial
     * Example: sta.maria_mk
     */

    if (!$errors) {

        $normalizedSurname = strtolower($values['last_name']);

        // Remove spaces and unsupported characters.
        // Preserve periods in compound surnames.
        $normalizedSurname = preg_replace(
            '/[^a-z0-9.]/',
            '',
            $normalizedSurname
        );

        $firstName = strtolower($values['first_name']);
        $middleName = strtolower($values['middle_name']);

        $firstInitial = substr(
            preg_replace('/[^a-z0-9]/', '', $firstName),
            0,
            1
        );

        $middleInitial = substr(
            preg_replace('/[^a-z0-9]/', '', $middleName),
            0,
            1
        );

        if ($normalizedSurname === '' || $firstInitial === '') {
            $errors[] = 'Unable to generate username. Please check the name entered.';
        } else {

            $initials = $firstInitial . $middleInitial;

            $baseUsername = substr($normalizedSurname, 0, 50)
                . '_'
                . $initials;

            // Check username availability.
            $usernameCheck = $pdo->prepare(
                'SELECT 1
                 FROM users
                 WHERE username = :username
                 LIMIT 1'
            );

            $generatedUsername = $baseUsername;
            $suffix = 2;

            while (true) {

                $usernameCheck->execute([
                    'username' => $generatedUsername
                ]);

                if (!$usernameCheck->fetchColumn()) {
                    break;
                }

                $suffixText = (string) $suffix;

                $generatedUsername = substr(
                    $baseUsername,
                    0,
                    60 - strlen($suffixText)
                ) . $suffixText;

                $suffix++;
            }
        }
    }

    // Check whether email is already registered.
    if (!$errors) {

        $emailCheck = $pdo->prepare(
            'SELECT 1
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $emailCheck->execute([
            'email' => $values['email']
        ]);

        if ($emailCheck->fetchColumn()) {
            $errors[] = 'That email address is already registered.';
        }
    }

    // Create student account.
    if (!$errors) {

        try {

            $pdo->beginTransaction();

            // Retrieve Student role.
            $roleStatement = $pdo->prepare(
                'SELECT role_id
                 FROM roles
                 WHERE role_name = :role
                 LIMIT 1'
            );

            $roleStatement->execute([
                'role' => 'Student'
            ]);

            $roleId = $roleStatement->fetchColumn();

            if (!$roleId) {
                throw new RuntimeException(
                    'Student role is missing from the database.'
                );
            }

            // Hash password before storing it.
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert user account.
            $userStatement = $pdo->prepare(
                'INSERT INTO users
                    (role_id, username, email, password_hash)
                 VALUES
                    (:role_id, :username, :email, :password_hash)'
            );

            $userStatement->execute([
                'role_id' => $roleId,
                'username' => $generatedUsername,
                'email' => $values['email'],
                'password_hash' => $passwordHash
            ]);

            $userId = (int) $pdo->lastInsertId();

            // Insert student information.
            $studentStatement = $pdo->prepare(
                'INSERT INTO students
                    (user_id, first_name, middle_name, last_name)
                 VALUES
                    (:user_id, :first_name, :middle_name, :last_name)'
            );

            $studentStatement->execute([
                'user_id' => $userId,
                'first_name' => $values['first_name'],
                'middle_name' => $values['middle_name'] !== ''
                    ? $values['middle_name']
                    : null,
                'last_name' => $values['last_name']
            ]);

            // Save both database operations.
            $pdo->commit();

            $success = true;

            // Clear submitted information after registration.
            $values = [
                'first_name' => '',
                'middle_name' => '',
                'last_name' => '',
                'email' => ''
            ];

            $password = '';
            $confirmPassword = '';

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Keep technical details in the server log.
            error_log(
                'Student registration error: '
                . $exception->getMessage()
            );

            $errors[] = 'Registration could not be completed. Please try again.';
        }
    }
}

$pageTitle = 'Student Registration';
require_once __DIR__ . '/includes/header.php';
?>

<section class="form-card form-card-wide">

    <p class="eyebrow">Create an account</p>

    <h1>Student Registration</h1>

    <p class="muted">
        Create an account to begin your online enrollment and access student services.
    </p>

    <?php if ($success): ?>

        <div class="alert alert-success">
            Your student account has been created successfully.
            <br>
            Your generated username is:
            <strong><?= e($generatedUsername) ?></strong>
            <br>
            Please remember your username when logging in.
            <br>
            <a href="<?= BASE_URL ?>/login.php">Continue to Log In</a>.
        </div>

    <?php endif; ?>

    <?php foreach ($errors as $message): ?>

        <div class="alert alert-error">
            <?= e($message) ?>
        </div>

    <?php endforeach; ?>

    <?php if (!$success): ?>

        <form method="post" class="form-grid two-columns">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <label>
                First Name
                <input
                    type="text"
                    name="first_name"
                    value="<?= e($values['first_name']) ?>"
                    maxlength="100"
                    autocomplete="given-name"
                    required
                >
            </label>

            <label>
                <span>
                    Middle Name <span class="muted">(Optional)</span>
                </span>

                <input
                    type="text"
                    name="middle_name"
                    value="<?= e($values['middle_name']) ?>"
                    maxlength="100"
                    autocomplete="additional-name"
                >
            </label>

            <label>
                Last Name
                <input
                    type="text"
                    name="last_name"
                    value="<?= e($values['last_name']) ?>"
                    maxlength="100"
                    autocomplete="family-name"
                    required
                >
            </label>

            <label>
                Email Address
                <input
                    type="email"
                    name="email"
                    value="<?= e($values['email']) ?>"
                    maxlength="150"
                    autocomplete="email"
                    required
                >
            </label>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            <label>
                Confirm Password
                <input
                    type="password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            <div class="form-actions">
                <button
                    class="button button-primary"
                    type="submit"
                >
                    Create Student Account
                </button>
            </div>

        </form>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
