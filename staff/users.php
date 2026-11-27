
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'User Management';

$allowedRoles = [
    'School Registrar',
    'Teacher',
    'Enrollment Committee',
    'Scholarship Coordinator'
];

$message = $_SESSION['user_management_message'] ?? '';
$messageType = $_SESSION['user_management_type'] ?? '';

unset(
    $_SESSION['user_management_message'],
    $_SESSION['user_management_type']
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $roleName = $_POST['role_name'] ?? '';

            if (strlen($username) < 3 || strlen($username) > 50) {
                $errors[] = 'Username must be between 3 and 50 characters.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            if (strlen($password) < 8) {
                $errors[] = 'Password must contain at least 8 characters.';
            }

            if (!in_array($roleName, $allowedRoles, true)) {
                $errors[] = 'Please select a valid staff role.';
            }

            if (!$errors) {
                try {
                    $roleQuery = $pdo->prepare(
                        'SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1'
                    );
                    $roleQuery->execute(['role_name' => $roleName]);
                    $role = $roleQuery->fetch();

                    if (!$role) {
                        $errors[] = 'The selected role does not exist.';
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                        $insert = $pdo->prepare(
                            'INSERT INTO users
                                (role_id, username, email, password_hash, account_status)
                             VALUES
                                (:role_id, :username, :email, :password_hash, :account_status)'
                        );

                        $insert->execute([
                            'role_id' => $role['role_id'],
                            'username' => $username,
                            'email' => $email,
                            'password_hash' => $passwordHash,
                            'account_status' => 'Active'
                        ]);

                        $_SESSION['user_management_message'] =
                            'Staff account created successfully.';
                        $_SESSION['user_management_type'] = 'success';

                        redirect('/staff/users.php');
                    }
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $errors[] = 'That username or email address is already registered.';
                    } else {
                        error_log('Staff account creation error: ' . $exception->getMessage());
                        $errors[] = 'Unable to create the account. Please try again.';
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            $targetUserId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$targetUserId) {
                $errors[] = 'Invalid staff account.';
            } elseif ((int) $targetUserId === (int) current_user()['user_id']) {
                $errors[] = 'You cannot deactivate your own account here.';
            } else {
                try {
                    $accountQuery = $pdo->prepare(
                        "SELECT u.user_id, u.account_status, r.role_name
                         FROM users u
                         INNER JOIN roles r ON r.role_id = u.role_id
                         WHERE u.user_id = :user_id
                         LIMIT 1"
                    );

                    $accountQuery->execute(['user_id' => $targetUserId]);
                    $account = $accountQuery->fetch();

                    if (
                        !$account ||
                        !in_array($account['role_name'], $allowedRoles, true)
                    ) {
                        $errors[] = 'The selected staff account could not be found.';
                    } else {
                        $newStatus = $account['account_status'] === 'Active'
                            ? 'Inactive'
                            : 'Active';

                        $update = $pdo->prepare(
                            'UPDATE users
                             SET account_status = :account_status
                             WHERE user_id = :user_id'
                        );

                        $update->execute([
                            'account_status' => $newStatus,
                            'user_id' => $targetUserId
                        ]);

                        $_SESSION['user_management_message'] =
                            'Staff account status updated successfully.';
                        $_SESSION['user_management_type'] = 'success';

                        redirect('/staff/users.php');
                    }
                } catch (PDOException $exception) {
                    error_log('Staff account status error: ' . $exception->getMessage());
                    $errors[] = 'Unable to update the account status.';
                }
            }
        } else {
            $errors[] = 'Invalid action.';
        }
    }
}

$staffQuery = $pdo->prepare(
    "SELECT
        u.user_id,
        u.username,
        u.email,
        u.account_status,
        u.created_at,
        r.role_name
     FROM users u
     INNER JOIN roles r ON r.role_id = u.role_id
     WHERE r.role_name IN (
        'School Registrar',
        'Teacher',
        'Enrollment Committee',
        'Scholarship Coordinator'
     )
     ORDER BY r.role_name ASC, u.username ASC"
);

$staffQuery->execute();
$staffAccounts = $staffQuery->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h1>User Management</h1>

    <?php if ($message): ?>
        <p class="success-message"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="form-card">
    <h2>Create Staff Account</h2>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">

        <div class="form-grid">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    minlength="3"
                    maxlength="50"
                    value="<?= e($_POST['username'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Temporary Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    required
                >
                <small>Use at least 8 characters.</small>
            </div>

            <div class="form-group">
                <label for="role_name">Staff Role</label>
                <select id="role_name" name="role_name" required>
                    <option value="">Select a role</option>

                    <?php foreach ($allowedRoles as $role): ?>
                        <option
                            value="<?= e($role) ?>"
                            <?= ($_POST['role_name'] ?? '') === $role ? 'selected' : '' ?>
                        >
                            <?= e($role) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Create Staff Account
            </button>
        </div>
    </form>
</section>

<section class="form-card">
    <h2>Staff Accounts</h2>

    <?php if (!$staffAccounts): ?>
        <p>No staff accounts have been created yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($staffAccounts as $staff): ?>
                        <tr>
                            <td><?= e($staff['username']) ?></td>
                            <td><?= e($staff['email']) ?></td>
                            <td><?= e($staff['role_name']) ?></td>
                            <td><?= e($staff['account_status']) ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(csrf_token()) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle_status"
                                    >
                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int) $staff['user_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-secondary"
                                        <?= (int) $staff['user_id'] === (int) current_user()['user_id'] ? 'disabled' : '' ?>
                                    >
                                        <?= $staff['account_status'] === 'Active'
                                            ? 'Deactivate'
                                            : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
