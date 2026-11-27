
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'User Management';

// Roles available when creating or updating staff accounts.
$allowedRoles = [
    'School Registrar',
    'Teacher',
    'Scholarship Coordinator'
];

// Roles currently managed by this page.
$managedRoles = [
    'School Registrar',
    'Teacher',
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

        // CREATE STAFF ACCOUNT
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
                        'SELECT role_id
                         FROM roles
                         WHERE role_name = :role_name
                         LIMIT 1'
                    );

                    $roleQuery->execute([
                        'role_name' => $roleName
                    ]);

                    $role = $roleQuery->fetch();

                    if (!$role) {
                        $errors[] = 'The selected role does not exist.';
                    } else {

                        $passwordHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

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

                        redirect('/admin/user_management.php');
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

        // UPDATE STAFF ACCOUNT
        } elseif ($action === 'update') {

            $targetUserId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $roleName = $_POST['role_name'] ?? '';
            $accountStatus = $_POST['account_status'] ?? '';
            $password = $_POST['password'] ?? '';

            if (!$targetUserId) {
                $errors[] = 'Invalid staff account.';
            }

            if (strlen($username) < 3 || strlen($username) > 50) {
                $errors[] = 'Username must be between 3 and 50 characters.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            if (!in_array($roleName, $allowedRoles, true)) {
                $errors[] = 'Please select a valid staff role.';
            }

            if (!in_array($accountStatus, ['Active', 'Inactive', 'Locked'], true)) {
                $errors[] = 'Please select a valid account status.';
            }

            if ($password !== '' && strlen($password) < 8) {
                $errors[] = 'New password must contain at least 8 characters.';
            }

            if (!$errors) {
                try {

                    // Confirm that the selected account belongs to this page.
                    $accountQuery = $pdo->prepare(
                        'SELECT u.user_id
                         FROM users u
                         INNER JOIN roles r ON r.role_id = u.role_id
                         WHERE u.user_id = :user_id
                         AND r.role_name IN (
                            "School Registrar",
                            "Teacher",
                            "Scholarship Coordinator"
                         )
                         LIMIT 1'
                    );

                    $accountQuery->execute([
                        'user_id' => $targetUserId
                    ]);

                    $account = $accountQuery->fetch();

                    if (!$account) {
                        $errors[] = 'The selected staff account could not be found.';
                    } else {

                        $roleQuery = $pdo->prepare(
                            'SELECT role_id
                             FROM roles
                             WHERE role_name = :role_name
                             LIMIT 1'
                        );

                        $roleQuery->execute([
                            'role_name' => $roleName
                        ]);

                        $role = $roleQuery->fetch();

                        if (!$role) {
                            $errors[] = 'The selected role does not exist.';
                        } else {

                            if ($password !== '') {

                                $passwordHash = password_hash(
                                    $password,
                                    PASSWORD_DEFAULT
                                );

                                $update = $pdo->prepare(
                                    'UPDATE users
                                     SET username = :username,
                                         email = :email,
                                         role_id = :role_id,
                                         account_status = :account_status,
                                         password_hash = :password_hash
                                     WHERE user_id = :user_id'
                                );

                                $update->execute([
                                    'username' => $username,
                                    'email' => $email,
                                    'role_id' => $role['role_id'],
                                    'account_status' => $accountStatus,
                                    'password_hash' => $passwordHash,
                                    'user_id' => $targetUserId
                                ]);

                            } else {

                                $update = $pdo->prepare(
                                    'UPDATE users
                                     SET username = :username,
                                         email = :email,
                                         role_id = :role_id,
                                         account_status = :account_status
                                     WHERE user_id = :user_id'
                                );

                                $update->execute([
                                    'username' => $username,
                                    'email' => $email,
                                    'role_id' => $role['role_id'],
                                    'account_status' => $accountStatus,
                                    'user_id' => $targetUserId
                                ]);
                            }

                            $_SESSION['user_management_message'] =
                                'Staff account updated successfully.';

                            $_SESSION['user_management_type'] = 'success';

                            redirect('/admin/user_management.php');
                        }
                    }

                } catch (PDOException $exception) {

                    if ($exception->getCode() === '23000') {
                        $errors[] = 'That username or email address is already registered.';
                    } else {
                        error_log('Staff account update error: ' . $exception->getMessage());
                        $errors[] = 'Unable to update the account. Please try again.';
                    }
                }
            }

        // DELETE STAFF ACCOUNT
        } elseif ($action === 'delete') {

            $targetUserId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$targetUserId) {
                $errors[] = 'Invalid staff account.';
            } elseif ((int) $targetUserId === (int) current_user()['user_id']) {
                $errors[] = 'You cannot delete your own account.';
            } else {

                try {

                    $accountQuery = $pdo->prepare(
                        'SELECT u.user_id
                         FROM users u
                         INNER JOIN roles r ON r.role_id = u.role_id
                         WHERE u.user_id = :user_id
                         AND r.role_name IN (
                            "School Registrar",
                            "Teacher",
                            "Scholarship Coordinator"
                         )
                         LIMIT 1'
                    );

                    $accountQuery->execute([
                        'user_id' => $targetUserId
                    ]);

                    $account = $accountQuery->fetch();

                    if (!$account) {
                        $errors[] = 'The selected staff account could not be found.';
                    } else {

                        // Use a transaction so both delete operations succeed together.
                        $pdo->beginTransaction();

                        // Remove the staff profile connected to this account.
                        $deleteProfile = $pdo->prepare(
                            'DELETE FROM staff_profiles
                             WHERE user_id = :user_id'
                        );

                        $deleteProfile->execute([
                            'user_id' => $targetUserId
                        ]);

                        // Remove the staff account.
                        $deleteUser = $pdo->prepare(
                            'DELETE FROM users
                             WHERE user_id = :user_id'
                        );

                        $deleteUser->execute([
                            'user_id' => $targetUserId
                        ]);

                        $pdo->commit();

                        $_SESSION['user_management_message'] =
                            'Staff account deleted successfully.';

                        $_SESSION['user_management_type'] = 'success';

                        redirect('/admin/user_management.php');
                    }

                } catch (PDOException $exception) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log('Staff account deletion error: ' . $exception->getMessage());
                    $errors[] = 'Unable to delete the account. Please try again.';
                }
            }

        // ACTIVATE OR DEACTIVATE ACCOUNT
        } elseif ($action === 'toggle_status') {

            $targetUserId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$targetUserId) {
                $errors[] = 'Invalid staff account.';
            } else {

                try {

                    $accountQuery = $pdo->prepare(
                        'SELECT u.user_id, u.account_status
                         FROM users u
                         INNER JOIN roles r ON r.role_id = u.role_id
                         WHERE u.user_id = :user_id
                         AND r.role_name IN (
                            "School Registrar",
                            "Teacher",
                            "Scholarship Coordinator"
                         )
                         LIMIT 1'
                    );

                    $accountQuery->execute([
                        'user_id' => $targetUserId
                    ]);

                    $account = $accountQuery->fetch();

                    if (!$account) {
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

                        redirect('/admin/user_management.php');
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

// GET ACCOUNT DETAILS WHEN ADMIN CLICKS EDIT.
$editAccount = null;

if (isset($_GET['edit'])) {

    $editUserId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT
    );

    if ($editUserId) {

        $editQuery = $pdo->prepare(
            'SELECT
                u.user_id,
                u.username,
                u.email,
                u.account_status,
                r.role_name
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE u.user_id = :user_id
             AND r.role_name IN (
                "School Registrar",
                "Teacher",
                "Scholarship Coordinator"
             )
             LIMIT 1'
        );

        $editQuery->execute([
            'user_id' => $editUserId
        ]);

        $editAccount = $editQuery->fetch();

        if (!$editAccount) {
            $errors[] = 'The selected staff account could not be found.';
        }
    }
}

// GET STAFF ACCOUNTS.
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
    <p>Create, update, activate, deactivate, and delete staff accounts.</p>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($editAccount): ?>

    <section class="form-card">
        <h2>Edit Staff Account</h2>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input type="hidden" name="action" value="update">

            <input
                type="hidden"
                name="user_id"
                value="<?= (int) $editAccount['user_id'] ?>"
            >

            <div class="user-form-grid">

                <label>
                    Username
                    <input
                        type="text"
                        name="username"
                        minlength="3"
                        maxlength="50"
                        value="<?= e($editAccount['username']) ?>"
                        required
                    >
                </label>

                <label>
                    Email Address
                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                        value="<?= e($editAccount['email']) ?>"
                        required
                    >
                </label>

                <label>
                    Staff Role
                    <select name="role_name" required>
                        <option value="">Select a role</option>

                        <?php foreach ($allowedRoles as $role): ?>
                            <option
                                value="<?= e($role) ?>"
                                <?= $editAccount['role_name'] === $role ? 'selected' : '' ?>
                            >
                                <?= e($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Account Status
                    <select name="account_status" required>
                        <option
                            value="Active"
                            <?= $editAccount['account_status'] === 'Active' ? 'selected' : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="Inactive"
                            <?= $editAccount['account_status'] === 'Inactive' ? 'selected' : '' ?>
                        >
                            Inactive
                        </option>

                        <option
                            value="Locked"
                            <?= $editAccount['account_status'] === 'Locked' ? 'selected' : '' ?>
                        >
                            Locked
                        </option>
                    </select>
                </label>

                <label>
                    New Password
                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        placeholder="Leave blank to keep current password"
                        autocomplete="new-password"
                    >
                    <small>Only enter a password if you want to change it.</small>
                </label>

            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    Save Changes
                </button>

                <a
                    href="<?= BASE_URL ?>/admin/user_management.php"
                    class="button button-outline"
                >
                    Cancel
                </a>
            </div>
        </form>
    </section>

<?php else: ?>

    <section class="form-card">
        <h2>Create Staff Account</h2>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input type="hidden" name="action" value="create">

            <div class="user-form-grid">

                <label>
                    Username
                    <input
                        type="text"
                        name="username"
                        minlength="3"
                        maxlength="50"
                        value="<?= e($_POST['username'] ?? '') ?>"
                        required
                    >
                </label>

                <label>
                    Email Address
                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                        value="<?= e($_POST['email'] ?? '') ?>"
                        required
                    >
                </label>

                <label>
                    Temporary Password
                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        required
                        autocomplete="new-password"
                    >
                    <small>Use at least 8 characters.</small>
                </label>

                <label>
                    Staff Role
                    <select name="role_name" required>
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
                </label>

            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    Create Staff Account
                </button>
            </div>
        </form>
    </section>

<?php endif; ?>

<section class="form-card">
    <h2>Staff Accounts</h2>

    <?php if (!$staffAccounts): ?>

        <p>No staff accounts have been created yet.</p>

    <?php else: ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($staffAccounts as $staff): ?>

                        <tr>
                            <td><?= e($staff['username']) ?></td>

                            <td><?= e($staff['email']) ?></td>

                            <td><?= e($staff['role_name']) ?></td>

                            <td><?= e($staff['account_status']) ?></td>

                            <td><?= e($staff['created_at']) ?></td>

                            <td>
                                <div class="account-actions">

                                    <a
                                        href="<?= BASE_URL ?>/admin/user_management.php?edit=<?= (int) $staff['user_id'] ?>"
                                        class="button button-outline"
                                    >
                                        Edit
                                    </a>

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
                                            class="button button-outline"
                                        >
                                            <?= $staff['account_status'] === 'Active'
                                                ? 'Deactivate'
                                                : 'Activate' ?>
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        action=""
                                        data-confirm-delete="Are you sure you want to permanently delete this staff account? Its staff profile and related teacher assignments may also be removed."
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $staff['user_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="button button-danger"
                                        >
                                            Delete
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
