
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
$showCreateForm = isset($_GET['new']);
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$requestedRole = $_GET['role'] ?? '';
$roleFilter = in_array($requestedRole, $managedRoles, true) ? $requestedRole : '';
$listQuery = array_filter(['q' => $search, 'role' => $roleFilter], static fn (string $value): bool => $value !== '');
$accountPath = BASE_URL . '/admin/user_management.php';
$listPath = '/admin/user_management.php' . ($listQuery ? '?' . http_build_query($listQuery) : '');
$listUrl = BASE_URL . $listPath;

$receipt = $_GET['created'] ?? null;
$createdAccount = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_string($receipt)) {
    $createdAccount = $_SESSION['staff_creation_receipts'][$receipt] ?? null;
    unset($_SESSION['staff_creation_receipts'][$receipt]);
    if (!$createdAccount) {
        redirect($listPath);
    }
    header('Cache-Control: no-store');
    $pageTitle = 'Staff Account Created';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    } else {

        $action = $_POST['action'] ?? '';

        // CREATE STAFF ACCOUNT
        if ($action === 'create') {
            $showCreateForm = true;

            $firstName = is_string($_POST['first_name'] ?? null) ? trim($_POST['first_name']) : '';
            $middleName = is_string($_POST['middle_name'] ?? null) ? trim($_POST['middle_name']) : '';
            $lastName = is_string($_POST['last_name'] ?? null) ? trim($_POST['last_name']) : '';
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $roleName = $_POST['role_name'] ?? '';

            if ($firstName === '' || $lastName === '' || max(mb_strlen($firstName), mb_strlen($middleName), mb_strlen($lastName)) > 100) {
                $errors[] = 'Enter a first and last name. Each name can have up to 100 characters.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                $errors[] = 'Enter a valid email address.';
            }
            if (!in_array($roleName, $allowedRoles, true)) {
                $errors[] = 'Select a valid staff role.';
            }

            if (!$errors) {
                try {
                    $roleQuery = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = :role');
                    $roleQuery->execute(['role' => $roleName]);
                    $roleId = $roleQuery->fetchColumn();
                    if (!$roleId) {
                        throw new RuntimeException('The selected role does not exist.');
                    }

                    $surname = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($lastName, 'UTF-8'));
                    $firstNameLetters = preg_replace('/[^\p{L}]+/u', '', mb_strtolower($firstName, 'UTF-8'));
                    if (!$surname || !$firstNameLetters) {
                        throw new RuntimeException('Enter names that contain letters to generate the username.');
                    }

                    $base = mb_strcut($surname, 0, 40, 'UTF-8') . '_' . mb_substr($firstNameLetters, 0, 1, 'UTF-8');
                    $username = $base;
                    $check = $pdo->prepare('SELECT 1 FROM users WHERE username = :username');
                    for ($suffix = 2; ; $suffix++) {
                        $check->execute(['username' => $username]);
                        if (!$check->fetchColumn()) {
                            break;
                        }
                        $username = $base . $suffix;
                    }

                    $employeeId = 'BCNHS-' . strtoupper(bin2hex(random_bytes(8)));
                    $pdo->beginTransaction();
                    $insert = $pdo->prepare("INSERT INTO users
                        (role_id, username, email, password_hash, account_status, must_change_password)
                        VALUES (:role, :username, :email, :password, 'Active', 1)");
                    $insert->execute([
                        'role' => $roleId,
                        'username' => $username,
                        'email' => $email,
                        'password' => password_hash($employeeId, PASSWORD_DEFAULT),
                    ]);
                    $profile = $pdo->prepare("INSERT INTO staff_profiles
                        (user_id, employee_id, first_name, middle_name, last_name)
                        VALUES (:user, :employee, :first, :middle, :last)");
                    $profile->execute([
                        'user' => $pdo->lastInsertId(),
                        'employee' => $employeeId,
                        'first' => $firstName,
                        'middle' => $middleName !== '' ? $middleName : null,
                        'last' => $lastName,
                    ]);
                    $pdo->commit();

                    $receipt = bin2hex(random_bytes(16));
                    $receipts = $_SESSION['staff_creation_receipts'] ?? [];
                    $receipts[$receipt] = ['username' => $username, 'employee_id' => $employeeId];
                    $_SESSION['staff_creation_receipts'] = array_slice($receipts, -5, null, true);
                    redirect('/admin/user_management.php?' . http_build_query($listQuery + ['created' => $receipt]));
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($exception->getCode() === '23000') {
                        $errors[] = 'An account with the same email or generated identifier already exists. Check the email address and try again.';
                    } else {
                        error_log('Staff account creation error: ' . $exception->getMessage());
                        $errors[] = 'Unable to create the account. Please try again.';
                    }
                } catch (RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }

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

                            redirect($listPath);
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

                        redirect($listPath);
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

                        redirect($listPath);
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

if ($editAccount && $errors && ($_POST['action'] ?? '') === 'update') {
    foreach (['username', 'email', 'role_name', 'account_status'] as $field) {
        if (is_string($_POST[$field] ?? null)) {
            $editAccount[$field] = $_POST[$field];
        }
    }
}

$showAccountForm = $editAccount || $showCreateForm;
$staffAccounts = [];

if (!$showAccountForm && !$createdAccount) {
    $staffSql = "SELECT u.user_id, u.username, u.email, u.account_status, u.created_at, r.role_name
        FROM users u
        INNER JOIN roles r ON r.role_id = u.role_id
        WHERE r.role_name IN ('School Registrar', 'Teacher', 'Scholarship Coordinator')";
    $staffParams = [];

    if ($search !== '') {
        $staffSql .= ' AND (u.username LIKE :username OR u.email LIKE :email)';
        $staffParams = ['username' => "%{$search}%", 'email' => "%{$search}%"];
    }

    if ($roleFilter !== '') {
        $staffSql .= ' AND r.role_name = :role';
        $staffParams['role'] = $roleFilter;
    }

    $staffQuery = $pdo->prepare($staffSql . ' ORDER BY r.role_name ASC, u.username ASC');
    $staffQuery->execute($staffParams);
    $staffAccounts = $staffQuery->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($createdAccount): ?>
<section class="form-card staff-account-confirmation" aria-labelledby="staff-created-title">
    <h1 id="staff-created-title">Staff Account Created</h1>
    <dl class="staff-account-credentials">
        <div>
            <dt>Username</dt>
            <dd><?= e($createdAccount['username']) ?></dd>
        </div>
        <div>
            <dt>Staff ID and temporary password</dt>
            <dd><?= e($createdAccount['employee_id']) ?></dd>
        </div>
    </dl>
    <p>Give these details privately to the staff member.</p>
    <div class="form-actions">
        <a class="button button-primary" href="<?= e($listUrl) ?>">Return to Staff Accounts</a>
        <a class="button button-outline" href="<?= e($listUrl . ($listQuery ? '&' : '?') . 'new=1') ?>">Add Another Staff Account</a>
    </div>
</section>
<?php else: ?>

<?php if ($showAccountForm): ?>
    <div class="portal-back-row">
        <a class="portal-back-link" href="<?= e($listUrl) ?>">Back to User Management</a>
    </div>
<?php endif; ?>

<section class="form-card">
    <h1><?= $editAccount ? 'Edit Staff Account' : ($showCreateForm ? 'Create Staff Account' : 'User Management') ?></h1>

    <?php if ($message): ?>
        <p class="alert alert-success" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($editAccount): ?>

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
                    New Password (optional)
                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        placeholder="Leave blank to keep current password"
                        autocomplete="new-password"
                    >
                </label>

            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    Save Changes
                </button>

                <a
                    href="<?= e($listUrl) ?>"
                    class="button button-outline"
                >
                    Cancel
                </a>
            </div>
        </form>
    <?php elseif ($showCreateForm): ?>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input type="hidden" name="action" value="create">

            <div class="user-form-grid">

                <label>
                    First Name
                    <input name="first_name" autocomplete="given-name" maxlength="100" value="<?= e($firstName ?? '') ?>" required>
                </label>
                <label>
                    Middle Name (optional)
                    <input name="middle_name" autocomplete="additional-name" maxlength="100" value="<?= e($middleName ?? '') ?>">
                </label>
                <label>
                    Last Name
                    <input name="last_name" autocomplete="family-name" maxlength="100" value="<?= e($lastName ?? '') ?>" required>
                </label>

                <label>
                    Email Address
                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                        value="<?= e($email ?? '') ?>"
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
                                <?= ($_POST['role_name'] ?? '') === $role ? 'selected' : '' ?>
                            >
                                <?= e($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

            </div>

            <p class="muted">The portal generates a username from the last name and first initial, such as benny_p, and creates an employee ID. The employee ID is the temporary password and must be changed at first sign-in.</p>
            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    Create Staff Account
                </button>
                <a href="<?= e($listUrl) ?>" class="button button-outline">Cancel</a>
            </div>
        </form>

    <?php else: ?>
        <div class="portal-list-toolbar">
            <h2 id="staff-accounts-heading">Staff Accounts</h2>
            <a class="button button-primary portal-add-action" href="<?= e($listUrl . ($listQuery ? '&' : '?') . 'new=1') ?>">
                <span class="portal-add-icon" aria-hidden="true">+</span>Add Staff Account
            </a>
        </div>

        <nav class="application-status-links" aria-label="Filter staff accounts by role">
            <?php foreach (array_merge([''], $managedRoles) as $filterRole): ?>
                <?php
                $roleQuery = array_filter(['q' => $search, 'role' => $filterRole], static fn (string $value): bool => $value !== '');
                $roleUrl = $accountPath . ($roleQuery ? '?' . http_build_query($roleQuery) : '');
                ?>
                <a class="button <?= $roleFilter === $filterRole ? 'button-primary' : 'button-outline' ?>"
                   href="<?= e($roleUrl) ?>" <?= $roleFilter === $filterRole ? 'aria-current="true"' : '' ?>>
                    <?= e($filterRole === '' ? 'All roles' : $filterRole) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="get" class="table-search-form" role="search" aria-label="Staff accounts">
            <?php if ($roleFilter !== ''): ?>
                <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
            <?php endif; ?>
            <div class="table-search-toolbar">
                <div class="table-search-field">
                    <label for="account-search">Search staff accounts</label>
                    <input type="search" id="account-search" name="q" value="<?= e($search) ?>" placeholder="Enter username or email">
                </div>
                <button type="submit" class="button button-primary">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= e($accountPath . ($roleFilter !== '' ? '?' . http_build_query(['role' => $roleFilter]) : '')) ?>" class="button button-outline">Clear search</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$staffAccounts): ?>

            <p><?= $listQuery ? 'No staff accounts match these filters.' : 'No staff accounts have been created yet.' ?></p>

        <?php else: ?>

            <div class="table-container">
                <table class="portal-row-table" aria-labelledby="staff-accounts-heading">
                    <thead>
                        <tr>
                            <th scope="col">Username</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created At</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($staffAccounts as $staff): ?>

                            <tr>
                                <td><?= e($staff['username']) ?></td>

                                <td><?= e($staff['email']) ?></td>

                                <td><?= e($staff['role_name']) ?></td>

                                <td>
                                    <span class="announcement-status<?= $staff['account_status'] === 'Active' ? ' status-published' : '' ?>">
                                        <?= e($staff['account_status']) ?>
                                    </span>
                                </td>

                                <td><?= e($staff['created_at']) ?></td>

                                <td>
                                    <div class="admin-account-actions">

                                        <a
                                            href="<?= e($listUrl . ($listQuery ? '&' : '?') . 'edit=' . (int) $staff['user_id']) ?>"
                                            class="button button-outline portal-record-action"
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
                                                class="button button-outline portal-record-action"
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
                                                class="button portal-record-action button-danger-outline"
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
    <?php endif; ?>
</section>

<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
